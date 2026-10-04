<?php

namespace App\Http\Controllers;

use App\Mail\BankTransferInstructionsMail;
use App\Mail\OfflinePaymentIntentAdminMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\BankTransferPaymentService;
use App\Services\BookingService;
use App\Services\CashPaymentService;
use App\Services\EsewaPaymentService;
use App\Services\FonepayPaymentService;
use App\Services\KhaltiPaymentService;
use App\Services\Payments\HoldExpiredException;
use App\Services\Payments\PaymentCheckResult;
use App\Services\Payments\PaymentReconciler;
use App\Services\SettingsService;
use App\Support\BookingAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Guest checkout. Every booking-specific route is behind booking.access
 * (App\Support\BookingAccess) — the confirmation code alone never lets
 * anyone pay for, view or change a booking.
 *
 * A payment is only ever "paid" when the gateway confirms it server-to-
 * server (the gateway services' reconcile()). The browser coming back to
 * the success/failure URL merely triggers that check early; the scheduled
 * `payments:reconcile` makes the same check for customers who never return.
 */
class PaymentController extends Controller
{
    public function show(Booking $booking)
    {
        abort_if($booking->status === Booking::STATUS_CANCELLED, 404);

        if ($booking->isPaid()) {
            return redirect()
                ->route('booking.confirmation', $booking->confirmation_code)
                ->with('success', $booking->outstandingBalance() > 0
                    ? 'Your advance payment has been received. The remaining '.npr($booking->outstandingBalance()).' is payable at the hotel.'
                    : 'This booking has already been paid for.');
        }

        $booking->load('room');

        // Only methods the hotel actually accepts right now are shown at all.
        $paymentMethods = array_filter($this->paymentMethods($booking), fn (array $method) => $method['enabled']);
        $bankName = app(SettingsService::class)->current()->bank_name;
        $split = $this->splitPayment($booking);

        return view('booking-payment', compact('booking', 'paymentMethods', 'bankName', 'split'));
    }

    /**
     * The "pay part now, rest at the hotel" (Pay at Hotel) option, or null when it
     * is switched off.
     *
     * @return array{percent: int, now: float, later: float, methods: list<string>}|null
     */
    protected function splitPayment(Booking $booking): ?array
    {
        $percent = (int) config('payments.split_payment.percent');

        if (! config('payments.split_payment.enabled') || $percent <= 0 || $percent >= 100) {
            return null;
        }

        $now = round((float) $booking->total_price * $percent / 100, 2);

        return [
            'percent' => $percent,
            'now' => $now,
            'later' => round((float) $booking->total_price - $now, 2),
            'methods' => config('payments.split_payment.methods', []),
        ];
    }

    /**
     * "Continue Payment": the payment page submits the chosen method here,
     * and it is handed to that method's own initiate action — so every
     * existing check (booking state, availability, amount) still applies.
     */
    public function continue(Request $request, Booking $booking)
    {
        $methods = $this->paymentMethods($booking);

        $validated = $request->validate([
            'payment_method' => ['required', 'string', Rule::in(array_keys($methods))],
            'payment_plan' => ['nullable', Rule::in(['full', 'split'])],
        ], [
            'payment_method.required' => 'Please choose a payment method.',
            'payment_method.in' => 'Please choose a valid payment method.',
        ]);
        $method = $validated['payment_method'];

        if (! $methods[$method]['enabled']) {
            return redirect()
                ->route('booking.pay', $booking->confirmation_code)
                ->withErrors(['payment_method' => $methods[$method]['label'].' is not available right now. Please choose another payment method.']);
        }

        // Pay part now / rest at the hotel — only with a method that supports it.
        $split = $this->splitPayment($booking);
        $useSplit = ($validated['payment_plan'] ?? 'full') === 'split'
            && $split && in_array($method, $split['methods'], true);

        if (! $booking->isPaid()) {
            $booking->update(['deposit_percent' => $useSplit ? $split['percent'] : null]);
        }

        $action = match ($method) {
            'cash' => 'cashInitiate',
            'bank_transfer' => 'bankTransferInitiate',
            'esewa' => 'initiate',
            'fonepay' => 'fonepayInitiate',
            'khalti' => 'khaltiInitiate',
        };

        return app()->call([$this, $action], ['booking' => $booking]);
    }

    /**
     * Payment methods for the checkout, from config/payments.php, each with
     * `enabled` resolved through its service's isAvailable() — so a method
     * is only offered when it is switched on AND its credentials / account
     * details exist (and, on a production server, are production
     * credentials — see GatewayEnvironmentGuard).
     *
     * @return array<string, array<string, mixed>>
     */
    protected function paymentMethods(Booking $booking): array
    {
        $services = [
            'cash' => CashPaymentService::class,
            'bank_transfer' => BankTransferPaymentService::class,
            'esewa' => EsewaPaymentService::class,
            'fonepay' => FonepayPaymentService::class,
            'khalti' => KhaltiPaymentService::class,
        ];

        return collect(config('payments.methods'))
            ->map(fn (array $method, string $key) => array_merge($method, [
                'enabled' => isset($services[$key]) && app($services[$key])->isAvailable(),
            ]))
            ->all();
    }

    /**
     * Checks shared by the online gateways before a new attempt may start.
     * Returns a response to send instead, or null to go ahead.
     */
    protected function guardOnlineAttempt(Request $request, Booking $booking, ?PaymentCheckResult $blocking, PaymentReconciler $reconciler)
    {
        if ($booking->isPaid()) {
            return redirect()->route('booking.confirmation', $booking->confirmation_code);
        }

        if ($blocker = app(BookingService::class)->onlinePaymentBlocker($booking)) {
            return redirect()->route('booking.confirmation', $booking->confirmation_code)->with('error', $blocker);
        }

        if ($blocking) {
            if ($blocking->isCompleted()) {
                return $this->respondToCheck($blocking, $request, $reconciler);
            }

            return redirect()
                ->route('booking.confirmation', $booking->confirmation_code)
                ->with('error', 'Your previous payment for this booking is still being processed by the payment provider. Please do not pay again — your booking will be confirmed automatically as soon as the provider confirms it. If money was deducted and you have not heard from us within an hour, please contact the hotel with your booking reference.');
        }

        return null;
    }

    public function initiate(Request $request, Booking $booking, EsewaPaymentService $esewa, PaymentReconciler $reconciler)
    {
        abort_if($booking->status === Booking::STATUS_CANCELLED, 404);
        abort_unless($esewa->isAvailable(), 404);

        if ($response = $this->guardOnlineAttempt($request, $booking, $booking->isPaid() ? null : $esewa->blockingAttempt($booking), $reconciler)) {
            return $response;
        }

        try {
            $result = $esewa->initiate($booking);
        } catch (\RuntimeException) {
            return redirect()
                ->route('booking.confirmation', $booking->confirmation_code)
                ->with('error', 'We could not start another eSewa payment for this booking. Please contact the hotel with your booking reference.');
        }

        // eSewa does not always include our transaction data on its return
        // (e.g. a customer who cancels at eSewa's login screen comes back
        // with no query data at all). Remember which booking this attempt
        // was for so the customer can be sent back to it.
        $request->session()->put('esewa_pending_booking', $booking->confirmation_code);

        return view('payment-redirect', [
            'actionUrl' => $result['action_url'],
            'fields' => $result['fields'],
        ]);
    }

    public function success(Request $request, EsewaPaymentService $esewa, PaymentReconciler $reconciler)
    {
        try {
            $result = $esewa->handleReturn($request->query('data'));
        } catch (\RuntimeException) {
            return $this->redirectUnresolvedPaymentAttempt($request, 'esewa_pending_booking')
                ?? redirect()->route('home')->with('error', 'We could not verify your payment. Please contact us with your booking reference if you believe this is an error.');
        }

        $request->session()->forget('esewa_pending_booking');

        return $this->respondToCheck($result, $request, $reconciler);
    }

    public function failure(Request $request, EsewaPaymentService $esewa, PaymentReconciler $reconciler)
    {
        $result = $esewa->handleFailure($request->query('data'));

        if (! $result) {
            return $this->redirectUnresolvedPaymentAttempt($request, 'esewa_pending_booking')
                ?? redirect()->route('home')->with('error', 'Your payment was not completed.');
        }

        $request->session()->forget('esewa_pending_booking');

        return $this->respondToCheck($result, $request, $reconciler);
    }

    public function khaltiInitiate(
        Request $request,
        Booking $booking,
        KhaltiPaymentService $khalti,
        BookingService $bookingService,
        SettingsService $settingsService
    ) {
        abort_if($booking->status === Booking::STATUS_CANCELLED, 404);
        abort_unless($khalti->isAvailable(), 404);

        if ($booking->isPaid()) {
            return redirect()->route('booking.confirmation', $booking->confirmation_code);
        }

        if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED], true)) {
            return redirect()
                ->route('booking.confirmation', $booking->confirmation_code)
                ->with('error', 'This booking can no longer be paid online. Please contact the hotel.');
        }

        // An earlier Khalti attempt for this booking is still open: settle
        // it with Khalti first, so the same booking can never be paid twice.
        if ($open = $khalti->openPayment($booking)) {
            $result = $khalti->reconcile($open);

            if ($result->isCompleted()) {
                return $this->respondToVerifiedPayment($result->payment, $request, $bookingService, $settingsService, $result->justCompleted);
            }

            // Not paid yet and its Khalti link is still valid (Khalti reports
            // a freshly opened session as "Initiated" or "Pending"): continue
            // that SAME session — one pidx can never be charged twice.
            if (in_array($result->gatewayStatus, [KhaltiPaymentService::STATUS_INITIATED, KhaltiPaymentService::STATUS_PENDING], true)
                && ($url = $khalti->reusableCheckoutUrl($result->payment, $booking))) {
                $request->session()->put('khalti_pending_booking', $booking->confirmation_code);

                return redirect()->away($url);
            }

            if ($result->isAwaitingKhalti()) {
                return redirect()
                    ->route('booking.confirmation', $booking->confirmation_code)
                    ->with('error', 'Your previous Khalti payment for this booking is still being processed by Khalti. Please do not pay again — your booking will be confirmed automatically once Khalti completes it.');
            }

            if ($result->isIndeterminate()) {
                return redirect()
                    ->route('booking.pay', $booking->confirmation_code)
                    ->with('error', 'We could not check your previous Khalti payment just now. Please try again in a moment.');
            }

        }

        if (KhaltiPaymentService::toPaisa($booking->amountDueNow()) < (int) config('khalti.minimum_amount_paisa', 1000)) {
            return redirect()
                ->route('booking.pay', $booking->confirmation_code)
                ->with('error', 'Khalti requires a minimum payment of Rs. 10. Please choose another payment method.');
        }

        try {
            $result = $khalti->initiate($booking);
        } catch (\RuntimeException $e) {
            // Khalti rejected/could not receive the request (already logged
            // by the service) — send the customer back to choose again.
            return redirect()
                ->route('booking.pay', $booking->confirmation_code)
                ->with('error', 'Khalti could not start your payment right now. Please try again or choose another payment method.');
        }

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
        $pidx = $request->query('pidx');

        try {
            $result = $khalti->handleReturn(is_string($pidx) ? $pidx : null, $request->query());
        } catch (\RuntimeException $e) {
            return $this->redirectUnresolvedPaymentAttempt($request, 'khalti_pending_booking')
                ?? redirect()->route('home')->with('error', 'We could not verify your payment. Please contact us with your booking reference if you believe this is an error.');
        }

        $request->session()->forget('khalti_pending_booking');

        $payment = $result->payment;
        // Coming back with a pidx we issued for this booking identifies the payer.
        BookingAccess::grant($request, $payment->booking);
        $confirmation = redirect()->route('booking.confirmation', $payment->booking->confirmation_code);

        if ($result->isCompleted()) {
            return $this->respondToVerifiedPayment($payment, $request, $bookingService, $settingsService, $result->justCompleted);
        }

        if ($result->isIndeterminate() || $result->isAwaitingKhalti()) {
            // Nothing decided either way — don't invite a second payment.
            return $confirmation->with('error', 'We could not confirm your Khalti payment just yet. If money was deducted from your Khalti account, please do not pay again — your booking will be confirmed automatically once Khalti confirms it, or contact us with your booking reference.');
        }

        if ($result->isCancelled()) {
            return $confirmation->with('error', 'You cancelled the Khalti payment. Your booking has not been confirmed — you can try paying again below.');
        }

        if ($result->isUnpaidButOpen()) {
            return $confirmation->with('error', 'Your Khalti payment was not completed. Your booking has not been confirmed — you can try paying again below.');
        }

        return $this->respondToVerifiedPayment($payment, $request, $bookingService, $settingsService, false);
    }

    public function fonepayInitiate(Request $request, Booking $booking, FonepayPaymentService $fonepay, PaymentReconciler $reconciler)
    {
        abort_if($booking->status === Booking::STATUS_CANCELLED, 404);
        abort_unless($fonepay->isAvailable(), 404);

        if ($response = $this->guardOnlineAttempt($request, $booking, $booking->isPaid() ? null : $fonepay->blockingAttempt($booking), $reconciler)) {
            return $response;
        }

        try {
            $result = $fonepay->initiate($booking);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('booking.pay', $booking->confirmation_code)
                ->with('error', 'Fonepay could not start your payment right now. Please try again or choose another payment method.');
        }

        $request->session()->put('fonepay_pending_booking', $booking->confirmation_code);

        return redirect()->away($result['payment_url']);
    }

    /**
     * Fonepay uses a single return URL (RU) for every outcome, with the
     * result in the query string — which is never trusted directly. The
     * service confirms the payment with Fonepay server-to-server before
     * anything is marked paid.
     */
    public function fonepayReturn(Request $request, FonepayPaymentService $fonepay, PaymentReconciler $reconciler)
    {
        try {
            $result = $fonepay->handleReturn($request->query());
        } catch (\RuntimeException $e) {
            return $this->redirectUnresolvedPaymentAttempt($request, 'fonepay_pending_booking')
                ?? redirect()->route('home')->with('error', 'We could not verify your payment. Please contact us with your booking reference if you believe this is an error.');
        }

        $request->session()->forget('fonepay_pending_booking');

        return $this->respondToCheck($result, $request, $reconciler);
    }

    /**
     * Cash is paid in person at the hotel: choosing it only records the
     * guest's choice (holding the booking) — nothing is marked paid or
     * confirmed here. Staff confirm the booking and record the cash from
     * the admin.
     */
    public function cashInitiate(Booking $booking, CashPaymentService $cash)
    {
        abort_if($booking->status === Booking::STATUS_CANCELLED, 404);
        abort_unless($cash->isAvailable(), 404);

        if ($booking->isPaid()) {
            return redirect()->route('booking.confirmation', $booking->confirmation_code);
        }

        try {
            $cash->initiate($booking);
        } catch (HoldExpiredException) {
            return $this->holdExpired($booking);
        } catch (\RuntimeException $e) {
            return redirect()->route('booking.confirmation', $booking->confirmation_code);
        }

        return redirect()
            ->route('booking.confirmation', $booking->confirmation_code)
            ->with('success', 'Thank you — you have chosen to pay at the hotel. Our team will contact you to confirm your booking.');
    }

    /**
     * Bank Transfer is a manual method: choosing it only records the guest's
     * intent and shows the hotel's account details. The booking is NOT
     * confirmed here — staff confirm receipt from the admin. The guest is
     * emailed the reference and details, and staff are told a room is now
     * held awaiting a transfer.
     */
    public function bankTransferInitiate(Booking $booking, BankTransferPaymentService $bank, SettingsService $settingsService)
    {
        abort_if($booking->status === Booking::STATUS_CANCELLED, 404);
        abort_unless($bank->isAvailable(), 404);

        if ($booking->isPaid()) {
            return redirect()->route('booking.confirmation', $booking->confirmation_code);
        }

        try {
            ['payment' => $payment, 'created' => $created] = $bank->initiate($booking);
        } catch (HoldExpiredException) {
            return $this->holdExpired($booking);
        } catch (\RuntimeException $e) {
            return redirect()->route('booking.confirmation', $booking->confirmation_code);
        }

        // Once per hold — re-selecting Bank Transfer reuses the same row.
        if ($created) {
            Mail::to($booking->email)->send(new BankTransferInstructionsMail($payment));

            if ($adminEmail = $settingsService->current()->site_email) {
                Mail::to($adminEmail)->send(new OfflinePaymentIntentAdminMail($payment));
            }
        }

        return redirect()->route('booking.pay.bank.show', $booking->confirmation_code);
    }

    public function bankTransferShow(Booking $booking, BankTransferPaymentService $bank)
    {
        abort_if($booking->status === Booking::STATUS_CANCELLED, 404);
        abort_unless($bank->isAvailable(), 404);

        if ($booking->isPaid()) {
            return redirect()->route('booking.confirmation', $booking->confirmation_code);
        }

        $payment = $booking->payments()->where('provider', 'bank_transfer')->where('status', Payment::STATUS_PENDING)->latest('id')->first();

        // Account details are only shown once the guest has actually chosen Bank Transfer.
        if (! $payment) {
            return redirect()->route('booking.pay', $booking->confirmation_code);
        }

        $booking->load('room');

        return view('booking-bank-transfer', [
            'booking' => $booking,
            'payment' => $payment,
            'account' => $bank->accountDetails(),
            'holdUntil' => $payment->created_at->copy()->addHours((int) config('bank_transfer.hold_hours', 24)),
        ]);
    }

    protected function holdExpired(Booking $booking)
    {
        return redirect()
            ->route('booking.confirmation', $booking->confirmation_code)
            ->with('error', 'Sorry — the hold on this booking has expired, so it can no longer be held for a bank transfer. Please make a new booking (or pay online straight away).');
    }

    /**
     * The customer's browser is back from eSewa/Fonepay and the gateway has
     * been asked server-to-server; tell them what was actually decided.
     */
    protected function respondToCheck(PaymentCheckResult $result, Request $request, PaymentReconciler $reconciler)
    {
        $payment = $result->payment;
        $booking = $payment->booking()->with('room')->first();

        // Coming back with our high-entropy transaction reference for this
        // booking identifies the payer — let them see the outcome.
        BookingAccess::grant($request, $booking);
        $reconciler->notifyIfJustConfirmed($result);

        $confirmation = redirect()->route('booking.confirmation', $booking->confirmation_code);

        if ($payment->isCompleted()) {
            if ($booking->status === Booking::STATUS_CONFIRMED && ! $payment->needsReview()) {
                return $confirmation->with('success', 'Payment successful! Your booking is confirmed.');
            }

            if ($booking->status === Booking::STATUS_CONFIRMED) {
                return $confirmation->with('success', 'Payment received. Your booking is confirmed — our team will contact you about a duplicate payment.');
            }

            // Payment captured, but the booking could not be (re)confirmed:
            // it expired, or the room was taken meanwhile. Flagged for staff.
            return $confirmation->with('error', 'Your payment was received, but we could not automatically hold this room for the selected dates. Our staff have been alerted and will contact you shortly to resolve this (another room or a full refund).');
        }

        return match ($result->outcome) {
            PaymentCheckResult::AWAITING, PaymentCheckResult::UNKNOWN => $confirmation->with('error', 'We could not confirm your payment with the payment provider just yet. If money was deducted, please do not pay again — your booking will be confirmed automatically once the provider confirms it, or contact us with your booking reference.'),
            PaymentCheckResult::CANCELLED => $confirmation->with('error', 'You cancelled the payment. Your booking has not been confirmed — you can try paying again below.'),
            PaymentCheckResult::UNPAID => $confirmation->with('error', 'Your payment was not completed. Your booking has not been confirmed — you can try paying again below.'),
            default => $confirmation->with('error', 'Your payment could not be verified. Your booking has not been confirmed — you can try paying again below.'),
        };
    }

    /**
     * Khalti's outcome handling (its own verification result type).
     */
    protected function respondToVerifiedPayment(
        Payment $payment,
        Request $request,
        BookingService $bookingService,
        SettingsService $settingsService,
        // Gateways that can tell exactly which call completed the payment
        // (Khalti) pass it here, so the email goes out once however many
        // requests/jobs see the payment; null = per-session de-duplication.
        ?bool $notify = null
    ) {
        $booking = $payment->booking()->with('room')->first();
        BookingAccess::grant($request, $booking);

        if ($payment->isCompleted() && $booking->status === Booking::STATUS_CONFIRMED) {
            // Only send the "your booking is confirmed" email once payment
            // has actually been verified — never before.
            if ($notify ?? ! $request->session()->get("payment_notified_{$payment->id}")) {
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
     * this browser last attempted to pay for via $sessionKey — that session
     * already had access to it — so the customer lands back on their booking.
     */
    protected function redirectUnresolvedPaymentAttempt(Request $request, string $sessionKey)
    {
        $code = $request->session()->pull($sessionKey);

        if (! $code) {
            return null;
        }

        $booking = Booking::where('confirmation_code', $code)->first();

        if (! $booking || $booking->isPaid() || ! BookingAccess::allows($request, $booking)) {
            return null;
        }

        return redirect()
            ->route('booking.pay', $booking->confirmation_code)
            ->with('error', 'Your payment was not completed. You can try again below.');
    }
}
