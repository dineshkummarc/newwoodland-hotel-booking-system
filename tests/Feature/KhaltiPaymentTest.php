<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmationMail;
use App\Mail\NewBookingNotificationMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * KhaltiPaymentService is fully implemented against Khalti's documented
 * e-Payment API (https://docs.khalti.com/khalti-epayment/). These tests use
 * Http::fake() to simulate Khalti's real request/response shapes — exactly
 * the same technique PaymentTest.php already uses for eSewa's status API —
 * so no real network call is ever made. This proves the *code* handles
 * Khalti's documented protocol correctly; it is not a substitute for a real
 * sandbox transaction, which requires an actual KHALTI_SECRET_KEY that does
 * not exist for this project yet (see .env.example).
 */
class KhaltiPaymentTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Only for these unit tests: a placeholder key so the service's
        // isAvailable()/initiate() guards don't short-circuit, and so we can
        // assert the exact Authorization header the real key would produce.
        // Never a real credential, and Http::fake() means it's never sent anywhere.
        config([
            'payments.methods.khalti.enabled' => true,
            'khalti.secret_key' => 'test_secret_key_unit_test_only',
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

    protected function fakeInitiate(string $pidx = 'khalti-pidx-test-1'): void
    {
        Http::fake([
            '*epayment/initiate/*' => Http::response([
                'pidx' => $pidx,
                'payment_url' => "https://test-pay.khalti.com/?pidx={$pidx}",
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
                'expires_in' => 1800,
            ], 200),
        ]);
    }

    protected function fakeLookup(string $pidx, string $status, ?int $totalAmountPaisa = null, ?string $transactionId = 'txn_test_123'): void
    {
        Http::fake([
            '*epayment/lookup/*' => Http::response([
                'pidx' => $pidx,
                'total_amount' => $totalAmountPaisa,
                'status' => $status,
                'transaction_id' => $transactionId,
                'fee' => 0,
                'refunded' => false,
            ], 200),
        ]);
    }

    public function test_initiating_payment_sends_the_correct_amount_and_authorization_header(): void
    {
        $booking = $this->pendingBooking(['total_price' => 4500]);
        $this->fakeInitiate();

        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'epayment/initiate/')
                && $request->hasHeader('Authorization', 'Key test_secret_key_unit_test_only')
                && $request['amount'] === 450000 // NPR 4500 -> paisa
                && $request['customer_info']['email'] !== null;
        });

        $payment = Payment::first();
        $this->assertNotNull($payment);
        $this->assertEquals('khalti', $payment->provider);
        $this->assertEquals($booking->id, $payment->booking_id);
        $this->assertEquals(4500, (float) $payment->amount);
        $this->assertEquals('khalti-pidx-test-1', $payment->reference_id);
        $this->assertEquals(Payment::STATUS_PENDING, $payment->status);
    }

    public function test_amount_sent_to_khalti_cannot_be_manipulated_by_the_client(): void
    {
        $booking = $this->pendingBooking(['total_price' => 4500]);
        $this->fakeInitiate();

        // The controller/service never reads amount from the request at all.
        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code), [
            'amount' => 1,
        ]);

        Http::assertSent(fn ($request) => ($request['amount'] ?? null) === 450000);
    }

    public function test_successful_verified_payment_confirms_the_booking_and_sends_notifications(): void
    {
        Mail::fake();
        Setting::factory()->create(['site_email' => 'admin@newwoodlandhotel.com']);
        $booking = $this->pendingBooking(['total_price' => 4500]);
        $this->fakeInitiate('pidx-success-1');
        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code));

        $this->fakeLookup('pidx-success-1', 'Completed', 450000);
        $response = $this->get(route('payment.khalti.return', ['pidx' => 'pidx-success-1']));

        $payment = Payment::first();
        $payment->refresh();
        $booking->refresh();

        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);
        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));

        Mail::assertQueued(BookingConfirmationMail::class);
        Mail::assertQueued(NewBookingNotificationMail::class);
    }

    public function test_pending_lookup_status_does_not_confirm_booking(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->fakeInitiate('pidx-pending-1');
        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code));

        $this->fakeLookup('pidx-pending-1', 'Pending', 450000);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-pending-1']));

        $payment = Payment::first();
        $payment->refresh();
        $booking->refresh();

        // Khalti's docs: "Pending" must be held — neither paid nor failed.
        $this->assertEquals(Payment::STATUS_PENDING, $payment->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
        Mail::assertNothingOutgoing();
    }

    public function test_user_canceled_status_marks_the_payment_cancelled(): void
    {
        $booking = $this->pendingBooking();
        $this->fakeInitiate('pidx-cancel-1');
        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code));

        $this->fakeLookup('pidx-cancel-1', 'User canceled', 450000);
        $response = $this->get(route('payment.khalti.return', ['pidx' => 'pidx-cancel-1']));

        $payment = Payment::first();
        $payment->refresh();
        $this->assertEquals(Payment::STATUS_CANCELLED, $payment->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));
        $response->assertSessionHas('error', fn ($message) => str_contains($message, 'cancelled'));
    }

    public function test_amount_mismatch_between_gateway_and_booking_is_rejected(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking(['total_price' => 4500]);
        $this->fakeInitiate('pidx-mismatch-1');
        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code));

        // Gateway claims Completed but for a different (tampered) amount.
        $this->fakeLookup('pidx-mismatch-1', 'Completed', 100);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-mismatch-1']));

        $payment = Payment::first();
        $payment->refresh();
        $booking->refresh();
        $this->assertEquals(Payment::STATUS_FAILED, $payment->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
        Mail::assertNothingOutgoing();
    }

    public function test_unknown_pidx_is_handled_gracefully(): void
    {
        $response = $this->get(route('payment.khalti.return', ['pidx' => 'does-not-exist']));

        $response->assertRedirect(route('home'));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_missing_pidx_is_handled_gracefully(): void
    {
        $response = $this->get(route('payment.khalti.return'));

        $response->assertRedirect(route('home'));
    }

    public function test_duplicate_return_callback_is_processed_only_once(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->fakeInitiate('pidx-dup-1');
        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code));

        $this->fakeLookup('pidx-dup-1', 'Completed', 450000);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-dup-1']));
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-dup-1'])); // refresh / duplicate

        $this->assertDatabaseCount('payments', 1);
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }

    public function test_already_paid_booking_cannot_be_paid_again_via_khalti(): void
    {
        $booking = $this->pendingBooking(['status' => Booking::STATUS_CONFIRMED]);
        Payment::factory()->for($booking)->completed()->create(['provider' => 'khalti', 'amount' => $booking->total_price]);

        $response = $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code));

        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_failed_payment_can_be_retried_with_a_new_payment_record(): void
    {
        $booking = $this->pendingBooking();
        Payment::factory()->for($booking)->failed()->create(['provider' => 'khalti', 'amount' => $booking->total_price]);

        $this->fakeInitiate('pidx-retry-1');
        $response = $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code));

        $response->assertRedirect();
        $this->assertDatabaseCount('payments', 2);
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
    }

    public function test_cancelled_booking_cannot_be_paid_via_khalti(): void
    {
        $booking = $this->pendingBooking(['status' => Booking::STATUS_CANCELLED]);

        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code))->assertNotFound();
    }

    public function test_khalti_route_is_unreachable_when_not_enabled(): void
    {
        config(['payments.methods.khalti.enabled' => false]);
        $booking = $this->pendingBooking();

        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code))->assertNotFound();
    }

    public function test_khalti_initiation_without_a_configured_secret_key_fails_safely(): void
    {
        config(['khalti.secret_key' => null]);
        $booking = $this->pendingBooking();

        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code))->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_late_payment_does_not_double_book_a_room_taken_by_another_confirmed_booking(): void
    {
        Mail::fake();
        $room = Room::factory()->create(['price_per_night' => 1500]);

        $bookingA = Booking::factory()->for($room)->create([
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'nightly_rate' => 1500,
            'total_price' => 3000,
            'nights' => 2,
            'status' => Booking::STATUS_PENDING,
        ]);
        $bookingA->created_at = now()->subMinutes(45);
        $bookingA->save();

        $paymentA = Payment::factory()->for($bookingA)->create(['provider' => 'khalti', 'reference_id' => 'pidx-late-1', 'amount' => 3000]);

        Booking::factory()->for($room)->create([
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->fakeLookup('pidx-late-1', 'Completed', 300000);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-late-1']));

        $paymentA->refresh();
        $bookingA->refresh();

        $this->assertEquals(Payment::STATUS_COMPLETED, $paymentA->status);
        $this->assertEquals(Booking::STATUS_PENDING, $bookingA->status);
    }
}
