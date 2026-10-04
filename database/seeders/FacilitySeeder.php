<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * The hotel's facilities, as confirmed by the client, presented as six
 * grouped cards. Each description line is shown as one highlight. Re-running
 * updates them in place (matched by title) and removes the older
 * one-facility-per-card entries they replace, as well as facilities the
 * hotel confirmed it does NOT offer.
 */
class FacilitySeeder extends Seeder
{
    /**
     * Earlier single-facility entries now merged into the cards below, plus
     * facilities the hotel does not offer (airport transport, fitness
     * center, wake-up service, elevator).
     */
    protected const REPLACED = [
        'Outdoor Swimming Pool', 'Restaurant & Bar', 'Luxury Rooms', 'Free High-Speed Wi-Fi',
        'Free Safe Parking', 'Garden', 'Room Service', '24-Hour Front Desk', 'Telephone',
        'Air Conditioning in Public Areas', 'Smoke-Free Property', 'Designated Smoking Area',
        'Terrace', 'Pets Allowed', 'Family & Child Friendly',
        'Airport Transportation', 'Fitness Center', 'Wake-Up Service', 'Elevator',
    ];

    public function run(): void
    {
        $facilities = [
            ['bi-water', 'wellness_recreation', 'Swimming Pools & Garden', ["Adult and children's pools", 'Garden and terrace']],
            ['bi-cup-hot-fill', 'dining', 'Restaurant, Bar & Room Service', ['Nepalese and international cuisine', 'In-room dining']],
            ['bi-house-heart-fill', 'guest_services', 'Comfortable Rooms', ['Quality bedding and modern amenities', 'Air-conditioned common areas']],
            ['bi-wifi', 'connectivity', 'Free Wi-Fi & Parking', ['Free high-speed Wi-Fi', 'Free secure parking']],
            ['bi-clock-history', 'guest_services', '24-Hour Guest Services', ['24-hour front desk', 'In-house telephone']],
            ['bi-people-fill', 'property_accessibility', 'Family & Pet Friendly', ['Family Room available', 'Smoke-free indoors', 'Pets allowed outdoors only']],
        ];

        foreach ($facilities as $order => [$icon, $category, $title, $highlights]) {
            Facility::updateOrCreate(['title' => $title], [
                'icon' => $icon,
                'category' => $category,
                'description' => implode("\n", $highlights),
                'sort_order' => $order + 1,
                'show_on_homepage' => true,
            ]);
        }

        Facility::whereIn('title', self::REPLACED)->delete();

        // The public pages cache facilities; show the changes immediately.
        Cache::forget('facilities.page.data');
        Cache::forget('home.page.data');
    }
}
