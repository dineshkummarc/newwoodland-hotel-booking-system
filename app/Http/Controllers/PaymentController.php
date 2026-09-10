<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\BookingService;
use App\Services\EsewaPaymentService;
use App\Services\KhaltiPaymentService;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function show(Booking $booking)
    {
        abort_if($booking->status === Booking::STATUS_CANCELLED, 404);

        if ($booking->isPaid()) {
            return redirect()
                ->route('booking.confirmation', $booking->confirmation_code)
                ->with('success', 'This booking has already been paid for.');
        }

        $booking->load('room');

        $paymentMethods = collect(config('payments.methods'))
            ->map(fn (array $method, string $key) => $method + [
                'initiate_route' => match ($key) {
                    'esewa' => route('booking.pay.initiate', $booking->confirmation_code),
                    'khalti' => route('booking.pay.khalti.initiate', $booking->confirmation_code),
                    default => null,
                },
            ])
            ->all();

        return view('booking-payment', compact('booking', 'paymentMethods'));
    }

    public function initiate(Request $request, Booking $booking, EsewaPaymentService $esewa)
    {
        abort_if($booking->status === Booking::STATUS_CANCELLED, 404);

        if ($booking->isPaid()) {
            return redirect()->route('booking.confirmation', $booking->confirmation_code);
        }

        $result = $esewa->initiate($booking);

        // eSewa does not always include our transaction data on its return
        // (e.g. a customer who cancels at eSewa's login screen, before any
        // transaction is attempted, comes back with no query data at all).
        // Remember which booking this attempt was for so success()/failure()
        // can still send the customer back to it instead of the homepage.
        $request->session()->put('esewa_pending_booking', $booking->confirmation_code);

        return view('payment-redirect', [
            'actionUrl' => $result['action_url'],
            'fields' => $result['fields'],
        ]);
    }

    public function success(
        Request $request,
        EsewaPaymentService $esewa,
        BookingService $bookingService,
        SettingsService $settingsService
    ) {
        try {
            $payment = $esewa->handleReturn($request->query('data'));
        } catch (\RuntimeException $e) {
            return $this->redirectUnresolvedPaymentAttempt($request, 'esewa_pending_booking')
                ?? redirect()->route('home')->with('error', 'We could not verify your payment. Please contact us with your booking reference if you believe this is an error.');
        }

        $request->session()->forget('esewa_pending_booking');

        return $this->respondToVerifiedPayment($payment, $request, $bookingService, $settingsService);
    }

    public function failure(Request $request, EsewaPaymentService $esewa)
    {
        $payment = $esewa->markFailed($request->query('data'));

        if (! $payment) {
            return $this->redirectUnresolvedPaymentAttempt($request, 'esewa_pending_booking')
                ?? redirect()->route('home')->with('error', 'Your payment was not completed.');
        }

        $request->session()->forget('esewa_pending_booking');

        return redirect()
            ->route('booking.confirmation', $payment->booking->confirmation_code)
            ->with('error', 'Your payment was not completed. Your booking has not been confirmed — you can try paying again below.');
    }

    public function khaltiInitiate(Request $request, Booking $booking, KhaltiPaymentService $khalti)
    {
        abort_if($booking->status === Booking::STATUS_CANCELLED, 404);
        abort_unless($khalti->isAvailable(), 404);

        if ($booking->isPaid()) {
            return redirect()->route('booking.confirmation', $booking->confirmation_code);
        }

        $result = $khalti->initiate($booking);

        $request->session()->put('khalti_pending_booking', $booking->confirmation_code);

        return redirect()->away($result['payment_url']);
    }

    /**
     * Khalti uses a single return_url for every outcome (unlike eSewa's
     * separate success/failure routes) — status is only ever trusted after
     * an independent server-to-server lookup, never from the query string.
     */
    public function khaltiReturn(
        Request $request,
        KhaltiPaymentService $khalti,
        BookingService $bookingService,
        SettingsService $settingsService
    ) {
        try {
            $payment = $khalti->handleReturn($request->query('pidx'));
        } catch (\RuntimeException $e) {
            return $this->redirectUnresolvedPaymentAttempt($request, 'khalti_pending_booking')
                ?? redirect()->route('home')->with('error', 'We could not verify your payment. Please contact us with your booking reference if you believe this is an error.');
        }

        $request->session()->forget('khalti_pending_booking');

        return $this->respondToVerifiedPayment($payment, $request, $bookingService, $settingsService);
    }

    /**
     * Shared outcome handling once a gateway has independently verified (or
     * failed to verify) a payment — deciding the redirect/message doesn't
     * depend on which gateway did the verifying.
     */
    protected function respondToVerifiedPayment(
        Payment $payment,
        Request $request,
        BookingService $bookingService,
        SettingsService $settingsService
    ) {
        $booking = $payment->booking()->with('room')->first();

        if ($payment->isCompleted() && $booking->status === Booking::STATUS_CONFIRMED) {
            // Only send the "your booking is confirmed" email once payment
            // has actually been verified — never before.
            if (! $request->session()->get("payment_notified_{$payment->id}")) {
                $bookingService->sendNotifications($booking, $settingsService->current()->site_email);
                $request->session()->put("payment_notified_{$payment->id}", true);
            }

            return redirect()
                ->route('booking.confirmation', $booking->confirmation_code)
                ->with('success', 'Payment successful! Your booking is confirmed.');
        }

        if ($payment->isCompleted()) {
            // Payment captured, but the room could not be held (rare edge
            // case — see BookingService::confirmAfterVerifiedPayment).
            return redirect()
                ->route('booking.confirmation', $booking->confirmation_code)
                ->with('error', 'Your payment was received, but we could not automatically hold this room for the selected dates. Our staff will contact you shortly to resolve this.');
        }

        return redirect()
            ->route('booking.confirmation', $booking->confirmation_code)
            ->with('error', 'Your payment could not be verified. Please try again.');
    }

    /**
     * Some gateways don't always send back identifiable transaction data
     * (e.g. a customer who cancels at the gateway's own login screen,
     * before any transaction is attempted there). Fall back to the booking
     * we remember this browser last attempted to pay for via $sessionKey,
     * so the customer lands back on their booking instead of a bare
     * homepage with no way back to it.
     */
    protected function redirectUnresolvedPaymentAttempt(Request $request, string $sessionKey)
    {
        $code = $request->session()->pull($sessionKey);

        if (! $code) {
            return null;
        }

        $booking = Booking::where('confirmation_code', $code)->first();

        if (! $booking || $booking->isPaid()) {
            return null;
        }

        return redirect()
            ->route('booking.pay', $booking->confirmation_code)
            ->with('error', 'Your payment was not completed. You can try again below.');
    }
}
