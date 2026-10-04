<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Client-confirmed hotel policies:
     *  - cancellation before arrival is free (0% deducted, no notice window);
     *    the 50% after-check-in charge is config, not a setting;
     *  - the official name is "Newwoodland Hotel & Lounge".
     * Earlier seeding stored a 50% pre-arrival deduction and a 24-hour notice
     * window, which contradict the policy, so those two are corrected
     * outright. Free-text fields are only rewritten where they still hold the
     * exact earlier seeded wording — anything an admin has written is kept.
     */
    protected const OLD_POLICY_TEXT = 'Bookings made through this website can be cancelled online up to the notice window before check-in; after that, please contact the hotel directly.';

    protected const NEW_POLICY_TEXT = 'Cancellation before arrival is free. If a stay is cancelled after check-in, 50% of the booking amount is charged.';

    protected const OLD_DESCRIPTION = 'Experience luxury, comfort, and authentic Nepalese hospitality at Newwoodland Hotel and Lounge in Bhadrapur-8, Jhapa, Nepal.';

    protected const NEW_DESCRIPTION = 'Experience luxury, comfort, and authentic Nepalese hospitality at Newwoodland Hotel & Lounge in Bhadrapur-8, Jhapa, Nepal.';

    public function up(): void
    {
        DB::table('settings')->update([
            'cancellation_deduction_percent' => 0,
            'cancellation_window_hours' => 0,
        ]);

        DB::table('settings')->where('hotel_name', 'Newwoodland Hotel and Lounge')->update(['hotel_name' => 'Newwoodland Hotel & Lounge']);
        DB::table('settings')->where('cancellation_policy_text', self::OLD_POLICY_TEXT)->update(['cancellation_policy_text' => self::NEW_POLICY_TEXT]);
        DB::table('settings')->where('hotel_description', self::OLD_DESCRIPTION)->update(['hotel_description' => self::NEW_DESCRIPTION]);

        // Settings are cached forever (SettingsService); drop the stale copy.
        Cache::forget(SettingsService::CACHE_KEY);
    }

    public function down(): void
    {
        // Nothing to restore: the previous values contradicted the confirmed policy.
    }
};
