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

class PaymentTest extends TestCase
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

    protected function esewaSignature(array $orderedFields): string
    {
        $message = collect($orderedFields)->map(fn ($v, $k) => "{$k}={$v}")->implode(',');

        return base64_encode(hash_hmac('sha256', $message, config('esewa.secret_key'), true));
    }

    /**
     * Build the base64 "data" query param exactly as eSewa would send it on
     * redirect back to our success/failure URL.
     */
    protected function returnPayload(Payment $payment, string $status = 'COMPLETE', ?string $amountOverride = null): string
    {
        $signedFieldNames = 'transaction_code,status,total_amount,transaction_uuid,product_code';

        $fields = [
            'transaction_code' => 'TESTCODE1',
            'status' => $status,
            'total_amount' => $amountOverride ?? number_format((float) $payment->amount, 2, '.', ''),
            'transaction_uuid' => $payment->transaction_uuid,
            'product_code' => config('esewa.product_code'),
        ];

        $signPayload = [];
        foreach (explode(',', $signedFieldNames) as $name) {
            $signPayload[$name] = $fields[$name];
        }

        $fields['signed_field_names'] = $signedFieldNames;
        $fields['signature'] = $this->esewaSignature($signPayload);

        return base64_encode(json_encode($fields));
    }

    protected function fakeGatewayStatus(Payment $payment, string $status = 'COMPLETE', ?float $amount = null): void
    {
        Http::fake([
            '*transaction/status*' => Http::response([
                'product_code' => config('esewa.product_code'),
                'transaction_uuid' => $payment->transaction_uuid,
                'total_amount' => number_format($amount ?? (float) $payment->amount, 2, '.', ''),
                'status' => $status,
                'ref_id' => 'REF1234567',
            ], 200),
        ]);
    }

    public function test_initiating_payment_creates_a_record_linked_to_the_correct_booking_and_amount(): void
    {
        $booking = $this->pendingBooking();

        $this->post(route('booking.pay.initiate', $booking->confirmation_code));

        $payment = Payment::first();
        $this->assertNotNull($payment);
        $this->assertEquals($booking->id, $payment->booking_id);
        $this->assertEquals(4500, (float) $payment->amount);
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals('esewa', $payment->provider);
    }

    public function test_payment_amount_cannot_be_manipulated_by_the_client(): void
    {
        $booking = $this->pendingBooking(['total_price' => 4500]);

        // Attempt to inject an arbitrary amount via the request — the
        // controller/service never reads amount from the request at all,
        // it is always taken from the booking's own stored total_price.
        $this->post(route('booking.pay.initiate', $booking->confirmation_code), [
            'amount' => 1,
            'total_amount' => 1,
        ]);

        $payment = Payment::first();
        $this->assertEquals(4500, (float) $payment->amount);
    }

    public function test_successful_verified_payment_confirms_the_booking_and_sends_notifications(): void
    {
        Mail::fake();
        Setting::factory()->create(['site_email' => 'admin@newwoodlandhotel.com']);
        $booking = $this->pendingBooking();
        $this->post(route('booking.pay.initiate', $booking->confirmation_code));
        $payment = Payment::first();

        $this->fakeGatewayStatus($payment);

        $response = $this->get(route('payment.esewa.success', ['data' => $this->returnPayload($payment)]));

        $payment->refresh();
        $booking->refresh();

        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->status);
        $this->assertEquals('REF1234567', $payment->reference_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);

        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));

        Mail::assertQueued(BookingConfirmationMail::class);
        Mail::assertQueued(NewBookingNotificationMail::class);
    }

    public function test_gateway_reporting_incomplete_status_does_not_confirm_booking(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->post(route('booking.pay.initiate', $booking->confirmation_code));
        $payment = Payment::first();

        $this->fakeGatewayStatus($payment, status: 'PENDING');

        $this->get(route('payment.esewa.success', ['data' => $this->returnPayload($payment, status: 'PENDING')]));

        $payment->refresh();
        $booking->refresh();

        // eSewa PENDING means money may be in flight: NOT confirmed, but also
        // NOT failed — it stays pending for reconciliation to settle later.
        $this->assertEquals(Payment::STATUS_PENDING, $payment->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
        Mail::assertNothingOutgoing();
    }

    public function test_amount_mismatch_between_gateway_and_booking_is_rejected(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking(['total_price' => 4500]);
        $this->post(route('booking.pay.initiate', $booking->confirmation_code));
        $payment = Payment::first();

        // Gateway claims COMPLETE but for a different (tampered/incorrect) amount.
        $this->fakeGatewayStatus($payment, status: 'COMPLETE', amount: 1.00);

        $this->get(route('payment.esewa.success', ['data' => $this->returnPayload($payment, amountOverride: '1.00')]));

        $payment->refresh();
        $booking->refresh();

        $this->assertEquals(Payment::STATUS_FAILED, $payment->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
        Mail::assertNothingOutgoing();
    }

    public function test_tampered_signature_is_rejected(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->post(route('booking.pay.initiate', $booking->confirmation_code));
        $payment = Payment::first();

        // eSewa itself has no payment for this transaction.
        $this->fakeGatewayStatus($payment, status: 'NOT_FOUND');

        $payload = $this->returnPayload($payment);
        $decoded = json_decode(base64_decode($payload), true);
        $decoded['status'] = 'COMPLETE'; // unchanged, but signature no longer matches after this mutation
        $decoded['total_amount'] = '1.00'; // tamper post-signing
        $tampered = base64_encode(json_encode($decoded));

        $this->get(route('payment.esewa.success', ['data' => $tampered]));

        $payment->refresh();
        $booking->refresh();

        // A forged "COMPLETE" redirect decides nothing: only eSewa's own
        // status API does, and it has no payment — nothing is paid/confirmed.
        $this->assertEquals(Payment::STATUS_PENDING, $payment->status);
        $this->assertFalse($booking->isPaid());
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
        Mail::assertNothingOutgoing();
    }

    public function test_cancelling_at_the_gateway_before_any_data_is_returned_sends_customer_back_to_their_booking(): void
    {
        // eSewa does not always include a "data" query param on return — a
        // customer who cancels at eSewa's own login screen, before any
        // transaction is attempted there, comes back with none at all.
        // (Confirmed against eSewa's real sandbox: this is a genuine gateway
        // behavior, not a hypothetical.)
        $booking = $this->pendingBooking();
        $this->post(route('booking.pay.initiate', $booking->confirmation_code));

        $response = $this->get(route('payment.esewa.failure'));

        $response->assertRedirect(route('booking.pay', $booking->confirmation_code));
        $response->assertSessionHas('error');
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
    }

    public function test_unresolved_payment_fallback_does_not_apply_without_a_prior_initiate(): void
    {
        // No booking.pay.initiate call in this test, so no session pointer
        // exists — must still fall back to home exactly as before.
        $response = $this->get(route('payment.esewa.failure'));

        $response->assertRedirect(route('home'));
    }

    public function test_unknown_transaction_uuid_is_handled_gracefully(): void
    {
        $bogus = base64_encode(json_encode([
            'transaction_uuid' => 'does-not-exist-in-our-db',
            'status' => 'COMPLETE',
            'total_amount' => '4500.00',
            'product_code' => config('esewa.product_code'),
            'signed_field_names' => 'total_amount,transaction_uuid,product_code',
            'signature' => 'invalid',
        ]));

        $response = $this->get(route('payment.esewa.success', ['data' => $bogus]));

        // Must not throw a 500 / leak a stack trace — redirects gracefully.
        $response->assertRedirect(route('home'));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_duplicate_success_callback_is_processed_only_once(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->post(route('booking.pay.initiate', $booking->confirmation_code));
        $payment = Payment::first();

        $this->fakeGatewayStatus($payment);
        $payload = $this->returnPayload($payment);

        $this->get(route('payment.esewa.success', ['data' => $payload]));
        $this->get(route('payment.esewa.success', ['data' => $payload])); // refresh / duplicate callback

        $this->assertDatabaseCount('payments', 1);
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }

    public function test_already_paid_booking_cannot_be_paid_again(): void
    {
        $booking = $this->pendingBooking(['status' => Booking::STATUS_CONFIRMED]);
        Payment::factory()->for($booking)->completed()->create(['amount' => $booking->total_price]);

        $response = $this->get(route('booking.pay', $booking->confirmation_code));
        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));

        $initiateResponse = $this->post(route('booking.pay.initiate', $booking->confirmation_code));
        $initiateResponse->assertRedirect(route('booking.confirmation', $booking->confirmation_code));

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_failed_payment_can_be_retried_with_a_new_payment_record(): void
    {
        $booking = $this->pendingBooking();
        Payment::factory()->for($booking)->failed()->create(['amount' => $booking->total_price]);

        $response = $this->post(route('booking.pay.initiate', $booking->confirmation_code));
        $response->assertOk();

        $this->assertDatabaseCount('payments', 2);
        $booking->refresh();
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
    }

    public function test_cancelled_booking_cannot_be_paid(): void
    {
        $booking = $this->pendingBooking(['status' => Booking::STATUS_CANCELLED]);

        $this->get(route('booking.pay', $booking->confirmation_code))->assertNotFound();
        $this->post(route('booking.pay.initiate', $booking->confirmation_code))->assertNotFound();
    }

    public function test_late_payment_does_not_double_book_a_room_taken_by_another_confirmed_booking(): void
    {
        Mail::fake();
        $room = Room::factory()->create(['price_per_night' => 1500]);

        // Booking A goes pending, then effectively abandons checkout long
        // enough to age out of the availability hold.
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

        $paymentA = Payment::factory()->for($bookingA)->create(['amount' => 3000]);

        // Someone else books and is confirmed for the same room/dates,
        // which is legitimate since A had aged out of the hold.
        Booking::factory()->for($room)->create([
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // A's payment (captured by the gateway) now comes back late.
        $this->fakeGatewayStatus($paymentA);
        $this->get(route('payment.esewa.success', ['data' => $this->returnPayload($paymentA)]));

        $paymentA->refresh();
        $bookingA->refresh();

        // Money was captured, so the payment is recorded as completed —
        // but the room must not be double-booked, so A stays unconfirmed
        // for manual staff resolution.
        $this->assertEquals(Payment::STATUS_COMPLETED, $paymentA->status);
        $this->assertEquals(Booking::STATUS_PENDING, $bookingA->status);
    }

    public function test_failed_callback_marks_payment_failed_and_keeps_booking_pending(): void
    {
        $booking = $this->pendingBooking();
        $this->post(route('booking.pay.initiate', $booking->confirmation_code));
        $payment = Payment::first();

        // The failure redirect is verified with eSewa like any other return.
        $this->fakeGatewayStatus($payment, status: 'CANCELED');

        $response = $this->get(route('payment.esewa.failure', [
            'data' => $this->returnPayload($payment, status: 'CANCELED'),
        ]));

        $payment->refresh();
        $booking->refresh();

        $this->assertEquals(Payment::STATUS_CANCELLED, $payment->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->status);
        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));
    }
}
