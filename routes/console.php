<?php

use App\Models\PhoneOtp;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires the server cron `* * * * * php artisan schedule:run` to be
// configured in production — see the deployment checklist.
Schedule::command('bookings:expire-abandoned')->everyFifteenMinutes();

// Confirm eSewa/Fonepay payments whose customer never returned to the site
// (and retry gateway checks that timed out). Payment correctness must never
// depend on the guest's browser coming back.
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();

// Confirm Khalti payments whose customer never returned to the site.
Schedule::command('payments:reconcile-khalti')->everyFiveMinutes()->withoutOverlapping();

// Pre-arrival reminder emails (config/bookings.php); each booking at most once.
Schedule::command('bookings:send-checkin-reminders')->hourly()->withoutOverlapping();

// Delete SMS one-time passcode rows older than a day (see PhoneOtp::prunable()).
Schedule::command('model:prune', ['--model' => [PhoneOtp::class]])->daily();
