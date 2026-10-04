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
            'site_phone' => '+977 9852690590',
            'site_phone_secondary' => '+977 9801415167',
            'site_email' => 'woodlandhotel146@gmail.com',
            'site_address' => 'Bhadrapur-8, Jhapa, Nepal',
            // Social links are entered in Admin → Settings once the hotel
            // provides its real profiles — never seeded, so the footer shows
            // nothing rather than a generic placeholder link.
            'checkin_time' => '12:00',
            'checkout_time' => '12:00', // client-confirmed fixed check-out time
            // Free cancellation before arrival: no notice window.
            'cancellation_window_hours' => 0,
            'hotel_name' => 'Newwoodland Hotel & Lounge',
            'hotel_description' => 'Experience luxury, comfort, and authentic Nepalese hospitality at Newwoodland Hotel & Lounge in Bhadrapur-8, Jhapa, Nepal.',
            'cancellation_policy_text' => 'Cancellation before arrival is free. If a stay is cancelled after check-in, 50% of the booking amount is charged.',
            // Client-confirmed booking policies: 5% first-booking discount;
            // cancellation before arrival is free (0% deducted). The 50%
            // after-check-in charge is config bookings.after_arrival_cancellation_percent.
            'first_booking_discount_percent' => 5,
            'cancellation_deduction_percent' => 0,
            'guest_satisfaction' => '90%+',
            // Client-confirmed figures. Guest volume is an approximate
            // normal-day figure (weekends are busier; no number confirmed).
            'total_rooms' => 9,
            'room_price_min' => 1500,
            'room_price_max' => 3500,
            'typical_daily_guests' => '15–20',
            // The hotel banks with Siddhartha Bank. Account name/number,
            // branch and QR are entered in Admin → Settings once the hotel
            // confirms them — never seeded, so nothing fake is ever shown.
            'bank_name' => 'Siddhartha Bank',
        ])->save();
    }
}
