<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\BookingService;
use App\Services\Payments\PaymentCheckResult;
use App\Services\Payments\PaymentReconciler;
use Illuminate\Console\Command;

/**
 * Settles eSewa and Fonepay payments server-to-server, so a payment never
 * depends on the customer's browser returning to the site (closed tab,
 * app switch, lost connection). Safe to run as often as you like: every
 * check locks its payment row and a completed payment is never processed
 * twice. Khalti has its own payments:reconcile-khalti.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Verify pending eSewa/Fonepay payments with the gateway and confirm, fail or flag them.';

    public function handle(PaymentReconciler $reconciler, BookingService $bookings): int
    {
        $staleDays = (int) config('bookings.online_payment_reconcile_days', 3);
        $counts = array_fill_keys([
            PaymentCheckResult::COMPLETED, PaymentCheckResult::AWAITING, PaymentCheckResult::UNPAID,
            PaymentCheckResult::FAILED, PaymentCheckResult::CANCELLED, PaymentCheckResult::UNKNOWN,
        ], 0);
        $newlyCompleted = 0;

        Payment::query()
            ->whereIn('provider', ['esewa', 'fonepay'])
            ->where('status', Payment::STATUS_PENDING)
            // Give customers who are mid-checkout a moment first.
            ->where('created_at', '<=', now()->subMinutes(2))
            ->orderBy('id')
            ->each(function (Payment $payment) use ($reconciler, $bookings, $staleDays, &$counts, &$newlyCompleted) {
                $result = $reconciler->reconcile($payment);

                $counts[$result->outcome]++;
                $newlyCompleted += $result->justCompleted ? 1 : 0;

                // Still undecided after days: a human has to look.
                if ($result->payment->isPending() && $payment->created_at->lte(now()->subDays($staleDays))) {
                    $bookings->flagPaymentForReview($result->payment, "This {$payment->provider} payment (reference {$payment->transaction_uuid}) has stayed unresolved for {$staleDays} days. Check the merchant portal and settle it manually.");
                }
            });

        $summary = collect($counts)->filter()->map(fn ($n, $outcome) => "{$outcome}: {$n}")->implode(', ') ?: 'nothing pending';
        $this->info("Reconciled eSewa/Fonepay payments — {$summary}; {$newlyCompleted} newly completed.");

        return self::SUCCESS;
    }
}
