<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

class PaymentMethodSelectionTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

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

    public function test_accepted_methods_are_listed_and_khalti_never_appears(): void
    {
        // Test env (phpunit.xml) leaves Fonepay and Bank Transfer switched
        // off, so only eSewa is usable here. Paying 100% at the hotel is not
        // a hotel payment option, so there is no cash method at checkout.
        $booking = $this->pendingBooking();

        $response = $this->get(route('booking.pay', $booking->confirmation_code));

        $response->assertOk();
        $response->assertSee('value="esewa"', false);
        $response->assertDontSee('value="cash"', false);
        $response->assertDontSee('Khalti');
        $response->assertDontSee('Fonepay');
        $response->assertDontSee('value="bank_transfer"', false);
    }

    public function test_esewa_is_presented_as_available_with_a_working_payment_form(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->get(route('booking.pay', $booking->confirmation_code));

        $response->assertSee(route('booking.pay.continue', $booking->confirmation_code), false);
        $response->assertSee('name="payment_method" value="esewa"', false);
        $response->assertSee('Available');
        $response->assertSee('Continue Payment');
    }

    public function test_unavailable_methods_are_not_rendered_at_all(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->get(route('booking.pay', $booking->confirmation_code));

        $response->assertDontSee('Coming Soon');
        $response->assertDontSee('payment-method-card--disabled', false);
    }

    public function test_khalti_stays_disabled_even_if_the_env_flag_is_set(): void
    {
        // config/payments.php hard-codes Khalti off (the hotel does not accept
        // it) and full payment at the hotel off (not a hotel payment option).
        $this->assertFalse(config('payments.methods.khalti.enabled'));
        $this->assertFalse(config('payments.methods.cash.enabled'));
        $this->assertTrue(config('payments.methods.esewa.enabled'));
        // Client-confirmed (2026-10-04): advance bookings are paid 100% in
        // advance, so "pay part now" is off unless SPLIT_PAYMENT_ENABLED=true.
        $this->assertFalse(config('payments.split_payment.enabled'));
    }

    public function test_khalti_cannot_be_selected_by_posting_it_directly(): void
    {
        config(['khalti.secret_key' => 'test_secret_key']);
        $booking = $this->pendingBooking();

        $this->post(route('booking.pay.continue', $booking->confirmation_code), ['payment_method' => 'khalti'])
            ->assertRedirect(route('booking.pay', $booking->confirmation_code))
            ->assertSessionHasErrors('payment_method');

        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code))->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_no_route_exists_to_confirm_a_booking_via_khalti_fonepay_or_bank_transfer(): void
    {
        // These providers have no real integration and therefore no
        // callback/initiate routes at all — nothing for a client to spoof.
        foreach (['payment.khalti.success', 'payment.fonepay.success', 'payment.bank.success', 'payment.khalti.initiate', 'payment.fonepay.initiate'] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Route [{$routeName}] should not exist yet.");
        }
    }

    public function test_viewing_the_payment_page_never_creates_a_payment_or_confirms_the_booking(): void
    {
        $booking = $this->pendingBooking();

        $this->get(route('booking.pay', $booking->confirmation_code));

        $this->assertDatabaseCount('payments', 0);
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
        $this->assertFalse($booking->isPaid());
    }

    public function test_payment_provider_label_helper_resolves_known_and_unknown_providers(): void
    {
        $this->assertEquals('eSewa', payment_provider_label('esewa'));
        $this->assertEquals('Khalti', payment_provider_label('khalti'));
        $this->assertEquals('Somegateway', payment_provider_label('somegateway'));
        $this->assertEquals('—', payment_provider_label(null));
    }

    public function test_booking_confirmation_shows_the_correct_provider_label_for_a_completed_payment(): void
    {
        $booking = $this->pendingBooking(['status' => Booking::STATUS_CONFIRMED]);
        Payment::factory()->for($booking)->completed()->create(['provider' => 'esewa']);

        $response = $this->get(route('booking.confirmation', $booking->confirmation_code));

        $response->assertSeeText('Paid via eSewa');
    }
}
