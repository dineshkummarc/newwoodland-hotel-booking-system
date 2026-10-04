<?php

namespace Tests\Feature;

use App\Mail\BookingCancelledMail;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\GuestDocument;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use App\Services\BookingService;
use App\Services\SettingsService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Client-confirmed decisions: official photo ID at check-in, a 50% charge
 * when a stay is cancelled after arrival, PAN/VAT on invoices once given,
 * and no invented social links.
 */
class HotelPolicyDecisionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // Hotel policy: free before arrival; 50% after check-in (config).
        Setting::factory()->create(['cancellation_deduction_percent' => 0]);
    }

    protected function stay(string $status = Booking::STATUS_CHECKED_IN, float $paid = 0): Booking
    {
        $booking = Booking::factory()->for(Room::factory())->create([
            'status' => $status, 'nightly_rate' => 2000, 'nights' => 2, 'total_price' => 4000,
            'check_in' => today()->toDateString(), 'check_out' => today()->addDays(2)->toDateString(),
        ]);

        if ($paid > 0) {
            Payment::factory()->create(['booking_id' => $booking->id, 'amount' => $paid, 'status' => Payment::STATUS_COMPLETED, 'paid_at' => now()]);
        }

        return $booking;
    }

    // --- photo ID at check-in ----------------------------------------------

    public function test_accepted_photo_id_types_are_exactly_citizenship_student_id_and_office_id(): void
    {
        $this->assertSame(['citizenship', 'student_id', 'office_id'], array_keys(GuestDocument::TYPES));
    }

    public function test_staff_can_record_a_student_id_but_not_an_unconfirmed_type(): void
    {
        $booking = $this->stay();
        $staff = Admin::factory()->frontDesk()->create();

        $this->actingAs($staff, 'admin')->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'student_id', 'document_number' => 'STU-2026-001',
        ])->assertSessionHas('success');
        $this->assertSame('Student ID Card', $booking->guestDocuments()->sole()->typeLabel());

        $this->actingAs($staff, 'admin')->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'passport', 'document_number' => 'PA123456',
        ])->assertSessionHasErrors('document_type');
        $this->assertSame(1, $booking->guestDocuments()->count());

        $this->actingAs($staff, 'admin')->get(route('admin.bookings.show', $booking))
            ->assertSee('Citizenship Certificate')->assertSee('Student ID Card')->assertSee('Office / Company ID Card')
            ->assertDontSee('<option value="passport"', false);
    }

    public function test_a_record_made_with_an_older_id_type_still_shows_its_label(): void
    {
        $document = $this->stay()->guestDocuments()->create(['document_type' => 'passport', 'document_number' => 'PA1']);

        $this->assertSame('Passport', $document->typeLabel());
    }

    public function test_id_scans_cannot_be_switched_on_from_the_environment(): void
    {
        $this->assertFalse(config('bookings.guest_documents.allow_upload'));
        $this->assertStringNotContainsString('GUEST_DOCUMENTS_ALLOW_UPLOAD', file_get_contents(config_path('bookings.php')));
    }

    public function test_check_in_requires_confirmation_that_a_photo_id_was_presented(): void
    {
        $booking = $this->stay(Booking::STATUS_CONFIRMED);
        $staff = Admin::factory()->frontDesk()->create();

        $this->actingAs($staff, 'admin')->patch(route('admin.bookings.check-in', $booking))->assertSessionHas('error');
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);

        $this->actingAs($staff, 'admin')->patch(route('admin.bookings.check-in', $booking), ['id_verified' => '1'])->assertSessionHas('success');
        $this->assertSame(Booking::STATUS_CHECKED_IN, $booking->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.checked_in', 'target_id' => $booking->id]);
    }

    public function test_a_recorded_photo_id_also_satisfies_the_check_in_rule(): void
    {
        $booking = $this->stay(Booking::STATUS_CONFIRMED);
        $booking->guestDocuments()->create(['document_type' => 'office_id', 'document_number' => 'EMP-42']);

        $this->actingAs(Admin::factory()->create(), 'admin')->patch(route('admin.bookings.check-in', $booking))->assertSessionHas('success');
        $this->assertSame(Booking::STATUS_CHECKED_IN, $booking->fresh()->status);
    }

    public function test_staff_can_record_an_office_id_card(): void
    {
        $booking = $this->stay();

        $this->actingAs(Admin::factory()->frontDesk()->create(), 'admin')->post(route('admin.bookings.documents.store', $booking), [
            'document_type' => 'office_id', 'document_number' => 'EMP-0042', 'issuing_country' => 'Nepal',
        ])->assertSessionHas('success');

        $this->assertSame('Office / Company ID Card', $booking->guestDocuments()->sole()->typeLabel());
    }

    public function test_booking_and_front_desk_pages_show_the_photo_id_confirmation(): void
    {
        $booking = $this->stay(Booking::STATUS_CONFIRMED);
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('name="id_verified"', false);
        $this->actingAs($admin, 'admin')->get(route('admin.front-desk.index'))->assertOk()->assertSee('name="id_verified"', false);
    }

    // --- cancellation after arrival -----------------------------------------

    public function test_cancelling_after_arrival_applies_a_50_percent_charge_and_refunds_the_rest(): void
    {
        $booking = $this->stay(Booking::STATUS_CHECKED_IN, paid: 4000);
        $staff = Admin::factory()->frontDesk()->create();

        $this->actingAs($staff, 'admin')->patch(route('admin.bookings.cancel-stay', $booking))->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->status);
        $this->assertSame(50, $booking->cancellation_charge_percent);
        $this->assertTrue($booking->wasCancelledAfterArrival());
        $this->assertSame(2000.0, $booking->cancellationCharge(0));
        $this->assertSame(2000.0, $booking->refundDue(0));
        $this->assertSame(-2000.0, $booking->outstandingBalance());
        $this->assertSame(4000.0, (float) $booking->total_price, 'The agreed booking amount itself is not rewritten.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.cancelled_after_arrival', 'admin_id' => $staff->id]);
        Mail::assertQueued(BookingCancelledMail::class);
    }

    public function test_the_50_percent_is_frozen_even_if_the_settings_deduction_changes(): void
    {
        $booking = $this->stay(Booking::STATUS_CHECKED_IN, paid: 4000);
        app(BookingService::class)->cancelAfterArrival($booking);

        Setting::query()->update(['cancellation_deduction_percent' => 10]);
        app(SettingsService::class)->forget();

        $this->assertSame(2000.0, $booking->fresh()->refundDue(10));
        $this->assertSame(2000.0, $booking->fresh()->refundDue(0));
    }

    public function test_unpaid_guest_cancelling_after_arrival_owes_the_charge_plus_extras_and_staff_can_record_it(): void
    {
        $booking = $this->stay(Booking::STATUS_CHECKED_IN);
        BookingCharge::create([
            'booking_id' => $booking->id, 'category' => 'restaurant', 'description' => 'Dinner',
            'unit_price' => 500, 'quantity' => 1, 'amount' => 500, 'charged_at' => now(),
        ]);
        $admin = Admin::factory()->frontDesk()->create();

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.cancel-stay', $booking));
        $this->assertSame(2500.0, $booking->fresh()->outstandingBalance());

        $this->actingAs($admin, 'admin')->get(route('admin.bookings.show', $booking))
            ->assertOk()->assertSee('Stay cancelled after arrival')->assertSee('Record Payment Received at the Hotel');

        $this->actingAs($admin, 'admin')->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'cash'])
            ->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame(2500.0, $booking->amountPaid());
        $this->assertSame(0.0, $booking->outstandingBalance());
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->status);
    }

    public function test_only_a_checked_in_stay_can_be_cancelled_after_arrival(): void
    {
        $admin = Admin::factory()->create();

        foreach ([Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_OUT, Booking::STATUS_COMPLETED] as $status) {
            $booking = $this->stay($status);
            $this->actingAs($admin, 'admin')->patch(route('admin.bookings.cancel-stay', $booking))->assertSessionHas('error');
            $this->assertSame($status, $booking->fresh()->status);
        }

        // Signed-out requests are refused.
        $this->app['auth']->forgetGuards();
        $this->patch(route('admin.bookings.cancel-stay', $this->stay()))->assertRedirect(route('admin.login'));
    }

    public function test_pre_arrival_cancellation_is_free_and_payments_stay_blocked(): void
    {
        $booking = Booking::factory()->for(Room::factory())->create([
            'status' => Booking::STATUS_CONFIRMED, 'total_price' => 4000,
            'check_in' => now()->addDays(10)->toDateString(), 'check_out' => now()->addDays(12)->toDateString(),
        ]);
        Payment::factory()->create(['booking_id' => $booking->id, 'amount' => 4000, 'status' => Payment::STATUS_COMPLETED, 'paid_at' => now()]);

        $booking = app(BookingService::class)->cancel($booking);

        $this->assertNull($booking->cancellation_charge_percent);
        $this->assertFalse($booking->wasCancelledAfterArrival());
        // Free before arrival: nothing is kept, the full amount paid is refunded.
        $deduction = app(BookingService::class)->cancellationDeductionPercent();
        $this->assertSame(0, $deduction);
        $this->assertSame(0.0, $booking->cancellationCharge($deduction));
        $this->assertSame(4000.0, $booking->refundDue($deduction));

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(route('admin.bookings.show', $booking))
            ->assertSeeText('Refund due to guest: NPR 4,000');

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'cash'])
            ->assertSessionHas('error');
    }

    public function test_guest_cancellation_email_states_the_after_arrival_charge(): void
    {
        $booking = $this->stay(Booking::STATUS_CHECKED_IN);
        $booking = app(BookingService::class)->cancelAfterArrival($booking);

        $html = (new BookingCancelledMail($booking))->render();

        $this->assertStringContainsString('cancelled after arrival', $html);
        $this->assertStringContainsString('50%', $html);
        $this->assertStringContainsString('NPR 2,000', $html);
    }

    // --- PAN / VAT ------------------------------------------------------------

    public function test_pan_vat_is_empty_until_entered_and_then_printed_on_the_invoice(): void
    {
        $booking = $this->stay(Booking::STATUS_CONFIRMED);
        $render = fn () => view('pdf.invoice', [
            'booking' => $booking->load(['room', 'additionalRooms', 'payments', 'charges', 'refunds']),
            'hotel' => app(SettingsService::class)->current(), 'invoiceNumber' => 'INV-1', 'issuedAt' => now(),
        ])->render();

        $this->assertStringNotContainsString('PAN/VAT', $render());

        $this->actingAs(Admin::factory()->create(), 'admin')->put(route('admin.settings.update'), [
            'site_phone' => '+977 9852690590', 'site_email' => 'info@example.com', 'site_address' => 'Bhadrapur-8, Jhapa, Nepal',
            'checkin_time' => '12:00', 'checkout_time' => '10:00', 'cancellation_window_hours' => 24,
            'pan_vat_number' => '123456789',
        ])->assertSessionHasNoErrors();
        app(SettingsService::class)->forget();

        $this->assertStringContainsString('PAN/VAT No.: <strong>123456789</strong>', $render());
    }

    // --- social links ---------------------------------------------------------

    public function test_social_links_are_not_seeded_with_placeholders(): void
    {
        Setting::query()->delete();
        $this->seed(SettingSeeder::class);

        $setting = Setting::first();
        $this->assertNull($setting->facebook_url);
        $this->assertNull($setting->instagram_url);
        $this->assertNull($setting->youtube_url);
        $this->assertNull($setting->tiktok_url);
    }

    public function test_cleanup_migration_removes_only_generic_placeholder_links(): void
    {
        Setting::query()->update([
            'facebook_url' => 'https://facebook.com',
            'instagram_url' => 'https://www.instagram.com/newwoodlandhotel',
            'youtube_url' => 'https://youtube.com',
        ]);

        (require database_path('migrations/2026_10_02_000003_clear_placeholder_social_links_from_settings.php'))->up();

        $row = DB::table('settings')->first();
        $this->assertNull($row->facebook_url);
        $this->assertNull($row->youtube_url);
        $this->assertSame('https://www.instagram.com/newwoodlandhotel', $row->instagram_url, 'A real profile link is kept.');
    }

    public function test_policy_migration_makes_pre_arrival_cancellation_free_and_fixes_the_seeded_name(): void
    {
        Setting::query()->update([
            'cancellation_deduction_percent' => 50,
            'cancellation_window_hours' => 24,
            'hotel_name' => 'Newwoodland Hotel and Lounge',
            'cancellation_policy_text' => 'Bookings made through this website can be cancelled online up to the notice window before check-in; after that, please contact the hotel directly.',
            'hotel_description' => 'Our own words about the hotel.',
        ]);

        (require database_path('migrations/2026_10_03_000001_apply_confirmed_hotel_policies_to_settings.php'))->up();

        $row = DB::table('settings')->first();
        $this->assertSame(0, (int) $row->cancellation_deduction_percent);
        $this->assertSame(0, (int) $row->cancellation_window_hours);
        $this->assertSame('Newwoodland Hotel & Lounge', $row->hotel_name);
        $this->assertSame('Cancellation before arrival is free. If a stay is cancelled after check-in, 50% of the booking amount is charged.', $row->cancellation_policy_text);
        $this->assertSame('Our own words about the hotel.', $row->hotel_description, 'Text an admin wrote is kept.');
        $this->assertSame(0, app(BookingService::class)->cancellationDeductionPercent(), 'The cached settings were refreshed.');
    }
}
