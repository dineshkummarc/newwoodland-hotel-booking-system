<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmationMail;
use App\Mail\PaymentNeedsReviewMail;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use App\Services\Payments\PaymentReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * B2 — payment correctness never depends on the customer's browser coming
 * back. All gateway traffic is faked; nothing leaves the machine.
 */
class PaymentReconciliationTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    /** What the fake eSewa status API answers: a status string, 'timeout' or 'error500'. */
    protected string $esewaAnswer = 'NOT_FOUND';

    protected int $esewaCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->delete();
        Setting::factory()->create(['site_email' => 'frontdesk@example.com']);

        Http::fake([
            '*transaction/status*' => function ($request) {
                $this->esewaCalls++;

                if ($this->esewaAnswer === 'timeout') {
                    throw new ConnectionException('cURL error 28: Operation timed out');
                }

                if ($this->esewaAnswer === 'error500') {
                    return Http::response('<html>Bad gateway</html>', 502);
                }

                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

                return Http::response([
                    'product_code' => config('esewa.product_code'),
                    'transaction_uuid' => $q['transaction_uuid'] ?? null,
                    'total_amount' => $q['total_amount'] ?? null,
                    'status' => $this->esewaAnswer,
                    'ref_id' => 'REF-'.$this->esewaCalls,
                ], $this->esewaAnswer === 'NOT_FOUND' ? 404 : 200);
            },
        ]);
    }

    protected function pendingBooking(array $overrides = []): Booking
    {
        $room = Room::factory()->create(['price_per_night' => 1500, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);

        return Booking::factory()->for($room)->create(array_merge([
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(13)->toDateString(),
            'nightly_rate' => 1500,
            'total_price' => 4500,
            'nights' => 3,
            'email' => 'guest@example.com',
            'status' => Booking::STATUS_PENDING,
        ], $overrides));
    }

    /** The guest starts an eSewa payment (and then may never come back). */
    protected function startEsewa(Booking $booking): Payment
    {
        $this->post(route('booking.pay.initiate', $booking->confirmation_code))->assertOk();

        return $booking->payments()->where('provider', 'esewa')->latest('id')->firstOrFail();
    }

    protected function returnData(Payment $payment): string
    {
        return base64_encode(json_encode(['transaction_uuid' => $payment->transaction_uuid, 'status' => 'COMPLETE']));
    }

    // --- customer never returns -------------------------------------------

    public function test_a_payment_completed_by_a_customer_who_closed_the_browser_is_still_confirmed(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $payment = $this->startEsewa($booking);

        // The guest pays at eSewa and closes the tab — no callback ever arrives.
        $this->esewaAnswer = 'COMPLETE';
        $this->travel(3)->minutes();
        Artisan::call('payments:reconcile');

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }

    public function test_the_expiry_job_confirms_instead_of_cancelling_when_the_gateway_says_paid(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $payment = $this->startEsewa($booking);

        $this->esewaAnswer = 'COMPLETE';
        $this->travel(40)->minutes();
        Artisan::call('bookings:expire-abandoned');

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }

    // --- gateway trouble ---------------------------------------------------

    public function test_a_gateway_timeout_on_return_decides_nothing_and_is_retried_later(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $payment = $this->startEsewa($booking);

        $this->esewaAnswer = 'timeout';
        $this->get(route('payment.esewa.success', ['data' => $this->returnData($payment)]))
            ->assertRedirect(route('booking.confirmation', $booking->confirmation_code))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'do not pay again'));

        $this->assertSame(Payment::STATUS_PENDING, $payment->refresh()->status);
        $this->assertSame(1, $payment->check_attempts);
        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);

        // eSewa is back: the scheduled run settles it.
        $this->esewaAnswer = 'COMPLETE';
        $this->travel(5)->minutes();
        Artisan::call('payments:reconcile');

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    public function test_a_gateway_server_error_is_not_treated_as_a_failed_payment(): void
    {
        $booking = $this->pendingBooking();
        $payment = $this->startEsewa($booking);

        $this->esewaAnswer = 'error500';
        $this->travel(3)->minutes();
        Artisan::call('payments:reconcile');

        $this->assertSame(Payment::STATUS_PENDING, $payment->refresh()->status);
    }

    public function test_the_booking_is_kept_while_the_gateway_cannot_be_asked_or_reports_pending(): void
    {
        $booking = $this->pendingBooking();
        $this->startEsewa($booking);
        $this->travel(40)->minutes();

        $this->esewaAnswer = 'timeout';
        Artisan::call('bookings:expire-abandoned');
        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);

        $this->esewaAnswer = 'PENDING';
        Artisan::call('bookings:expire-abandoned');
        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);

        $this->esewaAnswer = 'AMBIGUOUS';
        Artisan::call('bookings:expire-abandoned');
        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_an_attempt_that_stays_unresolved_too_long_is_flagged_for_staff_and_the_booking_released(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $payment = $this->startEsewa($booking);

        $this->esewaAnswer = 'timeout';
        $this->travel(25)->hours();
        Artisan::call('bookings:expire-abandoned');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->refresh()->status);
        $this->assertTrue($payment->refresh()->needsReview());
        Mail::assertQueued(PaymentNeedsReviewMail::class, fn ($mail) => $mail->hasTo('frontdesk@example.com'));
    }

    public function test_the_booking_is_not_expired_while_the_guest_may_still_be_on_the_gateway_page(): void
    {
        $booking = $this->pendingBooking();
        $booking->forceFill(['created_at' => now()->subMinutes(35)])->save();
        $this->startEsewa($booking); // attempt started just now

        Artisan::call('bookings:expire-abandoned');

        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    // --- definitive answers -------------------------------------------------

    public function test_an_attempt_esewa_never_saw_is_released_then_closed_as_failed(): void
    {
        $booking = $this->pendingBooking();
        $payment = $this->startEsewa($booking);
        $this->esewaAnswer = 'NOT_FOUND';

        $this->travel(40)->minutes();
        Artisan::call('bookings:expire-abandoned');
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->refresh()->status);
        // Still pending: the guest could in theory still be on eSewa's page.
        $this->assertSame(Payment::STATUS_PENDING, $payment->refresh()->status);

        $this->travel(3)->hours();
        Artisan::call('payments:reconcile');
        $this->assertSame(Payment::STATUS_FAILED, $payment->refresh()->status);
    }

    public function test_a_cancellation_reported_by_esewa_closes_the_attempt(): void
    {
        $booking = $this->pendingBooking();
        $payment = $this->startEsewa($booking);

        $this->esewaAnswer = 'CANCELED';
        $this->get(route('payment.esewa.failure', ['data' => $this->returnData($payment)]));

        $this->assertSame(Payment::STATUS_CANCELLED, $payment->refresh()->status);
        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_the_failure_url_cannot_cancel_a_payment_esewa_completed(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $payment = $this->startEsewa($booking);

        // A (forged or confused) failure redirect, but eSewa took the money.
        $this->esewaAnswer = 'COMPLETE';
        $this->get(route('payment.esewa.failure', ['data' => $this->returnData($payment)]));

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    // --- idempotency ------------------------------------------------------------

    public function test_repeated_reconciliation_and_duplicate_callbacks_process_a_payment_once(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $payment = $this->startEsewa($booking);
        $this->esewaAnswer = 'COMPLETE';

        $this->get(route('payment.esewa.success', ['data' => $this->returnData($payment)]));
        $this->get(route('payment.esewa.success', ['data' => $this->returnData($payment)]));
        $this->travel(3)->minutes();
        Artisan::call('payments:reconcile');
        Artisan::call('payments:reconcile');

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertSame(1, Payment::where('status', Payment::STATUS_COMPLETED)->count());
        Mail::assertQueued(BookingConfirmationMail::class, 1);
        // Once completed, the gateway is not asked again.
        $this->assertSame(1, $this->esewaCalls);
    }

    public function test_two_simultaneous_reconciliations_confirm_and_email_exactly_once(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->startEsewa($booking);
        $this->esewaAnswer = 'COMPLETE';

        // Two workers loaded the same still-pending row before either finished.
        $first = Payment::firstOrFail();
        $second = Payment::firstOrFail();

        $resultA = app(PaymentReconciler::class)->reconcile($first);
        $resultB = app(PaymentReconciler::class)->reconcile($second);

        $this->assertTrue($resultA->justCompleted);
        $this->assertFalse($resultB->justCompleted);
        $this->assertTrue($resultB->isCompleted());
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }

    // --- late payment ---------------------------------------------------------

    public function test_a_payment_completed_after_the_booking_expired_is_not_confirmed_but_flagged(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $payment = $this->startEsewa($booking);

        // Guest wandered off; the hold expired and the booking was released.
        $this->esewaAnswer = 'NOT_FOUND';
        $this->travel(40)->minutes();
        Artisan::call('bookings:expire-abandoned');
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->refresh()->status);

        // ...then they paid anyway.
        $this->esewaAnswer = 'COMPLETE';
        $this->get(route('payment.esewa.success', ['data' => $this->returnData($payment)]))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'staff have been alerted'));

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->refresh()->status);
        $this->assertTrue($payment->needsReview());
        Mail::assertNotQueued(BookingConfirmationMail::class);
        Mail::assertQueued(PaymentNeedsReviewMail::class, 1);
        $this->assertTrue(AuditLog::where('action', 'payment.review_flagged')->where('target_id', $booking->id)->exists());
    }

    // --- duplicate attempts -----------------------------------------------------

    public function test_a_new_attempt_is_refused_while_an_earlier_one_may_have_money_in_flight(): void
    {
        $booking = $this->pendingBooking();
        $this->startEsewa($booking);

        $this->esewaAnswer = 'PENDING';
        $this->post(route('booking.pay.initiate', $booking->confirmation_code))
            ->assertRedirect(route('booking.confirmation', $booking->confirmation_code))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'do not pay again'));

        $this->assertSame(1, Payment::count());
    }

    public function test_a_new_attempt_is_allowed_when_the_earlier_one_was_never_paid(): void
    {
        $booking = $this->pendingBooking();
        $this->startEsewa($booking);

        $this->esewaAnswer = 'NOT_FOUND';
        $this->post(route('booking.pay.initiate', $booking->confirmation_code))->assertOk();

        $this->assertSame(2, Payment::where('provider', 'esewa')->count());
    }

    public function test_an_earlier_attempt_found_paid_confirms_instead_of_charging_again(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->startEsewa($booking);

        $this->esewaAnswer = 'COMPLETE';
        $this->post(route('booking.pay.initiate', $booking->confirmation_code))
            ->assertRedirect(route('booking.confirmation', $booking->confirmation_code));

        $this->assertSame(1, Payment::count());
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    public function test_paying_twice_in_two_tabs_is_flagged_for_a_refund(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->esewaAnswer = 'NOT_FOUND';
        $first = $this->startEsewa($booking);
        $second = $this->startEsewa($booking);

        $this->esewaAnswer = 'COMPLETE';
        $this->travel(3)->minutes();
        Artisan::call('payments:reconcile');

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
        $this->assertSame(2, Payment::where('status', Payment::STATUS_COMPLETED)->count());
        $this->assertTrue($second->refresh()->needsReview() || $first->refresh()->needsReview());
        Mail::assertQueued(PaymentNeedsReviewMail::class);
    }

    public function test_every_status_change_is_audited(): void
    {
        $booking = $this->pendingBooking();
        $payment = $this->startEsewa($booking);

        $this->esewaAnswer = 'COMPLETE';
        $this->travel(3)->minutes();
        Artisan::call('payments:reconcile');

        $this->assertTrue(AuditLog::where('action', 'payment.status_changed')
            ->where('target_id', $booking->id)
            ->where('description', 'like', '%'.$payment->transaction_uuid.'%pending → completed%')
            ->exists());
    }

    public function test_reconciliation_never_runs_for_a_misconfigured_production_site(): void
    {
        $booking = $this->pendingBooking();
        $payment = $this->startEsewa($booking);

        // The live site was deployed with the sandbox configuration.
        $this->app['env'] = 'production';
        $this->esewaAnswer = 'COMPLETE';
        $this->travel(3)->minutes();
        Artisan::call('payments:reconcile');

        $this->assertSame(Payment::STATUS_PENDING, $payment->refresh()->status);
        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);
        $this->assertSame(0, $this->esewaCalls);
    }
}
