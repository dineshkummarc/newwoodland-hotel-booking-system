<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_phone' => '+977 9852690590',
            'site_phone_secondary' => '+977 9801415167',
            'site_email' => 'woodlandhotel146@gmail.com',
            'site_address' => 'Bhadrapur-8, Jhapa, Nepal',
            'facebook_url' => 'https://facebook.com',
            'instagram_url' => 'https://instagram.com',
            'youtube_url' => 'https://youtube.com',
            'tiktok_url' => null,
            'checkin_time' => '12:00',
            'checkout_time' => '12:00', // client-confirmed fixed check-out time
            'cancellation_window_hours' => 0,
            'hotel_name' => 'Newwoodland Hotel & Lounge',
        ];
    }
}
