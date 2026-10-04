<?php

namespace App\Console\Commands;

use App\Mail\CheckInReminderMail;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Queues one pre-arrival reminder per confirmed booking (config/bookings.php).
 * Safe to run as often as you like: each booking is claimed with a single
 * conditional UPDATE on reminder_sent_at before its email is queued, so
 * overlapping runs can never send the same reminder twice. Only confirmed
 * bookings qualify — pending, cancelled, no-show, checked-in and completed
 * stays are never reminded.
 */
class SendCheckInReminders extends Command
{
    protected $signature = 'bookings:send-checkin-reminders';

    protected $description = 'Email a reminder to confirmed guests whose check-in is coming up.';

    public function handle(): int
    {
        if (! config('bookings.checkin_reminder.enabled')) {
            $this->info('Check-in reminders are disabled (CHECKIN_REMINDER_ENABLED=false).');

            return self::SUCCESS;
        }

        $daysBefore = max(0, (int) config('bookings.checkin_reminder.days_before'));
        $skipHours = (int) config('bookings.checkin_reminder.skip_if_booked_within_hours');

        $candidates = Booking::query()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->whereNull('reminder_sent_at')
            ->whereDate('check_in', '>=', today())
            ->whereDate('check_in', '<=', today()->addDays($daysBefore))
            ->where('created_at', '<=', now()->subHours($skipHours))
            ->pluck('id');

        $sent = 0;

        foreach ($candidates as $id) {
            // Atomic claim: only the run that flips reminder_sent_at sends.
            $claimed = Booking::query()
                ->whereKey($id)
                ->where('status', Booking::STATUS_CONFIRMED)
                ->whereNull('reminder_sent_at')
                ->update(['reminder_sent_at' => now()]);

            if (! $claimed) {
                continue;
            }

            $booking = Booking::with('room')->find($id);
            Mail::to($booking->email)->send(new CheckInReminderMail($booking));
            $sent++;
        }

        $this->info("Queued {$sent} check-in reminder(s).");

        return self::SUCCESS;
    }
}
