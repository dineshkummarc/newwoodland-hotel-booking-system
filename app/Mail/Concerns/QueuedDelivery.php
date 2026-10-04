<?php

namespace App\Mail\Concerns;

/**
 * Retry policy shared by the guest/admin notification mailables, which are
 * queued (ShouldQueueAfterCommit) so a slow or failing mail server never
 * delays or breaks the booking/payment response. A transient SMTP failure is
 * retried a few times before the job lands in failed_jobs for a human.
 */
trait QueuedDelivery
{
    public $tries = 3;

    /** Seconds to wait before each retry. */
    public $backoff = [60, 300];
}
