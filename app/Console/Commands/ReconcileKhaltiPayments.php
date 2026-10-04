<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\KhaltiPaymentService;
use App\Services\SettingsService;
use Illuminate\Console\Command;

/**
 * Settles Khalti payments whose customer never came back to our return URL
 * (closed the tab, lost connection, session expired) by asking Khalti's
 * lookup API directly. Without this, a customer who paid but didn't return
 * would never be confirmed. Safe to run repeatedly: reconcile() is
 * idempotent and locks each payment row.
 */
class ReconcileKhaltiPayments extends Command
{
    protected $signature = 'payments:reconcile-khalti';

    protected $description = 'Verify pending Khalti payments with Khalti and confirm/fail them accordingly.';

    public function handle(KhaltiPaymentService $khalti, SettingsService $settings): int
    {
        if (! filled(config('khalti.secret_key'))) {
            $this->info('KHALTI_SECRET_KEY is not set — nothing to reconcile.');

            return self::SUCCESS;
        }

        $checked = 0;
        $completed = 0;

        Payment::query()
            ->where('provider', 'khalti')
            ->where('status', Payment::STATUS_PENDING)
            // Give customers who are mid-checkout a moment first; older
            // than two days is left for staff (Khalti links last 60 minutes).
            ->where('created_at', '<=', now()->subMinutes(2))
            ->where('created_at', '>=', now()->subDays(2))
            ->orderBy('id')
            ->each(function (Payment $payment) use ($khalti, $settings, &$checked, &$completed) {
                $result = $khalti->reconcile($payment);
                $khalti->notifyIfJustConfirmed($result, $settings);

                $checked++;
                $completed += $result->justCompleted ? 1 : 0;
            });

        $this->info("Checked {$checked} pending Khalti payment(s); {$completed} newly completed.");

        return self::SUCCESS;
    }
}
