<?php

namespace App\View\Composers;

use App\Services\SettingsService;
use Illuminate\View\View;

class SettingsComposer
{
    public function __construct(protected SettingsService $settings) {}

    public function compose(View $view): void
    {
        $settings = $this->settings->current();

        $view->with([
            'hotelName' => $settings->hotel_name,
            'hotelDescription' => $settings->hotel_description,
            'hotelPhone' => $settings->site_phone,
            'hotelPhoneRaw' => $settings->site_phone ? '+'.preg_replace('/\D/', '', $settings->site_phone) : null,
            'hotelPhoneSecondary' => $settings->site_phone_secondary,
            'hotelPhoneSecondaryRaw' => $settings->site_phone_secondary ? '+'.preg_replace('/\D/', '', $settings->site_phone_secondary) : null,
            'hotelEmail' => $settings->site_email,
            'hotelAddress' => $settings->site_address,
            'facebookUrl' => $settings->facebook_url,
            'instagramUrl' => $settings->instagram_url,
            'youtubeUrl' => $settings->youtube_url,
            'tiktokUrl' => $settings->tiktok_url,
            'checkinTime' => $settings->checkin_time,
            'checkoutTime' => $settings->checkout_time,
            'cancellationPolicyText' => $settings->cancellation_policy_text,
            'bankName' => $settings->bank_name,
            'bankAccountName' => $settings->bank_account_name,
            'bankAccountNumber' => $settings->bank_account_number,
            'bankBranch' => $settings->bank_branch,
            'totalRooms' => $settings->total_rooms,
            'roomPriceMin' => $settings->room_price_min,
            'roomPriceMax' => $settings->room_price_max,
            'typicalDailyGuests' => $settings->typical_daily_guests,
            'guestSatisfaction' => $settings->guest_satisfaction,
            'firstBookingDiscount' => (float) $settings->first_booking_discount_percent,
            'cancellationDeductionPercent' => $settings->cancellation_deduction_percent,
        ]);
    }
}
