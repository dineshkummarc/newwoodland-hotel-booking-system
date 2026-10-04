<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use App\Services\KhaltiPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsBookingGuest;
use Tests\TestCase;

/**
 * End-to-end Khalti checkout: the "Continue Payment" selection, ownership,
 * amount handling, reuse of an open Khalti session, and settling payments
 * whose customer never came back. Every Khalti call is faked.
 */
class KhaltiCheckoutFlowTest extends TestCase
{
    use ActsAsBookingGuest, RefreshDatabase;

    private const SECRET = 'test_secret_key_unit_test_only';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payments.methods.khalti.enabled' => true,
            'khalti.secret_key' => self::SECRET,
        ]);

        Setting::factory()->create();
    }

    private function pendingBooking(array $overrides = []): Booking
    {
        $room = Room::factory()->create(['price_per_night' => 1500]);

        return Booking::factory()->for($room)->create(array_merge([
            'nightly_rate' => 1500,
            'total_price' => 4500,
            'nights' => 3,
            'status' => Booking::STATUS_PENDING,
        ], $overrides));
    }

    private function fakeKhalti(string $pidx, ?array $lookup = null): void
    {
        Http::swap(new HttpFactory);
        Http::fake([
            '*epayment/initiate/*' => Http::response([
                'pidx' => $pidx,
                'payment_url' => "https://test-pay.khalti.com/?pidx={$pidx}",
                'expires_at' => now()->addHour()->toIso8601String(),
                'expires_in' => 3600,
            ]),
            '*epayment/lookup/*' => Http::response($lookup ?? ['pidx' => $pidx, 'status' => 'Initiated', 'total_amount' => 450000]),
        ]);
    }

    private function fakeLookup(array $body, int $status = 200): void
    {
        Http::swap(new HttpFactory);
        Http::fake(['*epayment/lookup/*' => Http::response($body, $status)]);
    }

    private function continueWith(Booking $booking, string $method, array $extra = [])
    {
        return $this->post(route('booking.pay.continue', $booking->confirmation_code), ['payment_method' => $method] + $extra);
    }

    // --- Checkout page -----------------------------------------------------

    public function test_khalti_is_offered_as_a_selectable_option_without_exposing_the_key(): void
    {
        $booking = $this->pendingBooking();

        $this->get(route('booking.pay', $booking->confirmation_code))
            ->assertOk()
            ->assertSee('name="payment_method" value="khalti"', false)
            ->assertSee('Continue Payment')
            ->assertSee('NPR 4,500')
            ->assertDontSee(self::SECRET, false);
    }

    public function test_continue_with_khalti_redirects_to_khaltis_checkout_with_a_pending_payment(): void
    {
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-flow-1');

        $this->continueWith($booking, 'khalti')->assertRedirect('https://test-pay.khalti.com/?pidx=pidx-flow-1');

        $payment = Payment::sole();
        $this->assertSame('khalti', $payment->provider);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame('pidx-flow-1', $payment->reference_id);
        $this->assertSame('4500.00', $payment->amount);
        $this->assertSame('NPR', $payment->currency);
        $this->assertStringStartsWith($booking->confirmation_code.'-', $payment->transaction_uuid);
        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_amount_comes_from_the_booking_not_the_request(): void
    {
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-flow-2');

        $this->continueWith($booking, 'khalti', ['amount' => 100000000, 'total_price' => 1, 'booking_id' => 999]);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'epayment/initiate/')
            && $request['amount'] === 450000
            && $request['return_url'] === route('payment.khalti.return'));
        $this->assertSame('4500.00', Payment::sole()->amount);
    }

    public function test_esewa_still_works_through_continue_payment(): void
    {
        $booking = $this->pendingBooking();

        $this->continueWith($booking, 'esewa')
            ->assertOk()
            ->assertViewIs('payment-redirect')
            ->assertSee('esewa.com.np', false);

        $this->assertSame('esewa', Payment::sole()->provider);
    }

    public function test_an_unavailable_or_unknown_method_is_refused(): void
    {
        $booking = $this->pendingBooking();

        $this->continueWith($booking, 'fonepay')->assertSessionHasErrors('payment_method');
        $this->continueWith($booking, 'bitcoin')->assertSessionHasErrors('payment_method');
        $this->post(route('booking.pay.continue', $booking->confirmation_code))->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('payments', 0);
    }

    // --- Payability / ownership -------------------------------------------

    public function test_a_booking_that_is_no_longer_payable_is_refused(): void
    {
        $booking = $this->pendingBooking(['status' => Booking::STATUS_CHECKED_OUT]);
        Http::fake();

        $this->continueWith($booking, 'khalti')->assertRedirect(route('booking.confirmation', $booking->confirmation_code));

        Http::assertNothingSent();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_an_account_booking_can_only_be_paid_by_its_owner(): void
    {
        $owner = User::factory()->create();
        $booking = $this->pendingBooking(['user_id' => $owner->id]);
        $this->fakeKhalti('pidx-owner-1');

        // Signed out: the booking-access page (which offers sign-in) — the
        // code alone gets nothing, and nothing reveals the booking exists.
        $this->get(route('booking.pay', $booking->confirmation_code))->assertForbidden()->assertSee('sign in');
        $this->continueWith($booking, 'khalti')->assertForbidden();

        // Another customer: forbidden.
        $this->actingAs(User::factory()->create());
        $this->get(route('booking.pay', $booking->confirmation_code))->assertForbidden();
        $this->post(route('booking.pay.khalti.initiate', $booking->confirmation_code))->assertForbidden();
        $this->assertDatabaseCount('payments', 0);

        // The owner: allowed.
        $this->actingAs($owner);
        $this->continueWith($booking, 'khalti')->assertRedirect('https://test-pay.khalti.com/?pidx=pidx-owner-1');
    }

    public function test_guest_bookings_remain_payable_from_the_browser_that_made_them(): void
    {
        $booking = $this->pendingBooking(['user_id' => null]);

        $this->get(route('booking.pay', $booking->confirmation_code))->assertOk();
    }

    public function test_public_documentation_key_works_in_sandbox_but_is_refused_in_production(): void
    {
        config(['khalti.secret_key' => KhaltiPaymentService::PUBLIC_SANDBOX_KEY, 'khalti.mode' => 'sandbox']);
        $this->assertTrue(app(KhaltiPaymentService::class)->isAvailable());

        config(['khalti.mode' => 'production']);
        $this->assertFalse(app(KhaltiPaymentService::class)->isAvailable());

        $booking = $this->pendingBooking();
        Http::fake();
        $this->continueWith($booking, 'khalti')->assertSessionHasErrors('payment_method');
        Http::assertNothingSent();
    }

    // --- Amount conversion -------------------------------------------------

    public function test_paisa_conversion_is_exact(): void
    {
        $this->assertSame(450000, KhaltiPaymentService::toPaisa('4500.00'));
        $this->assertSame(450000, KhaltiPaymentService::toPaisa(4500));
        $this->assertSame(123456, KhaltiPaymentService::toPaisa('1234.56'));
        $this->assertSame(30, KhaltiPaymentService::toPaisa(0.1 + 0.2));
        $this->assertSame(1000, KhaltiPaymentService::toPaisa(10));
    }

    public function test_amount_below_khaltis_minimum_is_not_sent(): void
    {
        $booking = $this->pendingBooking(['total_price' => 9.99]);
        Http::fake();

        $this->continueWith($booking, 'khalti')
            ->assertRedirect(route('booking.pay', $booking->confirmation_code))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Rs. 10'));

        Http::assertNothingSent();
    }

    // --- Verification ------------------------------------------------------

    public function test_successful_payment_shows_the_khalti_receipt(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-ok-1');
        $this->continueWith($booking, 'khalti');

        $this->fakeLookup(['pidx' => 'pidx-ok-1', 'status' => 'Completed', 'total_amount' => 450000, 'transaction_id' => 'KHALTI-TXN-9', 'fee' => 0, 'refunded' => false]);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-ok-1', 'status' => 'Completed']))
            ->assertRedirect(route('booking.confirmation', $booking->confirmation_code));

        $payment = Payment::sole();
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('KHALTI-TXN-9', $payment->gatewayTransactionId());
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);

        $this->get(route('booking.confirmation', $booking->confirmation_code))
            ->assertSeeText('Paid via Khalti')
            ->assertSeeText('KHALTI-TXN-9')
            ->assertSeeText('NPR 4,500');
    }

    public function test_a_lookup_for_a_different_transaction_is_not_accepted(): void
    {
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-swap-1');
        $this->continueWith($booking, 'khalti');

        // Khalti answers about some other pidx (e.g. another booking's payment).
        $this->fakeLookup(['pidx' => 'pidx-someone-else', 'status' => 'Completed', 'total_amount' => 450000]);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-swap-1']));

        $this->assertSame(Payment::STATUS_PENDING, Payment::sole()->status);
        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_api_failure_leaves_the_payment_pending_and_does_not_invite_a_second_payment(): void
    {
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-down-1');
        $this->continueWith($booking, 'khalti');

        Http::swap(new HttpFactory);
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-down-1']))
            ->assertRedirect(route('booking.confirmation', $booking->confirmation_code))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'do not pay again'));

        $this->assertSame(Payment::STATUS_PENDING, Payment::sole()->status);
    }

    public function test_malformed_or_unauthorised_lookup_response_decides_nothing(): void
    {
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-bad-1');
        $this->continueWith($booking, 'khalti');

        $this->fakeLookup(['detail' => 'Invalid token.', 'status_code' => 401], 401);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-bad-1']));
        $this->assertSame(Payment::STATUS_PENDING, Payment::sole()->status);

        Http::swap(new HttpFactory);
        Http::fake(['*epayment/lookup/*' => Http::response('<html>Bad Gateway</html>', 502)]);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-bad-1']));
        $this->assertSame(Payment::STATUS_PENDING, Payment::sole()->status);
    }

    public function test_refunded_payment_is_failed(): void
    {
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-ref-1');
        $this->continueWith($booking, 'khalti');

        $this->fakeLookup(['pidx' => 'pidx-ref-1', 'status' => 'Refunded', 'total_amount' => 450000, 'refunded' => true]);
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-ref-1']));

        $this->assertSame(Payment::STATUS_FAILED, Payment::sole()->status);
        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    // --- No double payment ---------------------------------------------------

    public function test_retrying_while_the_first_khalti_session_is_still_open_reuses_it(): void
    {
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-open-1');
        $this->continueWith($booking, 'khalti');

        // Customer comes back without paying and presses Continue again.
        $this->fakeKhalti('pidx-open-2', ['pidx' => 'pidx-open-1', 'status' => 'Initiated', 'total_amount' => 450000]);
        $this->continueWith($booking, 'khalti')->assertRedirect('https://test-pay.khalti.com/?pidx=pidx-open-1');

        $this->assertDatabaseCount('payments', 1);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'epayment/initiate/'));
    }

    public function test_retrying_after_khalti_completed_the_first_attempt_confirms_instead_of_charging_again(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-paid-1');
        $this->continueWith($booking, 'khalti');

        // Paid at Khalti, but the customer never reached our return URL.
        $this->fakeKhalti('pidx-paid-2', ['pidx' => 'pidx-paid-1', 'status' => 'Completed', 'total_amount' => 450000, 'transaction_id' => 'T-1']);
        $this->continueWith($booking, 'khalti')->assertRedirect(route('booking.confirmation', $booking->confirmation_code));

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
        Mail::assertQueued(BookingConfirmationMail::class, 1);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'epayment/initiate/'));
    }

    public function test_retrying_while_the_sandbox_reports_the_unpaid_session_as_pending_reuses_it(): void
    {
        // Khalti's sandbox reports a freshly opened, unpaid session as "Pending".
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-sbx-1');
        $this->continueWith($booking, 'khalti');

        $this->fakeKhalti('pidx-sbx-2', ['pidx' => 'pidx-sbx-1', 'status' => 'Pending', 'total_amount' => 450000]);
        $this->continueWith($booking, 'khalti')->assertRedirect('https://test-pay.khalti.com/?pidx=pidx-sbx-1');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_retrying_while_khalti_is_still_processing_is_blocked(): void
    {
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-proc-1');
        $this->continueWith($booking, 'khalti');

        // The original link has expired, so it can't be reused — but Khalti
        // still has the payment in flight: never start a second charge.
        $this->travel(61)->minutes();
        $this->fakeKhalti('pidx-proc-2', ['pidx' => 'pidx-proc-1', 'status' => 'Pending', 'total_amount' => 450000]);
        $this->continueWith($booking, 'khalti')
            ->assertRedirect(route('booking.confirmation', $booking->confirmation_code))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'still being processed'));

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_already_paid_booking_cannot_start_another_payment_through_continue(): void
    {
        $booking = $this->pendingBooking(['status' => Booking::STATUS_CONFIRMED]);
        Payment::factory()->for($booking)->completed()->create(['provider' => 'esewa', 'amount' => 4500]);
        Http::fake();

        $this->continueWith($booking, 'khalti')->assertRedirect(route('booking.confirmation', $booking->confirmation_code));

        Http::assertNothingSent();
        $this->assertDatabaseCount('payments', 1);
    }

    // --- Customer never returns ---------------------------------------------

    public function test_reconcile_command_confirms_a_payment_whose_customer_never_returned_and_emails_once(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-away-1');
        $this->continueWith($booking, 'khalti');

        $this->travel(5)->minutes();
        $this->fakeLookup(['pidx' => 'pidx-away-1', 'status' => 'Completed', 'total_amount' => 450000, 'transaction_id' => 'T-away']);

        $this->artisan('payments:reconcile-khalti')->assertExitCode(0);
        $this->artisan('payments:reconcile-khalti')->assertExitCode(0);
        // The customer finally returns too.
        $this->get(route('payment.khalti.return', ['pidx' => 'pidx-away-1']));

        $this->assertSame(Payment::STATUS_COMPLETED, Payment::sole()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }

    public function test_expiry_confirms_instead_of_cancelling_a_booking_paid_at_khalti(): void
    {
        Mail::fake();
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-exp-paid');
        $this->continueWith($booking, 'khalti');

        $this->travel(Booking::PENDING_HOLD_MINUTES + 5)->minutes();
        $this->fakeLookup(['pidx' => 'pidx-exp-paid', 'status' => 'Completed', 'total_amount' => 450000]);

        $this->artisan('bookings:expire-abandoned')->assertExitCode(0);

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
        Mail::assertQueued(BookingConfirmationMail::class, 1);
    }

    public function test_expiry_holds_a_booking_while_khalti_is_processing_or_unreachable(): void
    {
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-exp-hold');
        $this->continueWith($booking, 'khalti');
        $this->travel(Booking::PENDING_HOLD_MINUTES + 5)->minutes();

        $this->fakeLookup(['pidx' => 'pidx-exp-hold', 'status' => 'Pending', 'total_amount' => 450000]);
        $this->artisan('bookings:expire-abandoned');
        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);

        Http::swap(new HttpFactory);
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->artisan('bookings:expire-abandoned');
        $this->assertSame(Booking::STATUS_PENDING, $booking->refresh()->status);
    }

    public function test_expiry_still_cancels_when_the_khalti_session_was_never_paid(): void
    {
        $booking = $this->pendingBooking();
        $this->fakeKhalti('pidx-exp-unpaid');
        $this->continueWith($booking, 'khalti');
        $this->travel(Booking::PENDING_HOLD_MINUTES + 5)->minutes();

        $this->fakeLookup(['pidx' => 'pidx-exp-unpaid', 'status' => 'User canceled', 'total_amount' => 450000]);
        $this->artisan('bookings:expire-abandoned');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->refresh()->status);
        $this->assertSame(Payment::STATUS_CANCELLED, Payment::sole()->status);
    }
}
