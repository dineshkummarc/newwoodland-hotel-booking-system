<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * The normalized outcome of asking a gateway (eSewa / Fonepay) about one
 * payment attempt, server-to-server. The browser's own success/failure
 * redirect is never an outcome by itself — it only triggers this check.
 */
final class PaymentCheckResult
{
    /** The gateway confirmed the money for exactly the amount we charged. */
    public const COMPLETED = 'completed';

    /** The gateway says money is in flight (pending/ambiguous): never invite a second payment. */
    public const AWAITING = 'awaiting';

    /** No payment exists at the gateway (yet) — the guest may still be on the gateway's page. */
    public const UNPAID = 'unpaid';

    /** Definitively not paid (rejected, expired, amount mismatch, refunded). */
    public const FAILED = 'failed';

    /** The guest cancelled at the gateway. */
    public const CANCELLED = 'cancelled';

    /** No trustworthy answer (timeout, outage, unreadable reply, or not askable): nothing decided. */
    public const UNKNOWN = 'unknown';

    public function __construct(
        public readonly Payment $payment,
        public readonly string $outcome,
        // The gateway's own status string, for logs/audit (null when not asked/answered).
        public readonly ?string $gatewayStatus = null,
        // True only for the one call that moved the payment to completed —
        // confirmation emails are sent exactly once, whoever saw it first.
        public readonly bool $justCompleted = false,
    ) {}

    public function isCompleted(): bool
    {
        return $this->payment->isCompleted();
    }

    /**
     * Money may be (or may have been) taken: the guest must not be invited
     * to pay again, and the booking must not be expired yet.
     */
    public function mayHaveMoneyInFlight(): bool
    {
        return ! $this->isCompleted() && in_array($this->outcome, [self::AWAITING, self::UNKNOWN], true);
    }
}
