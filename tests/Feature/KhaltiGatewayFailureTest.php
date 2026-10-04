<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * Gateway/API-failure and tampering cases for the Khalti integration, kept
 * separate from KhaltiPaymentTest (which covers the happy path and protocol).
 * All external calls are faked; the key below is a placeholder, never real.
 */
class KhaltiGatewayFailureTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.methods.khalti.enabled' => true,
            'khalti.secret_key' => 'test_secret_key_unit_test_only',
        ]);
    }

    protected function pendingBooking(): Booking
    {
        $room = Room::factory()->create(['price_per_night' => 1500]);

        return Booking::factory()->for($room)->create([
            'nightly_rate' => 1500,
            'total_price' => 4500,
            'nights' => 3,
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    protected function startPayment(Booking $booking, string $pidx): Payment
    {
        Http::fake(['*epayment/initiate/*' => Http::response([
            'pidx' => $pidx,
            'payment_url' => "https://test-pay.khalti.com/?pidx={$pidx}",
            'expires_in' => 1800,
        ], 200)]);

        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code));

        return Payment::where('reference_id', $pidx)->firstOrFail();
    }

    public function test_khalti_rejecting_the_initiation_sends_the_customer_back_instead_of_erroring(): void
    {
        $booking = $this->pendingBooking();
        Http::fake(['*epayment/initiate/*' => Http::response(['detail' => 'Invalid token.'], 401)]);

        $response = $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code));

        $response->assertRedirect(route('booking.pay', $booking->confirmation_code));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 0);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_khalti_being_unreachable_on_initiation_sends_the_customer_back(): void
    {
        $booking = $this->pendingBooking();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $response = $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code));

        $response->assertRedirect(route('booking.pay', $booking->confirmation_code));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unknown_booking_cannot_be_paid_via_khalti(): void
    {
        // Unknown codes get the same access page as someone else's booking.
        $this->post(route('booking.pay.khalti.initiate', 'NW-2026-DOESNT'))->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_khalti_being_unreachable_on_verification_never_confirms_the_booking(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $payment = $this->startPayment($booking, 'pidx-net-1');

        Http::fake(fn () => throw new ConnectionException('timeout'));
        $response = $this->get(route('payment.khalti.return', ['pidx' => 'pidx-net-1']));

        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));
        $this->assertNotEquals(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_a_verification_server_error_never_confirms_the_booking(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $payment = $this->startPayment($booking, 'pidx-500-1');

        Http::fake(['*epayment/lookup/*' => Http::response('Server Error', 500)]);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-500-1']));

        $this->assertNotEquals(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_a_browser_claiming_completed_is_not_believed_when_khalti_lookup_disagrees(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $payment = $this->startPayment($booking, 'pidx-forged-1');

        Http::fake(['*epayment/lookup/*' => Http::response(['pidx' => 'pidx-forged-1', 'total_amount' => 450000, 'status' => 'Pending'], 200)]);
        $this->get(route('payment.khalti.return', [
            'pidx' => 'pidx-forged-1',
            'status' => 'Completed',
            'total_amount' => 450000,
            'amount' => 450000,
            'transaction_id' => 'FORGED',
        ]));

        // Khalti says Pending: held, never completed on the browser's word.
        $this->assertEquals(Payment::STATUS_PENDING, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_an_expired_transaction_is_treated_as_a_failure(): void
    {
        $booking = $this->pendingBooking();
        $payment = $this->startPayment($booking, 'pidx-exp-1');

        Http::fake(['*epayment/lookup/*' => Http::response(['pidx' => 'pidx-exp-1', 'status' => 'Expired'], 400)]);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-exp-1']));

        $this->assertEquals(Payment::STATUS_FAILED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_a_failed_khalti_payment_can_still_be_settled_if_khalti_later_reports_completed(): void
    {
        $booking = $this->pendingBooking();
        $payment = $this->startPayment($booking, 'pidx-late-2');

        Http::fake(['*epayment/lookup/*' => Http::response(['pidx' => 'pidx-late-2', 'total_amount' => 450000, 'status' => 'Expired'], 200)]);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-late-2']));
        $this->assertEquals(Payment::STATUS_FAILED, $payment->refresh()->status);

        Http::swap(new HttpFactory); // a second Http::fake() would only append stubs behind the Pending one
        Http::fake(['*epayment/lookup/*' => Http::response(['pidx' => 'pidx-late-2', 'total_amount' => 450000, 'status' => 'Completed', 'transaction_id' => 'T1'], 200)]);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-late-2']));

        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    public function test_completed_khalti_payment_is_never_confirmed_twice(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->startPayment($booking, 'pidx-twice-1');

        Http::fake(['*epayment/lookup/*' => Http::response(['pidx' => 'pidx-twice-1', 'total_amount' => 450000, 'status' => 'Completed', 'transaction_id' => 'T2'], 200)]);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-twice-1']));
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-twice-1']));

        $this->assertEquals(1, Payment::where('status', Payment::STATUS_COMPLETED)->count());
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }
}
