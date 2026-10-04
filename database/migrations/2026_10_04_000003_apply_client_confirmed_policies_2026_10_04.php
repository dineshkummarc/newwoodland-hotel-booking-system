<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Client-confirmed policies (final completion brief, 2026-10-04). This
     * resolves the earlier contradiction between "50% / 24h" (old notes)
     * and "0% / 0h" (seeder):
     *  - first-booking discount: 5%;
     *  - cancellation BEFORE arrival/check-in: 0% charge, no notice window
     *    (the 50% charge after check-in is config
     *    bookings.after_arrival_cancellation_percent, applied only to a
     *    checked-in stay — see BookingService::cancelAfterArrival());
     *  - fixed check-out time 12:00 (not "24 hours after arrival"). Only
     *    the old seeded 10:00 (or an empty value) is replaced, so a time an
     *    admin has deliberately set is never overwritten. The check-in time
     *    is NOT changed: the hotel has not confirmed it.
     */
    public function up(): void
    {
        DB::table('settings')->update([
            'first_booking_discount_percent' => 5,
            'cancellation_deduction_percent' => 0,
            'cancellation_window_hours' => 0,
        ]);

        DB::table('settings')
            ->where(fn ($q) => $q->whereNull('checkout_time')->orWhereIn('checkout_time', ['10:00', '10:00:00']))
            ->update(['checkout_time' => '12:00']);

        Cache::forget(SettingsService::CACHE_KEY);
    }

    public function down(): void
    {
        // Nothing to restore: the previous values were superseded by the client's confirmation.
    }
};
