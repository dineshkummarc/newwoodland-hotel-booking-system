<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmationMail;
use App\Mail\PaymentNeedsReviewMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * B2 for Fonepay. Fonepay's published protocol can only verify a payment
 * with the trace id (UID) / bill id (BID) handed to the customer's browser
 * on return, so: trace ids are stored before verifying (a timed-out check
 * is retried later), and an attempt whose customer never returned is
 * flagged for staff (merchant portal) when its booking is released.
 */
class FonepayReconciliationTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    protected string $answer = 'successful';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.methods.fonepay.enabled' => true,
            'fonepay.mode' => 'sandbox',
            'fonepay.merchant_code' => 'TESTMERCHANT',
            'fonepay.secret_key' => 'fonepay_unit_test_secret_not_real',
        ]);
        Setting::query()->delete();
        Setting::factory()->create(['site_email' => 'frontdesk@example.com']);

        Http::fake([
            '*verificationMerchant*' => function () {
                if ($this->answer === 'timeout') {
                    throw new ConnectionException('timed out');
                }

                return Http::response('<?xml version="1.0"?><response><response_code>'.$this->answer.'</response_code><success>'
                    .($this->answer === 'successful' ? 'true' : 'false').'</success><txnAmount>4500.00</txnAmount><uniqueId>7654321</uniqueId></response>', 200);
            },
        ]);
    }

    protected function startFonepay(): array
    {
        $room = Room::factory()->create(['price_per_night' => 1500, 'availability_status' => Room::AVAILABILITY_AVAILABLE]);
        $booking = Booking::factory()->for($room)->create([
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(13)->toDateString(),
            'total_price' => 4500, 'nightly_rate' => 1500, 'nights' => 3,
            'status' => Booking::STATUS_PENDING,
        ]);
        $this->post(route('booking.pay.fonepay.initiate', $booking->confirmation_code))->assertRedirect();

        return [$booking, Payment::where('provider', 'fonepay')->latest('id')->firstOrFail()];
    }

    protected function back(Payment $payment): TestResponse
    {
        return $this->get(route('payment.fonepay.return', [
            'PRN' => $payment->transaction_uuid, 'PID' => 'TESTMERCHANT', 'PS' => 'true', 'RC' => 'successful',
            'UID' => '7654321', 'BID' => 'BILL-1', 'P_AMT' => '4500.00', 'R_AMT' => '4500.00',
        ]));
    }

    public function test_a_verification_timeout_keeps_the_trace_ids_and_is_settled_later(): void
    {
        Mail::fake();
        [$booking, $payment] = $this->startFonepay();

        $this->answer = 'timeout';
        $this->back($payment)->assertSessionHas('error', fn ($m) => str_contains($m, 'do not pay again'));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame('7654321', $payment->gateway_response['trace']['uid']);

        $this->answer = 'successful';
        $this->travel(5)->minutes();
        Artisan::call('payments:reconcile');

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }

    public function test_an_attempt_whose_customer_never_returned_is_flagged_when_the_booking_is_released(): void
    {
        Mail::fake();
        [$booking, $payment] = $this->startFonepay();

        $this->travel(40)->minutes();
        Artisan::call('bookings:expire-abandoned');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->refresh()->status);
        $payment->refresh();
        $this->assertTrue($payment->needsReview());
        $this->assertStringContainsString($payment->transaction_uuid, $payment->review_reason);
        Mail::assertQueued(PaymentNeedsReviewMail::class, 1);
        Http::assertNothingSent(); // nothing Fonepay could be asked
    }

    public function test_a_late_fonepay_return_after_release_is_recorded_but_not_auto_confirmed(): void
    {
        Mail::fake();
        [$booking, $payment] = $this->startFonepay();
        $this->travel(40)->minutes();
        Artisan::call('bookings:expire-abandoned');

        $this->answer = 'successful';
        $this->back($payment);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status);
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->refresh()->status);
        $this->assertTrue($payment->needsReview());
        // The open flag's reason is updated to the new situation.
        $this->assertStringContainsString('after the booking was cancelled', $payment->review_reason);
        Mail::assertNotQueued(BookingConfirmationMail::class);
    }

    public function test_a_duplicate_fonepay_return_settles_once(): void
    {
        Mail::fake();
        [$booking, $payment] = $this->startFonepay();

        $this->back($payment);
        $this->back($payment);

        $this->assertSame(1, Payment::where('status', Payment::STATUS_COMPLETED)->count());
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }
}
