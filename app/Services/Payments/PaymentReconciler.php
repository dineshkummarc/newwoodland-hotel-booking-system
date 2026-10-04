<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\BookingService;
use App\Services\EsewaPaymentService;
use App\Services\FonepayPaymentService;
use App\Services\KhaltiPaymentService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;

/**
 * One place that settles online payment attempts with their gateway,
 * whether or not the customer ever came back to the site. Used by:
 *  - the scheduled `payments:reconcile` command (every few minutes);
 *  - `bookings:expire-abandoned`, which asks mayExpire() before releasing
 *    an unpaid booking, so a payment in flight is never buried;
 *  - the payment pages, before a guest may start another attempt.
 *
 * The gateway services do the actual verification under row locks; this
 * class only decides what to do with their outcome. Everything here is
 * idempotent and safe to run repeatedly or concurrently.
 */
class PaymentReconciler
{
    public function __construct(
        protected EsewaPaymentService $esewa,
        protected FonepayPaymentService $fonepay,
        protected KhaltiPaymentService $khalti,
        protected BookingService $bookings,
        protected SettingsService $settings,
    ) {}

    /**
     * Ask the gateway about one eSewa/Fonepay attempt. Null for providers
     * this class doesn't reconcile (offline methods; Khalti has its own).
     */
    public function reconcile(Payment $payment): ?PaymentCheckResult
    {
        $result = match ($payment->provider) {
            'esewa' => $this->esewa->reconcile($payment),
            'fonepay' => $this->fonepay->reconcile($payment),
            default => null,
        };

        if ($result) {
            $this->notifyIfJustConfirmed($result);
        }

        return $result;
    }

    /**
     * Send the normal booking-confirmed emails for the one call that
     * completed a payment — whether that was the customer's return, a
     * scheduled run, or the expiry check. Exactly once per payment.
     */
    public function notifyIfJustConfirmed(PaymentCheckResult $result): void
    {
        if (! $result->justCompleted) {
            return;
        }

        $booking = $result->payment->booking()->with('room')->first();

        if ($booking?->status === Booking::STATUS_CONFIRMED) {
            $this->bookings->sendNotifications($booking, $this->settings->current()->site_email);
        }
    }

    /**
     * Whether an unpaid booking may be expired now. False while a payment
     * attempt may still complete: it was started only moments ago, the
     * gateway says money is in flight, or the gateway could not be asked
     * (retried on the next run). An attempt the gateway can NEVER be asked
     * about (a Fonepay checkout the guest never returned from), or that
     * stays unresolved too long, is flagged for staff and the booking is
     * released — staff check the gateway's merchant portal.
     */
    public function mayExpire(Booking $booking): bool
    {
        if ($booking->payments()->where('provider', 'khalti')->where('status', Payment::STATUS_PENDING)->exists()
            && ! $this->khalti->mayExpire($booking, $this->settings)) {
            return false;
        }

        $graceMinutes = (int) config('bookings.online_payment_grace_minutes', 15);
        $maxUnresolvedHours = (int) config('bookings.online_payment_max_unresolved_hours', 24);
        $mayExpire = true;

        $attempts = $booking->payments()
            ->whereIn('provider', ['esewa', 'fonepay'])
            ->where('status', Payment::STATUS_PENDING)
            ->orderBy('id')
            ->get();

        foreach ($attempts as $payment) {
            // The guest may be on the gateway's page right now.
            if ($payment->created_at->gt(now()->subMinutes($graceMinutes))) {
                $mayExpire = false;

                continue;
            }

            $result = $this->reconcile($payment);

            if ($result->isCompleted()) {
                // Confirmed, or flagged for staff by confirmAfterVerifiedPayment().
                $mayExpire = false;

                continue;
            }

            if (! $result->mayHaveMoneyInFlight()) {
                continue;
            }

            $unaskable = $payment->provider === 'fonepay' && ! $this->fonepay->hasTraceIds($payment);

            if ($unaskable || $payment->created_at->lte(now()->subHours($maxUnresolvedHours))) {
                $this->bookings->flagPaymentForReview($result->payment, $unaskable
                    ? "The guest started a Fonepay payment (reference {$payment->transaction_uuid}) but never returned to the site, and Fonepay cannot be asked about it automatically. The unpaid booking was released. Check the Fonepay merchant portal: if this reference was paid, contact the guest (re-confirm or refund)."
                    : "The {$payment->provider} payment (reference {$payment->transaction_uuid}) could not be confirmed with the gateway for {$maxUnresolvedHours} hours. The unpaid booking was released. Check the merchant portal: if it was paid, contact the guest (re-confirm or refund).");

                continue;
            }

            $mayExpire = false;
        }

        if (! $mayExpire) {
            Log::info('Unpaid booking kept: an online payment may still complete.', ['booking_id' => $booking->id]);
        }

        return $mayExpire;
    }
}
