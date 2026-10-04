<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmationMail;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use App\Services\BankTransferPaymentService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * Bank Transfer is a manual method — no gateway, so nothing to fake. The
 * guest chooses it, sees the hotel's account details, and the booking is only
 * confirmed when staff record the transfer as received in the admin.
 *
 * The account details (bank name/account name/account number/branch) come
 * from the same admin-editable `settings` table every other Setting uses —
 * NOT from .env — so these tests seed a Setting row rather than config().
 */
class BankTransferPaymentTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.methods.bank_transfer.enabled' => true,
            'bank_transfer.instructions' => null,
            'bank_transfer.hold_hours' => 24,
        ]);

        Setting::factory()->create([
            'bank_name' => 'Test Bank Ltd',
            'bank_account_name' => 'Test Hotel Pvt Ltd',
            'bank_account_number' => '0001112223334',
            'bank_branch' => 'Test Branch',
        ]);
    }

    protected function pendingBooking(array $overrides = []): Booking
    {
        $room = Room::factory()->create(['price_per_night' => 1500]);

        return Booking::factory()->for($room)->create(array_merge([
            'nightly_rate' => 1500,
            'total_price' => 4500,
            'nights' => 3,
            'status' => Booking::STATUS_PENDING,
        ], $overrides));
    }

    protected function chooseBankTransfer(Booking $booking)
    {
        return $this->post(route('booking.pay.bank.initiate', $booking->confirmation_code));
    }

    // --- availability -----------------------------------------------------

    public function test_bank_transfer_is_offered_at_checkout_when_configured(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->get(route('booking.pay', $booking->confirmation_code));

        $response->assertOk();
        $response->assertSee('name="payment_method" value="bank_transfer"', false);
    }

    public function test_bank_transfer_is_offered_before_the_account_number_is_set_and_the_guest_is_told_details_will_follow(): void
    {
        Setting::first()->update(['bank_account_number' => null]);
        app(SettingsService::class)->forget();
        $booking = $this->pendingBooking();

        $page = $this->get(route('booking.pay', $booking->confirmation_code));
        $page->assertSee('name="payment_method" value="bank_transfer"', false);

        $this->chooseBankTransfer($booking)->assertRedirect(route('booking.pay.bank.show', $booking->confirmation_code));
        $this->assertSame('bank_transfer', $booking->payments()->sole()->provider);

        $this->get(route('booking.pay.bank.show', $booking->confirmation_code))
            ->assertOk()
            ->assertSeeText('Our team will send you the Test Bank Ltd account details');
    }

    public function test_bank_transfer_is_unreachable_when_disabled(): void
    {
        config(['payments.methods.bank_transfer.enabled' => false]);
        $booking = $this->pendingBooking();

        $this->chooseBankTransfer($booking)->assertNotFound();
        $this->get(route('booking.pay.bank.show', $booking->confirmation_code))->assertNotFound();
    }

    // --- choosing it ------------------------------------------------------

    public function test_choosing_bank_transfer_records_a_pending_payment_and_never_confirms_the_booking(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking(['total_price' => 4500]);

        // A client-supplied amount is ignored: it is always the booking total.
        $response = $this->post(route('booking.pay.bank.initiate', $booking->confirmation_code), ['amount' => 1]);

        $response->assertRedirect(route('booking.pay.bank.show', $booking->confirmation_code));

        $payment = Payment::first();
        $this->assertEquals('bank_transfer', $payment->provider);
        $this->assertEquals($booking->id, $payment->booking_id);
        $this->assertEquals(4500, (float) $payment->amount);
        $this->assertEquals(Payment::STATUS_PENDING, $payment->status);
        $this->assertNull($payment->paid_at);

        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
        $this->assertFalse($booking->isPaid());

        // Not a confirmation: the guest gets the transfer details + reference,
        // and staff are told a room is now held awaiting a transfer.
        Mail::assertNotQueued(\App\Mail\BookingConfirmationMail::class);
        Mail::assertQueued(\App\Mail\BankTransferInstructionsMail::class, fn ($mail) => $mail->hasTo($booking->email));
        Mail::assertQueued(\App\Mail\OfflinePaymentIntentAdminMail::class, 1);
    }

    public function test_choosing_bank_transfer_repeatedly_does_not_create_duplicate_payments(): void
    {
        $booking = $this->pendingBooking();

        $this->chooseBankTransfer($booking);
        $this->chooseBankTransfer($booking);
        $this->chooseBankTransfer($booking);

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_unknown_cancelled_and_paid_bookings_cannot_choose_bank_transfer(): void
    {
        // Unknown codes get the same access page as someone else's booking.
        $this->post(route('booking.pay.bank.initiate', 'NW-2026-DOESNT'))->assertForbidden();

        $cancelled = $this->pendingBooking(['status' => Booking::STATUS_CANCELLED]);
        $this->chooseBankTransfer($cancelled)->assertNotFound();

        $paid = $this->pendingBooking(['status' => Booking::STATUS_CONFIRMED]);
        Payment::factory()->for($paid)->completed()->create(['amount' => $paid->total_price]);
        $this->chooseBankTransfer($paid)->assertRedirect(route('booking.confirmation', $paid->confirmation_code));

        $this->assertDatabaseCount('payments', 1);
    }

    // --- the instruction page ----------------------------------------------

    public function test_instruction_page_shows_the_account_details_amount_and_reference(): void
    {
        $booking = $this->pendingBooking(['total_price' => 4500]);
        $this->chooseBankTransfer($booking);

        $response = $this->get(route('booking.pay.bank.show', $booking->confirmation_code));

        $response->assertOk();
        $response->assertSeeText('Test Bank Ltd');
        $response->assertSeeText('Test Hotel Pvt Ltd');
        $response->assertSeeText('0001112223334');
        $response->assertSeeText('NPR 4,500');
        $response->assertSeeText($booking->confirmation_code);
        $response->assertSeeText('not confirmed yet');
    }

    public function test_updating_settings_immediately_changes_what_the_bank_transfer_page_shows(): void
    {
        // Proves the single-source-of-truth architecture end-to-end: an
        // admin edit on the EXISTING Settings screen — not .env, not a
        // Bank-Transfer-specific form — is what the guest-facing page reads,
        // including through SettingsService's cache.
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking(['total_price' => 4500]);
        $this->chooseBankTransfer($booking);

        // Warm the cache with the original details, same as a real guest
        // landing on the page before the admin makes any change.
        $this->get(route('booking.pay.bank.show', $booking->confirmation_code))
            ->assertSeeText('Test Bank Ltd')
            ->assertSeeText('0001112223334');

        $this->actingAs($admin, 'admin')->put(route('admin.settings.update'), [
            'site_phone' => '+977 9852690590',
            'site_email' => 'info@newwoodlandhotel.com',
            'site_address' => 'Bhadrapur-8, Jhapa, Nepal',
            'checkin_time' => '12:00',
            'checkout_time' => '10:00',
            'cancellation_window_hours' => 24,
            'bank_name' => 'Everest Bank',
            'bank_account_name' => 'Newwoodland Hotel and Lounge',
            'bank_account_number' => '99988877',
            'bank_branch' => 'Kathmandu',
        ]);

        $response = $this->get(route('booking.pay.bank.show', $booking->confirmation_code));

        $response->assertSeeText('Everest Bank');
        $response->assertSeeText('99988877');
        // The old value must be gone, not merely the new one present —
        // otherwise a stale .env/cache value could linger alongside it.
        $response->assertDontSeeText('Test Bank Ltd');
        $response->assertDontSee('0001112223334');
    }

    public function test_the_bank_transfer_service_itself_reads_account_details_from_settings_not_env(): void
    {
        // Exercises App\Services\BankTransferPaymentService directly (not
        // the Blade template) so this fails if the service ever goes back to
        // config('bank_transfer.*') instead of SettingsService.
        Setting::first()->update([
            'bank_name' => 'Direct Service Bank',
            'bank_account_name' => 'Direct Service Account',
            'bank_account_number' => 'DS-000111',
            'bank_branch' => 'Direct Branch',
        ]);
        app(SettingsService::class)->forget();

        // No matching BANK_TRANSFER_* config exists at all anymore — if the
        // service read from there it would see nulls, not these values.
        config([
            'bank_transfer.bank_name' => null,
            'bank_transfer.account_name' => null,
            'bank_transfer.account_number' => null,
            'bank_transfer.branch' => null,
        ]);

        $service = app(BankTransferPaymentService::class);

        $this->assertTrue($service->isAvailable());
        $this->assertSame([
            'bank_name' => 'Direct Service Bank',
            'account_name' => 'Direct Service Account',
            'account_number' => 'DS-000111',
            'branch' => 'Direct Branch',
            'qr_url' => null,
            'instructions' => null,
        ], $service->accountDetails());
    }

    public function test_the_bank_transfer_service_reports_missing_account_details_without_going_unavailable(): void
    {
        Setting::first()->update(['bank_account_name' => null, 'bank_account_number' => null]);
        app(SettingsService::class)->forget();

        $service = app(BankTransferPaymentService::class);
        $this->assertTrue($service->isAvailable());
        $this->assertFalse($service->hasAccountDetails());
    }

    public function test_account_details_are_not_shown_before_bank_transfer_is_chosen(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->get(route('booking.pay.bank.show', $booking->confirmation_code));

        $response->assertRedirect(route('booking.pay', $booking->confirmation_code));
        $this->assertStringNotContainsString('0001112223334', (string) $response->getContent());
    }

    public function test_instruction_page_is_not_available_for_cancelled_or_paid_bookings(): void
    {
        $cancelled = $this->pendingBooking(['status' => Booking::STATUS_CANCELLED]);
        $this->get(route('booking.pay.bank.show', $cancelled->confirmation_code))->assertNotFound();

        $paid = $this->pendingBooking(['status' => Booking::STATUS_CONFIRMED]);
        Payment::factory()->for($paid)->completed()->create();
        $this->get(route('booking.pay.bank.show', $paid->confirmation_code))
            ->assertRedirect(route('booking.confirmation', $paid->confirmation_code));

        $this->get(route('booking.pay.bank.show', 'NW-2026-DOESNT'))->assertForbidden();
    }

    public function test_confirmation_page_shows_that_a_transfer_is_awaited(): void
    {
        $booking = $this->pendingBooking();
        $this->chooseBankTransfer($booking);

        $response = $this->get(route('booking.confirmation', $booking->confirmation_code));

        $response->assertSeeText('Awaiting your bank transfer');
        $response->assertSee(route('booking.pay.bank.show', $booking->confirmation_code), false);
    }

    // --- the longer hold ----------------------------------------------------

    public function test_a_booking_awaiting_a_bank_transfer_is_held_beyond_the_normal_30_minutes(): void
    {
        $booking = $this->pendingBooking(['check_in' => now()->addDays(10)->toDateString(), 'check_out' => now()->addDays(12)->toDateString()]);
        $booking->forceFill(['created_at' => now()->subHours(2)])->save();
        Payment::factory()->for($booking)->create(['provider' => 'bank_transfer', 'amount' => 4500, 'created_at' => now()->subHours(2)]);

        $this->artisan('bookings:expire-abandoned')->assertSuccessful();

        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
        $this->assertTrue(
            Booking::overlapping($booking->room_id, now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString())->where('id', $booking->id)->exists(),
            'The room should still be held for a guest awaiting a bank transfer.'
        );
    }

    public function test_an_unpaid_booking_without_a_bank_transfer_still_expires_after_30_minutes(): void
    {
        $booking = $this->pendingBooking();
        $booking->forceFill(['created_at' => now()->subHours(2)])->save();

        $this->artisan('bookings:expire-abandoned')->assertSuccessful();

        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->refresh()->status);
    }

    public function test_the_bank_transfer_hold_eventually_expires(): void
    {
        $booking = $this->pendingBooking(['check_in' => now()->addDays(10)->toDateString(), 'check_out' => now()->addDays(12)->toDateString()]);
        $booking->forceFill(['created_at' => now()->subHours(30)])->save();
        Payment::factory()->for($booking)->create(['provider' => 'bank_transfer', 'amount' => 4500, 'created_at' => now()->subHours(25)]);

        $this->assertFalse(
            Booking::overlapping($booking->room_id, now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString())->where('id', $booking->id)->exists()
        );

        $this->artisan('bookings:expire-abandoned')->assertSuccessful();

        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->refresh()->status);
    }

    // --- staff confirming receipt ------------------------------------------

    public function test_staff_confirming_the_transfer_completes_the_same_payment_confirms_the_booking_and_emails_the_guest(): void
    {
        Mail::fake();
        Setting::factory()->create(['site_email' => 'admin@newwoodlandhotel.com']);
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking(['total_price' => 4500]);
        $this->chooseBankTransfer($booking);

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), [
            'provider' => 'bank_transfer',
            'reference' => 'BANKREF-778',
            'amount' => 1, // ignored
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('payments', 1); // completed the pending row, no duplicate

        $payment = Payment::first();
        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->status);
        $this->assertEquals('bank_transfer', $payment->provider);
        $this->assertEquals('BANKREF-778', $payment->reference_id);
        $this->assertEquals(4500, (float) $payment->amount);
        $this->assertNotNull($payment->paid_at);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->refresh()->status);

        Mail::assertQueued(BookingConfirmationMail::class, fn ($mail) => $mail->hasTo($booking->email));
    }

    public function test_staff_can_record_a_transfer_the_guest_never_registered_on_the_site(): void
    {
        Mail::fake();
        Setting::factory()->create();
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking(['total_price' => 3000]);

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'bank_transfer']);

        $this->assertDatabaseCount('payments', 1);
        $this->assertEquals(Payment::STATUS_COMPLETED, Payment::first()->status);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    public function test_a_transfer_cannot_be_recorded_twice(): void
    {
        Mail::fake();
        Setting::factory()->create();
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking();
        $this->chooseBankTransfer($booking);

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'bank_transfer']);
        $second = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'bank_transfer']);

        $second->assertSessionHas('error');
        $this->assertEquals(1, Payment::where('status', Payment::STATUS_COMPLETED)->count());
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }

    public function test_recording_a_cash_payment_does_not_email_the_guest(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking();

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'cash']);

        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_an_unknown_provider_cannot_be_recorded_manually(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking();

        $response = $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'esewa']);

        $response->assertSessionHasErrors('provider');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_guest_cannot_record_a_bank_transfer(): void
    {
        $booking = $this->pendingBooking();

        $this->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'bank_transfer'])->assertRedirect();

        $this->assertDatabaseCount('payments', 0);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_the_admin_booking_page_offers_the_confirm_transfer_action(): void
    {
        $admin = Admin::factory()->create();
        $booking = $this->pendingBooking();
        $this->chooseBankTransfer($booking);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.bookings.show', $booking));

        $response->assertOk();
        $response->assertSeeText('Confirm Bank Transfer Received');
        $response->assertSeeText('Bank Transfer'); // provider shown in the payment panel
    }
}
