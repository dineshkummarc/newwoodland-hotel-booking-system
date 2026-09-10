<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_phone' => '+977 9801415107',
            'site_email' => 'info@newwoodlandhotel.com',
            'site_address' => 'Bhadrapur-8, Jhapa, Nepal',
            'facebook_url' => 'https://facebook.com',
            'instagram_url' => 'https://instagram.com',
            'youtube_url' => 'https://youtube.com',
            'tiktok_url' => null,
            'checkin_time' => '12:00',
            'checkout_time' => '10:00',
            'cancellation_window_hours' => 24,
        ];
    }
}
