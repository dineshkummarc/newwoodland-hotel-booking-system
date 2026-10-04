<?php

namespace App\Services;

use App\Models\Payment;

/**
 * The normalized outcome of checking a Khalti payment with Khalti's lookup
 * API (KhaltiPaymentService::reconcile()), so callers never need to know
 * Khalti's raw status strings.
 */
final class KhaltiVerificationResult
{
    public function __construct(
        public readonly Payment $payment,
        // Khalti's own lookup status ("Completed", "Pending", "Initiated",
        // "User canceled", "Expired", "Refunded", "Partially Refunded"), or
        // null when Khalti could not be asked / gave no usable answer.
        public readonly ?string $gatewayStatus,
        // True only for the one call that moved the payment to completed —
        // used so confirmation emails are sent exactly once.
        public readonly bool $justCompleted = false,
    ) {}

    public function isCompleted(): bool
    {
        return $this->payment->isCompleted();
    }

    /**
     * Khalti could not be reached or answered unusably: nothing about the
     * payment has been decided, in either direction.
     */
    public function isIndeterminate(): bool
    {
        return $this->gatewayStatus === null && ! $this->payment->isCompleted();
    }

    /**
     * Money may be moving (Khalti says Pending / Partially Refunded): the
     * customer must not be invited to pay again.
     */
    public function isAwaitingKhalti(): bool
    {
        return in_array($this->gatewayStatus, KhaltiPaymentService::HOLD_STATUSES, true)
            && $this->gatewayStatus !== KhaltiPaymentService::STATUS_INITIATED;
    }

    /**
     * The customer opened Khalti's checkout but has not paid yet.
     */
    public function isUnpaidButOpen(): bool
    {
        return $this->gatewayStatus === KhaltiPaymentService::STATUS_INITIATED;
    }

    public function isCancelled(): bool
    {
        return $this->payment->status === Payment::STATUS_CANCELLED;
    }
}
