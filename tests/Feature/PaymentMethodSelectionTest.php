<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaymentMethodSelectionTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_all_four_payment_methods_are_listed_on_the_payment_page(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->get(route('booking.pay', $booking->confirmation_code));

        $response->assertOk();
        foreach (['eSewa', 'Khalti', 'Fonepay', 'Bank Transfer'] as $label) {
            $response->assertSee($label);
        }
    }

    public function test_esewa_is_presented_as_available_with_a_working_payment_form(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->get(route('booking.pay', $booking->confirmation_code));

        $response->assertSee(route('booking.pay.initiate', $booking->confirmation_code), false);
        $response->assertSee('Available');
    }

    public function test_khalti_fonepay_and_bank_transfer_are_presented_as_unavailable(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->get(route('booking.pay', $booking->confirmation_code));

        // The unavailable message is delivered via a data attribute (revealed
        // in a modal on click, so it isn't in the static page text) — assert
        // against the raw HTML rather than assertSeeText.
        $response->assertSee('Khalti payment is currently unavailable. Please choose another payment method or contact the hotel.', false);
        $response->assertSee('Fonepay payment is currently unavailable. Please choose another payment method or contact the hotel.', false);
        $response->assertSee('Bank transfer details are currently unavailable. Please contact the hotel for payment instructions.', false);

        // Rendered 3 times: once per unavailable method card.
        $response->assertSeeTextInOrder(['Coming Soon', 'Coming Soon', 'Coming Soon']);
    }

    public function test_disabled_payment_methods_are_disabled_by_config_not_hardcoded_markup(): void
    {
        $this->assertFalse(config('payments.methods.khalti.enabled'));
        $this->assertFalse(config('payments.methods.fonepay.enabled'));
        $this->assertFalse(config('payments.methods.bank_transfer.enabled'));
        $this->assertTrue(config('payments.methods.esewa.enabled'));
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
