<?php

namespace Tests\Feature;

use App\Mail\BookingCancelledMail;
use App\Models\Admin;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingService;
use Database\Seeders\FacilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * Client booking policies: 5% first-booking discount, pay 100% now or 50%
 * now / 50% at the hotel, free cancellation before arrival (50% after
 * check-in), and the Terms, home page and facilities content that goes with them.
 */
class BookingPoliciesTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::factory()->create([
            'first_booking_discount_percent' => 5,
            // Hotel policy: cancellation before arrival is free.
            'cancellation_deduction_percent' => 0,
            'guest_satisfaction' => '90%+',
        ]);

        $this->room = Room::factory()->create(['price_per_night' => 2000, 'max_guests' => null]);
    }

    protected function book(string $email = 'first@example.com', int $nightsFromNow = 5): Booking
    {
        return app(BookingService::class)->create($this->room, [
            'check_in' => now()->addDays($nightsFromNow)->toDateString(),
            'check_out' => now()->addDays($nightsFromNow + 2)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Test Guest',
            'email' => $email,
            'phone' => '+977 9800000000',
        ]);
    }

    // --- first-booking discount ------------------------------------------------

    public function test_first_booking_gets_the_discount_and_a_repeat_guest_does_not(): void
    {
        $first = $this->book();
        $this->assertEquals(5, (float) $first->discount_percent);
        $this->assertEquals(200, (float) $first->discount_amount);   // 5% of 4,000
        $this->assertEquals(3800, (float) $first->total_price);

        $second = $this->book('FIRST@example.com', 10);   // same guest, any case
        $this->assertEquals(0, (float) $second->discount_amount);
        $this->assertEquals(4000, (float) $second->total_price);
    }

    public function test_an_abandoned_unpaid_booking_does_not_use_up_the_first_booking_discount(): void
    {
        $abandoned = $this->book();
        $abandoned->update(['status' => Booking::STATUS_CANCELLED]);

        $this->assertEquals(200, (float) $this->book('first@example.com', 10)->discount_amount);
    }

    public function test_modifying_a_discounted_booking_keeps_the_discount(): void
    {
        $booking = $this->book();

        $modified = app(BookingService::class)->modify($booking, [
            'check_in' => $booking->check_in->toDateString(),
            'check_out' => $booking->check_in->copy()->addDays(3)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => $booking->customer_name,
            'email' => $booking->email,
            'phone' => $booking->phone,
        ]);

        $this->assertEquals(300, (float) $modified->discount_amount);   // 5% of 6,000
        $this->assertEquals(5700, (float) $modified->total_price);
    }

    public function test_discount_is_advertised_on_the_home_page_and_shown_at_payment(): void
    {
        $this->get(route('home'))
            ->assertSeeText('5% OFF')
            ->assertSeeText('Your first booking is 5% off')
            ->assertSeeText('90%+');

        $booking = $this->book();
        $this->get(route('booking.pay', $booking->confirmation_code))
            ->assertSeeText('First-booking discount (5%)')
            ->assertSeeText('NPR 3,800');
    }

    // --- 50% now, 50% at the hotel --------------------------------------------

    public function test_guest_can_pay_half_online_and_the_rest_is_due_at_the_hotel(): void
    {
        // Optional feature (hotel policy is 100% in advance, so it is off by default).
        config(['payments.split_payment.enabled' => true]);
        Mail::fake();
        $booking = $this->book();   // total 3,800

        $this->get(route('booking.pay', $booking->confirmation_code))
            ->assertSeeText('Pay 100% Now')
            ->assertSeeText('Pay 50% Now + 50% at Hotel')
            ->assertSeeText('NPR 1,900 now, NPR 1,900 — Pay at Hotel')
            ->assertDontSeeText('Cash on Arrival');

        $this->post(route('booking.pay.continue', $booking->confirmation_code), [
            'payment_method' => 'esewa', 'payment_plan' => 'split',
        ])->assertOk();

        $payment = $booking->payments()->sole();
        $this->assertEquals(1900, (float) $payment->amount);
        $this->assertSame(50, $booking->fresh()->deposit_percent);

        // eSewa verifies the half: booking confirmed, half still due.
        $payment->update(['status' => Payment::STATUS_COMPLETED, 'paid_at' => now()]);
        app(BookingService::class)->confirmAfterVerifiedPayment($payment);
        $booking->refresh();
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertEquals(1900, $booking->outstandingBalance());

        $this->get(route('booking.confirmation', $booking->confirmation_code))
            ->assertSeeText('Balance — Pay at Hotel');

        // Staff take the other half in cash on arrival.
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->patch(route('admin.bookings.record-payment', $booking), ['provider' => 'cash']);

        $this->assertEquals(0, $booking->fresh()->outstandingBalance());
        $this->assertEquals(1900, (float) $booking->payments()->where('provider', 'cash')->sole()->amount);
    }

    public function test_bank_transfer_half_payment_asks_for_the_deposit_amount(): void
    {
        config(['payments.methods.bank_transfer.enabled' => true, 'payments.split_payment.enabled' => true]);
        Setting::first()->update(['bank_name' => 'Siddhartha Bank', 'bank_account_number' => '0123']);
        $booking = $this->book();

        $this->post(route('booking.pay.continue', $booking->confirmation_code), [
            'payment_method' => 'bank_transfer', 'payment_plan' => 'split',
        ])->assertRedirect(route('booking.pay.bank.show', $booking->confirmation_code));

        $this->assertEquals(1900, (float) $booking->payments()->sole()->amount);
        $this->get(route('booking.pay.bank.show', $booking->confirmation_code))
            ->assertSeeText('Balance — Pay at Hotel');
    }

    public function test_paying_the_full_amount_at_the_hotel_is_not_offered(): void
    {
        // Hotel policy: 100% in advance, or 50% in advance + 50% at the hotel.
        putenv('CASH_ENABLED=true');
        $booking = $this->book();

        $this->get(route('booking.pay', $booking->confirmation_code))
            ->assertOk()
            ->assertDontSee('value="cash"', false)
            ->assertDontSeeText('Cash on Arrival');

        $this->post(route('booking.pay.continue', $booking->confirmation_code), [
            'payment_method' => 'cash', 'payment_plan' => 'full',
        ])->assertSessionHasErrors('payment_method');

        $this->post(route('booking.pay.cash.initiate', $booking->confirmation_code))->assertNotFound();

        $this->assertSame(0, $booking->payments()->count());
        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
        putenv('CASH_ENABLED');
    }

    // --- 50% deducted on cancellation -----------------------------------------

    public function test_cancellation_before_arrival_is_free_and_the_full_amount_is_refunded(): void
    {
        Mail::fake();
        $booking = $this->book();   // total 3,800
        Payment::factory()->for($booking)->completed()->create(['amount' => 3800, 'provider' => 'esewa']);

        $this->assertEquals(0, $booking->cancellationCharge(app(BookingService::class)->cancellationDeductionPercent()));
        $this->assertEquals(3800, $booking->refundDue(app(BookingService::class)->cancellationDeductionPercent()));

        $this->get($booking->guestCancellationUrl())
            ->assertSeeText('Cancellation before arrival is free')
            ->assertSeeText('NPR 3,800')
            ->assertDontSeeText('is deducted on cancellation');

        $this->post($booking->guestCancellationUrl())->assertRedirect();
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(route('admin.bookings.show', $booking))
            ->assertSeeText('Refund due to guest: NPR 3,800');

        Mail::assertQueued(BookingCancelledMail::class, function (BookingCancelledMail $mail) {
            $html = $mail->render();

            return str_contains($html, 'Cancellation before arrival is free, so the refund due is NPR 3,800')
                && ! str_contains($html, '50%');
        });
    }

    public function test_account_holder_sees_free_cancellation_with_no_notice_window(): void
    {
        $user = User::factory()->create();
        $booking = app(BookingService::class)->create($this->room, [
            'user_id' => $user->id,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'guests_label' => '2 Adults',
            'customer_name' => 'Account Holder',
            'email' => $user->email,
            'phone' => '9812345678',
        ]);

        $this->actingAs($user)->get(route('account.bookings'))
            ->assertOk()
            ->assertSeeText('Cancellation before arrival is free')
            ->assertDontSeeText('hours before check-in');

        // Less than 24 hours' notice is fine: no notice window applies.
        $this->travel(20)->hours();
        $this->actingAs($user)->post(route('account.bookings.cancel', $booking->confirmation_code))->assertSessionHas('success');
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }

    // --- terms, facilities -------------------------------------------------------

    public function test_terms_include_the_hotel_policies(): void
    {
        $this->get(route('terms'))
            ->assertSeeText('photo ID')
            ->assertSeeText('Spitting on the walls')
            ->assertSeeText('Pets are welcome in the outdoor areas')
            ->assertSeeText('not allowed inside the building')
            ->assertSeeText('Cancellation before arrival is free')
            ->assertSeeText('Cancellation after check-in: 50% of the booking amount is charged')
            ->assertDontSeeText('before arrival or during the stay')
            ->assertSeeText('Full payment: 100% of the booking amount paid in advance by QR')
            // Client-confirmed: 100% in advance; the half-payment plan is off by default.
            ->assertDontSeeText('Half payment')
            ->assertDontSeeText('cash at the hotel')
            ->assertSeeText('Citizenship Certificate, Student ID Card or Office / Company ID Card')
            ->assertSeeText('Copies or scans of ID documents are not kept')
            ->assertSeeText('5% discount');
    }

    public function test_facilities_are_six_grouped_cards_without_the_ones_the_hotel_does_not_offer(): void
    {
        // Older one-per-card entries and not-offered facilities are replaced.
        Facility::factory()->create(['title' => 'Elevator']);
        Facility::factory()->create(['title' => 'Outdoor Swimming Pool']);

        $this->seed(FacilitySeeder::class);

        $this->assertSame([
            'Swimming Pools & Garden', 'Restaurant, Bar & Room Service', 'Comfortable Rooms',
            'Free Wi-Fi & Parking', '24-Hour Guest Services', 'Family & Pet Friendly',
        ], Facility::orderBy('sort_order')->pluck('title')->all());

        $this->get(route('facilities'))
            ->assertSeeText("Adult and children's pools")
            ->assertSeeText('Pets allowed outdoors only')
            ->assertDontSeeText('Fitness Center')
            ->assertDontSeeText('Elevator')
            ->assertDontSeeText('Airport Transportation');
    }
}
