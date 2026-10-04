<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Services\EsewaPaymentService;
use App\Services\FonepayPaymentService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * B3 — a production deployment can never silently take sandbox payments.
 */
class PaymentProductionSafetyTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    private const LIVE_LOOKING_SECRET = 'not-a-real-live-secret-for-tests';

    protected function production(array $config = []): void
    {
        $this->app['env'] = 'production';
        config($config);

        // Laravel only skips CSRF checks in the "testing" environment.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function pendingBooking(): Booking
    {
        $room = Room::factory()->create(['price_per_night' => 1500]);

        return Booking::factory()->for($room)->create(['status' => Booking::STATUS_PENDING, 'total_price' => 3000]);
    }

    public function test_sandbox_esewa_works_outside_production(): void
    {
        config(['esewa.mode' => 'sandbox', 'esewa.product_code' => 'EPAYTEST', 'esewa.secret_key' => '8gBm/:&EnhH.1/q']);

        $this->assertTrue(app(EsewaPaymentService::class)->isAvailable());
    }

    public function test_production_with_sandbox_esewa_is_switched_off_and_cannot_be_used(): void
    {
        // Exactly what a copied .env.example gives a live server.
        $this->production(['esewa.mode' => 'sandbox', 'esewa.product_code' => 'EPAYTEST', 'esewa.secret_key' => '8gBm/:&EnhH.1/q']);
        $booking = $this->pendingBooking();

        $this->assertFalse(app(EsewaPaymentService::class)->isAvailable());
        $this->get(route('booking.pay', $booking->confirmation_code))->assertOk()->assertDontSee('value="esewa"', false);
        $this->post(route('booking.pay.initiate', $booking->confirmation_code))->assertNotFound();
        $this->post(route('booking.pay.continue', $booking->confirmation_code), ['payment_method' => 'esewa'])
            ->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Payment::count());
    }

    public function test_production_mode_with_public_test_credentials_is_refused(): void
    {
        $this->production(['esewa.mode' => 'production', 'esewa.product_code' => 'EPAYTEST', 'esewa.secret_key' => self::LIVE_LOOKING_SECRET]);
        $this->assertFalse(app(EsewaPaymentService::class)->isAvailable());

        config(['esewa.product_code' => 'NW_LIVE_MERCHANT', 'esewa.secret_key' => '8gBm/:&EnhH.1/q']);
        $this->assertFalse(app(EsewaPaymentService::class)->isAvailable());
    }

    public function test_production_with_explicit_live_configuration_is_allowed(): void
    {
        $this->production(['esewa.mode' => 'production', 'esewa.product_code' => 'NW_LIVE_MERCHANT', 'esewa.secret_key' => self::LIVE_LOOKING_SECRET]);

        $this->assertTrue(app(EsewaPaymentService::class)->isAvailable());
    }

    public function test_production_with_sandbox_fonepay_is_switched_off(): void
    {
        $this->production([
            'payments.methods.fonepay.enabled' => true,
            'fonepay.mode' => 'sandbox',
            'fonepay.merchant_code' => 'DEVMERCHANT',
            'fonepay.secret_key' => self::LIVE_LOOKING_SECRET,
        ]);
        $booking = $this->pendingBooking();

        $this->assertFalse(app(FonepayPaymentService::class)->isAvailable());
        $this->post(route('booking.pay.fonepay.initiate', $booking->confirmation_code))->assertNotFound();
        $this->assertSame(0, Payment::count());

        config(['fonepay.mode' => 'production']);
        $this->assertTrue(app(FonepayPaymentService::class)->isAvailable());
    }

    public function test_the_misconfiguration_is_logged_without_any_secret(): void
    {
        Log::spy();
        $this->production(['esewa.mode' => 'sandbox', 'esewa.product_code' => 'EPAYTEST', 'esewa.secret_key' => self::LIVE_LOOKING_SECRET]);

        app(EsewaPaymentService::class)->isAvailable();

        Log::shouldHaveReceived('error')->withArgs(function ($message, $context = []) {
            $all = $message.json_encode($context);

            return str_contains($all, 'ESEWA_MODE') && ! str_contains($all, self::LIVE_LOOKING_SECRET);
        })->once();
    }

    public function test_check_config_command_fails_on_unsafe_production_config_and_never_prints_secrets(): void
    {
        $this->production(['esewa.mode' => 'sandbox', 'esewa.product_code' => 'EPAYTEST', 'esewa.secret_key' => self::LIVE_LOOKING_SECRET, 'payments.methods.fonepay.enabled' => false]);

        $exit = Artisan::call('payments:check-config');
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('esewa: UNSAFE', $output);
        $this->assertStringContainsString('ESEWA_MODE', $output);
        $this->assertStringNotContainsString(self::LIVE_LOOKING_SECRET, $output);
    }

    public function test_check_config_command_passes_on_a_correct_live_configuration(): void
    {
        $this->production([
            'esewa.mode' => 'production', 'esewa.product_code' => 'NW_LIVE_MERCHANT', 'esewa.secret_key' => self::LIVE_LOOKING_SECRET,
            'payments.methods.fonepay.enabled' => false,
        ]);

        $this->assertSame(0, Artisan::call('payments:check-config'));
    }
}
