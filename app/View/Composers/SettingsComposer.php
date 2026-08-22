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
            'hotelPhone' => $settings->site_phone,
            'hotelPhoneRaw' => $settings->site_phone ? '+'.preg_replace('/\D/', '', $settings->site_phone) : null,
            'hotelEmail' => $settings->site_email,
            'hotelAddress' => $settings->site_address,
            'facebookUrl' => $settings->facebook_url,
            'instagramUrl' => $settings->instagram_url,
            'youtubeUrl' => $settings->youtube_url,
            'tiktokUrl' => $settings->tiktok_url,
            'checkinTime' => $settings->checkin_time,
            'checkoutTime' => $settings->checkout_time,
        ]);
    }
}
