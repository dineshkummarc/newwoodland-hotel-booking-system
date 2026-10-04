<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingService;
use App\Services\Payments\PaymentReconciler;
use Illuminate\Console\Command;

/**
 * A pending booking that's aged past Booking::PENDING_HOLD_MINUTES already
 * stops blocking the room for other guests (see Booking::scopeOverlapping)
 * — but the row itself stays 'pending' forever unless something actually
 * cancels it. Left unchecked this silently pollutes the dashboard's
 * "Pending Bookings" count and every report that counts pending bookings
 * with abandoned checkouts nobody ever completed.
 *
 * Before a booking is released, every online payment attempt on it is
 * settled with its gateway (PaymentReconciler::mayExpire()): a payment that
 * completed without the customer ever returning confirms the booking
 * instead, and a payment that may still be in flight keeps it for now.
 *
 * Intended to run on a schedule (see routes/console.php) — safe to run as
 * often as you like, each candidate is re-verified under its own row lock
 * immediately before being cancelled (BookingService::expireIfAbandoned),
 * so a payment that completes mid-run for one of these bookings is never
 * raced into being wrongly cancelled.
 */
class ExpireAbandonedBookings extends Command
{
    protected $signature = 'bookings:expire-abandoned';

    protected $description = 'Cancel pending bookings that have aged past their availability hold and were never paid.';

    public function handle(BookingService $bookingService, PaymentReconciler $reconciler): int
    {
        $candidates = Booking::query()
            ->where('status', Booking::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes(Booking::PENDING_HOLD_MINUTES))
            ->whereDoesntHave('payments', fn ($p) => $p->activeOfflineHold())
            ->get();

        $expired = 0;

        foreach ($candidates as $booking) {
            if (! $reconciler->mayExpire($booking)) {
                continue;
            }

            if ($bookingService->expireIfAbandoned($booking)) {
                $expired++;
            }
        }

        $this->info("Expired {$expired} abandoned pending booking(s) out of {$candidates->count()} candidate(s).");

        return self::SUCCESS;
    }
}
