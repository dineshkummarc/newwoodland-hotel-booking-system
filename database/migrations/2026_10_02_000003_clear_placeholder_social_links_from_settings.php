<?php

use Illuminate\Database\Migrations\Migration;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Earlier seeding filled the social links with the networks' generic home
     * pages (not the hotel's profiles), which the footer then showed as real
     * links. Clear only those exact placeholder values; any real link an
     * admin has entered is left untouched.
     */
    protected const PLACEHOLDERS = [
        'facebook_url' => ['https://facebook.com', 'https://facebook.com/', 'https://www.facebook.com', 'https://www.facebook.com/'],
        'instagram_url' => ['https://instagram.com', 'https://instagram.com/', 'https://www.instagram.com', 'https://www.instagram.com/'],
        'youtube_url' => ['https://youtube.com', 'https://youtube.com/', 'https://www.youtube.com', 'https://www.youtube.com/'],
        'tiktok_url' => ['https://tiktok.com', 'https://tiktok.com/', 'https://www.tiktok.com', 'https://www.tiktok.com/'],
    ];

    public function up(): void
    {
        foreach (self::PLACEHOLDERS as $column => $values) {
            DB::table('settings')->whereIn($column, $values)->update([$column => null]);
        }

        // Settings are cached forever (SettingsService); drop the stale copy.
        Cache::forget(SettingsService::CACHE_KEY);
    }

    public function down(): void
    {
        // Nothing to restore: the cleared values were placeholders, not data.
    }
};
