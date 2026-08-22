<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $setting = Setting::query()->orderBy('id')->first() ?? new Setting;

        $setting->fill([
            'site_phone' => '+977 9801415107',
            'site_email' => 'info@newwoodlandhotel.com',
            'site_address' => 'Bhadrapur-8, Jhapa, Nepal',
            'facebook_url' => 'https://facebook.com',
            'instagram_url' => 'https://instagram.com',
            'youtube_url' => 'https://youtube.com',
            'tiktok_url' => null,
            'checkin_time' => '12:00',
            'checkout_time' => '10:00',
        ])->save();
    }
}
