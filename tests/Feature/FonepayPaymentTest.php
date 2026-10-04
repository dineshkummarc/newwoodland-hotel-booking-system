<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmationMail;
use App\Mail\NewBookingNotificationMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * FonepayPaymentService is implemented against Fonepay's published web-payment
 * protocol (github.com/fonepay/Fonepay-Java). These tests use Http::fake() to
 * simulate Fonepay's verification endpoint — the same technique the eSewa and
 * Khalti tests use — so no real network call is ever made. They prove the
 * *code* follows the documented protocol; they are not a substitute for a real
 * sandbox transaction, which needs dev merchant credentials issued by Fonepay.
 * The merchant code and secret below are obvious placeholders, never real.
 */
class FonepayPaymentTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    private const MERCHANT = 'TESTMERCHANT';

    private const SECRET = 'fonepay_unit_test_secret_not_real';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.methods.fonepay.enabled' => true,
            'fonepay.mode' => 'sandbox',
            'fonepay.merchant_code' => self::MERCHANT,
            'fonepay.secret_key' => self::SECRET,
            'fonepay.currency' => 'NPR',
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

    /** Independent re-implementation of Fonepay's DV, so the test isn't just echoing the service. */
    protected function dv(array $values): string
    {
        return strtoupper(hash_hmac('sha512', implode(',', $values), self::SECRET));
    }

    protected function verificationXml(array $fields): string
    {
        $inner = '';
        foreach ($fields as $name => $value) {
            $inner .= "<{$name}>{$value}</{$name}>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><response>'.$inner.'</response>';
    }

    protected function fakeVerification(string $uid, string $responseCode = 'successful', string $txnAmount = '4500.00'): void
    {
        Http::fake([
            '*verificationMerchant*' => Http::response($this->verificationXml([
                'amount' => '4500.00',
                'bankCode' => 'TESTBANK',
                'initiator' => '9800000000',
                'message' => $responseCode === 'successful' ? 'Payment Successful' : 'Payment Failed',
                'response_code' => $responseCode,
                'statusCode' => $responseCode === 'successful' ? '1' : '0',
                'success' => $responseCode === 'successful' ? 'true' : 'false',
                'txnAmount' => $txnAmount,
                'uniqueId' => $uid,
            ]), 200, ['Content-Type' => 'application/xml']),
        ]);
    }

    /** Start a payment the way a customer would and return the Fonepay redirect's query fields. */
    protected function initiate(Booking $booking): array
    {
        $response = $this->post(route('booking.pay.fonepay.initiate', $booking->confirmation_code));
        $response->assertRedirect();

        return $this->queryOf($response->headers->get('Location'));
    }

    protected function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    protected function returnParams(Payment $payment, array $overrides = []): array
    {
        return array_merge([
            'PRN' => $payment->transaction_uuid,
            'PID' => self::MERCHANT,
            'PS' => 'true',
            'RC' => 'successful',
            'UID' => '1234567',
            'BC' => 'TESTBANK',
            'INI' => '9800000000',
            'P_AMT' => '4500.00',
            'R_AMT' => '4500.00',
        ], $overrides);
    }

    // --- initiation -------------------------------------------------------

    public function test_initiating_redirects_to_fonepay_with_a_correctly_signed_request(): void
    {
        $booking = $this->pendingBooking(['total_price' => 4500]);

        $response = $this->post(route('booking.pay.fonepay.initiate', $booking->confirmation_code));

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://dev-clientapi.fonepay.com/api/merchantRequest?', $location);

        $q = $this->queryOf($location);
        $payment = Payment::first();

        $this->assertSame(self::MERCHANT, $q['PID']);
        $this->assertSame('P', $q['MD']);
        $this->assertSame('4500.00', $q['AMT']);
        $this->assertSame('NPR', $q['CRN']);
        $this->assertSame($payment->transaction_uuid, $q['PRN']);
        $this->assertSame(route('payment.fonepay.return'), $q['RU']);
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4}$#', $q['DT']);
        $this->assertGreaterThanOrEqual(3, strlen($q['PRN']));
        $this->assertLessThanOrEqual(25, strlen($q['PRN']));

        $this->assertSame(
            $this->dv([$q['PID'], $q['MD'], $q['PRN'], $q['AMT'], $q['CRN'], $q['DT'], $q['R1'], $q['R2'], $q['RU']]),
            $q['DV'],
        );

        $this->assertEquals('fonepay', $payment->provider);
        $this->assertEquals($booking->id, $payment->booking_id);
        $this->assertEquals(4500, (float) $payment->amount);
        $this->assertEquals(Payment::STATUS_PENDING, $payment->status);
    }

    public function test_the_secret_key_never_appears_in_the_redirect_url(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->post(route('booking.pay.fonepay.initiate', $booking->confirmation_code));

        $this->assertStringNotContainsString(self::SECRET, urldecode($response->headers->get('Location')));
    }

    public function test_amount_sent_to_fonepay_cannot_be_manipulated_by_the_client(): void
    {
        $booking = $this->pendingBooking(['total_price' => 4500]);

        $response = $this->post(route('booking.pay.fonepay.initiate', $booking->confirmation_code), ['amount' => 1, 'AMT' => 1]);

        $this->assertSame('4500.00', $this->queryOf($response->headers->get('Location'))['AMT']);
    }

    public function test_unknown_booking_cannot_be_paid(): void
    {
        // Unknown codes get the same access page as someone else's booking.
        $this->post(route('booking.pay.fonepay.initiate', 'NW-2026-DOESNT'))->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cancelled_booking_cannot_be_paid_via_fonepay(): void
    {
        $booking = $this->pendingBooking(['status' => Booking::STATUS_CANCELLED]);

        $this->post(route('booking.pay.fonepay.initiate', $booking->confirmation_code))->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_already_paid_booking_cannot_be_paid_again_via_fonepay(): void
    {
        $booking = $this->pendingBooking(['status' => Booking::STATUS_CONFIRMED]);
        Payment::factory()->for($booking)->completed()->create(['provider' => 'fonepay', 'amount' => $booking->total_price]);

        $response = $this->post(route('booking.pay.fonepay.initiate', $booking->confirmation_code));

        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_fonepay_route_is_unreachable_when_not_enabled(): void
    {
        config(['payments.methods.fonepay.enabled' => false]);
        $booking = $this->pendingBooking();

        $this->post(route('booking.pay.fonepay.initiate', $booking->confirmation_code))->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_fonepay_initiation_without_credentials_fails_safely(): void
    {
        config(['fonepay.secret_key' => null]);
        $booking = $this->pendingBooking();

        $this->post(route('booking.pay.fonepay.initiate', $booking->confirmation_code))->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_checkout_page_offers_fonepay_once_it_is_enabled(): void
    {
        $booking = $this->pendingBooking();

        $response = $this->get(route('booking.pay', $booking->confirmation_code));

        $response->assertOk();
        $response->assertSee('name="payment_method" value="fonepay"', false);
        $response->assertDontSee(self::SECRET, false);
    }

    public function test_checkout_does_not_offer_fonepay_or_khalti_as_available_without_credentials(): void
    {
        config([
            'payments.methods.khalti.enabled' => true,
            'khalti.secret_key' => null,
            'fonepay.secret_key' => null,
        ]);
        $booking = $this->pendingBooking();

        $response = $this->get(route('booking.pay', $booking->confirmation_code));

        // Flag on but no credentials: shown as unavailable, with no dead payment form.
        $response->assertDontSee('name="payment_method" value="fonepay"', false);
        $response->assertDontSee('name="payment_method" value="khalti"', false);
    }

    public function test_failed_payment_can_be_retried_with_a_new_payment_record(): void
    {
        $booking = $this->pendingBooking();
        Payment::factory()->for($booking)->failed()->create(['provider' => 'fonepay', 'amount' => $booking->total_price]);

        $this->post(route('booking.pay.fonepay.initiate', $booking->confirmation_code))->assertRedirect();

        $this->assertDatabaseCount('payments', 2);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    // --- verified success -------------------------------------------------

    public function test_successful_verified_payment_confirms_the_booking_and_sends_notifications(): void
    {
        Mail::fake();
        Setting::factory()->create(['site_email' => 'admin@newwoodlandhotel.com']);
        $booking = $this->pendingBooking(['total_price' => 4500]);
        $this->initiate($booking);
        $payment = Payment::first();

        $this->fakeVerification('1234567');
        $response = $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $payment->refresh();
        $booking->refresh();

        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->status);
        $this->assertEquals('1234567', $payment->reference_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->status);
        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));

        Mail::assertQueued(BookingConfirmationMail::class);
        Mail::assertQueued(NewBookingNotificationMail::class);
    }

    public function test_verification_request_uses_the_server_side_amount_and_a_valid_signature(): void
    {
        $booking = $this->pendingBooking(['total_price' => 4500]);
        $this->initiate($booking);
        $payment = Payment::first();
        $this->fakeVerification('1234567');

        // The browser claims a different amount; it must be ignored.
        $this->get(route('payment.fonepay.return', $this->returnParams($payment, ['P_AMT' => '1.00', 'R_AMT' => '1.00', 'AMT' => '1'])));

        Http::assertSent(function (Request $request) use ($payment) {
            $q = $this->queryOf($request->url());

            return str_contains($request->url(), 'https://dev-clientapi.fonepay.com/api/merchantRequest/verificationMerchant')
                && $request->method() === 'GET'
                && $q['PRN'] === $payment->transaction_uuid
                && $q['PID'] === self::MERCHANT
                && $q['AMT'] === '4500.00'
                && $q['UID'] === '1234567'
                && $q['BID'] === 'TESTBANK'
                && $q['DV'] === $this->dv([self::MERCHANT, '4500.00', $payment->transaction_uuid, 'TESTBANK', '1234567']);
        });
    }

    public function test_the_bid_return_parameter_is_accepted_as_the_bill_id(): void
    {
        $booking = $this->pendingBooking();
        $this->initiate($booking);
        $payment = Payment::first();
        $this->fakeVerification('1234567');

        $params = $this->returnParams($payment);
        unset($params['BC']);
        $params['BID'] = 'BILL-77';
        $this->get(route('payment.fonepay.return', $params));

        Http::assertSent(fn (Request $request) => $this->queryOf($request->url())['BID'] === 'BILL-77');
        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->refresh()->status);
    }

    // --- failed / tampered / invalid -------------------------------------

    public function test_a_browser_claiming_success_is_not_believed_when_fonepay_says_otherwise(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->initiate($booking);
        $payment = Payment::first();

        // Return URL says PS=true / RC=successful; Fonepay's own API disagrees.
        $this->fakeVerification('1234567', 'failed', '0');
        $response = $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertEquals(Payment::STATUS_FAILED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));
        Mail::assertNothingOutgoing();
    }

    public function test_an_amount_paid_below_the_booking_total_is_rejected(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking(['total_price' => 4500]);
        $this->initiate($booking);
        $payment = Payment::first();

        $this->fakeVerification('1234567', 'successful', '100.00');
        $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertEquals(Payment::STATUS_FAILED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_a_verification_response_for_a_different_trace_id_is_rejected(): void
    {
        $booking = $this->pendingBooking();
        $this->initiate($booking);
        $payment = Payment::first();

        $this->fakeVerification('9999999');
        $this->get(route('payment.fonepay.return', $this->returnParams($payment, ['UID' => '1234567'])));

        $this->assertEquals(Payment::STATUS_FAILED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_a_trace_id_already_settled_on_another_payment_cannot_settle_a_second_one(): void
    {
        $bookingA = $this->pendingBooking(['status' => Booking::STATUS_CONFIRMED]);
        Payment::factory()->for($bookingA)->completed()->create(['provider' => 'fonepay', 'reference_id' => '1234567', 'amount' => 4500]);

        $bookingB = $this->pendingBooking();
        $paymentB = Payment::factory()->for($bookingB)->create(['provider' => 'fonepay', 'amount' => 4500, 'transaction_uuid' => 'REPLAYPRN0000001']);

        $this->fakeVerification('1234567');
        $this->get(route('payment.fonepay.return', $this->returnParams($paymentB, ['UID' => '1234567'])));

        $this->assertEquals(Payment::STATUS_FAILED, $paymentB->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $bookingB->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_unknown_prn_is_handled_gracefully(): void
    {
        $response = $this->get(route('payment.fonepay.return', ['PRN' => 'DOESNOTEXIST', 'UID' => '1', 'BC' => 'X', 'PS' => 'true']));

        $response->assertRedirect(route('home'));
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_missing_prn_is_handled_gracefully(): void
    {
        $this->get(route('payment.fonepay.return'))->assertRedirect(route('home'));
        Http::assertNothingSent();
    }

    public function test_a_prn_belonging_to_another_gateway_is_not_accepted(): void
    {
        $booking = $this->pendingBooking();
        $esewa = Payment::factory()->for($booking)->create(['provider' => 'esewa', 'transaction_uuid' => 'ESEWA-TXN-00001']);

        $this->get(route('payment.fonepay.return', $this->returnParams($esewa)))->assertRedirect(route('home'));

        $this->assertEquals(Payment::STATUS_PENDING, $esewa->refresh()->status);
        Http::assertNothingSent();
    }

    // --- cancellation -----------------------------------------------------

    public function test_cancelling_at_fonepay_marks_the_payment_cancelled_and_never_confirms_the_booking(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->initiate($booking);
        $payment = Payment::first();
        Http::fake();

        $response = $this->get(route('payment.fonepay.return', ['PRN' => $payment->transaction_uuid, 'PS' => 'false', 'RC' => 'cancel']));

        $this->assertEquals(Payment::STATUS_CANCELLED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));
        $response->assertSessionHas('error');
        Mail::assertNothingOutgoing();
        Http::assertNothingSent(); // nothing to verify — no Fonepay transaction exists
    }

    public function test_a_cancelled_payment_can_still_be_settled_if_fonepay_later_confirms_it(): void
    {
        $booking = $this->pendingBooking();
        $this->initiate($booking);
        $payment = Payment::first();
        $payment->update(['status' => Payment::STATUS_CANCELLED]);

        $this->fakeVerification('1234567');
        $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    public function test_a_forged_cancel_cannot_undo_a_completed_payment(): void
    {
        $booking = $this->pendingBooking(['status' => Booking::STATUS_CONFIRMED]);
        $payment = Payment::factory()->for($booking)->completed()->create([
            'provider' => 'fonepay', 'amount' => 4500, 'transaction_uuid' => 'DONEPRN000000001',
        ]);

        $this->get(route('payment.fonepay.return', ['PRN' => 'DONEPRN000000001', 'PS' => 'false', 'RC' => 'cancel']));

        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    // --- duplicate callbacks ---------------------------------------------

    public function test_duplicate_return_callback_is_processed_only_once(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->initiate($booking);
        $payment = Payment::first();

        $this->fakeVerification('1234567');
        $params = $this->returnParams($payment);
        $this->get(route('payment.fonepay.return', $params));
        $this->get(route('payment.fonepay.return', $params)); // refresh / duplicate

        $this->assertDatabaseCount('payments', 1);
        $this->assertEquals(1, Payment::where('status', Payment::STATUS_COMPLETED)->count());
        Http::assertSentCount(1); // the replay is never re-verified
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }

    // --- gateway/API failure ---------------------------------------------

    public function test_verification_server_error_leaves_the_payment_undecided(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->initiate($booking);
        $payment = Payment::first();

        Http::fake(['*verificationMerchant*' => Http::response('Server Error', 500)]);
        $response = $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertEquals(Payment::STATUS_PENDING, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
        $response->assertRedirect(route('booking.confirmation', $booking->confirmation_code));
        $response->assertSessionHas('error');
        Mail::assertNothingOutgoing();
    }

    public function test_verification_connection_failure_leaves_the_payment_undecided_and_is_recoverable(): void
    {
        $booking = $this->pendingBooking();
        $this->initiate($booking);
        $payment = Payment::first();

        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->get(route('payment.fonepay.return', $this->returnParams($payment)))->assertRedirect();

        $this->assertEquals(Payment::STATUS_PENDING, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);

        // Retrying the same return URL once Fonepay is reachable settles it.
        Http::swap(new HttpFactory); // a second Http::fake() would only append stubs behind the failing one
        $this->fakeVerification('1234567');
        $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
    }

    public function test_unreadable_verification_response_leaves_the_payment_undecided(): void
    {
        $booking = $this->pendingBooking();
        $this->initiate($booking);
        $payment = Payment::first();

        Http::fake(['*verificationMerchant*' => Http::response('<html>not the expected xml', 200)]);
        $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertEquals(Payment::STATUS_PENDING, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    // --- JSON verification replies (observed live on Fonepay's dev server) ----

    protected function startedPayment(): array
    {
        $booking = $this->pendingBooking(['total_price' => 4500]);
        $this->initiate($booking);

        return [$booking, Payment::first()];
    }

    public function test_a_live_style_json_not_found_reply_is_a_definitive_failure_and_never_confirms(): void
    {
        Mail::fake();
        [$booking, $payment] = $this->startedPayment();

        // Exactly what dev-clientapi.fonepay.com returned for a non-existent transaction.
        Http::fake(['*verificationMerchant*' => Http::response(['message' => 'No data found for verification', 'isSuccess' => false], 409)]);
        $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertEquals(Payment::STATUS_FAILED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
        Mail::assertNothingOutgoing();
    }

    public function test_a_json_reply_with_issuccess_true_confirms_the_booking(): void
    {
        Mail::fake();
        Setting::factory()->create();
        [$booking, $payment] = $this->startedPayment();

        // NOTE: the *success* JSON shape has not been seen from the real
        // server yet; this pins the conservative rule (explicit isSuccess:true).
        Http::fake(['*verificationMerchant*' => Http::response(['message' => 'Success', 'isSuccess' => true], 200)]);
        $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertEquals(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }

    public function test_a_json_reply_without_an_explicit_success_never_confirms(): void
    {
        [$booking, $payment] = $this->startedPayment();

        Http::fake(['*verificationMerchant*' => Http::response(['message' => 'Processed'], 200)]);
        $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertNotEquals(Payment::STATUS_COMPLETED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_a_json_success_flag_contradicted_by_the_response_code_is_rejected(): void
    {
        [$booking, $payment] = $this->startedPayment();

        Http::fake(['*verificationMerchant*' => Http::response(['isSuccess' => true, 'data' => ['response_code' => 'failed']], 200)]);
        $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertEquals(Payment::STATUS_FAILED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_a_json_reply_reporting_less_than_the_booking_total_is_rejected(): void
    {
        [$booking, $payment] = $this->startedPayment();

        Http::fake(['*verificationMerchant*' => Http::response(['isSuccess' => true, 'data' => ['txnAmount' => '100.00']], 200)]);
        $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertEquals(Payment::STATUS_FAILED, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_a_json_server_error_without_a_definitive_answer_leaves_the_payment_undecided(): void
    {
        [$booking, $payment] = $this->startedPayment();

        Http::fake(['*verificationMerchant*' => Http::response(['message' => 'Internal error'], 500)]);
        $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertEquals(Payment::STATUS_PENDING, $payment->refresh()->status);
        $this->assertEquals(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    // --- housekeeping ------------------------------------------------------

    public function test_the_return_route_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get(route('payment.fonepay.return'))->assertStatus(302);
        }

        $this->get(route('payment.fonepay.return'))->assertStatus(429);
    }

    public function test_the_secret_key_is_never_stored_in_the_payment_record(): void
    {
        $booking = $this->pendingBooking();
        $this->initiate($booking);
        $payment = Payment::first();
        $this->fakeVerification('1234567');
        $this->get(route('payment.fonepay.return', $this->returnParams($payment)));

        $this->assertStringNotContainsString(self::SECRET, json_encode($payment->refresh()->getAttributes()));
    }
}
