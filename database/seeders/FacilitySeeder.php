<?php

namespace Database\Seeders;

use App\Models\Facility;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
{
    public function run(): void
    {
        $facilities = [
            [
                'icon' => 'bi-water',
                'title' => 'Outdoor Swimming Pool',
                'description' => 'Relax beside our refreshing outdoor swimming pool surrounded by peaceful greenery.',
                'sort_order' => 1,
            ],
            [
                'icon' => 'bi-cup-hot-fill',
                'title' => 'Restaurant & Bar',
                'description' => 'Enjoy delicious local Nepalese and international cuisine prepared by experienced chefs.',
                'sort_order' => 2,
            ],
            [
                'icon' => 'bi-house-heart-fill',
                'title' => 'Luxury Rooms',
                'description' => 'Spacious rooms with elegant interiors, premium bedding and modern amenities.',
                'sort_order' => 3,
            ],
            [
                'icon' => 'bi-wifi',
                'title' => 'Free High-Speed Wi-Fi',
                'description' => 'Complimentary wireless internet throughout the hotel for all guests.',
                'sort_order' => 4,
            ],
            [
                'icon' => 'bi-p-square-fill',
                'title' => 'Free Safe Parking',
                'description' => 'Safe and spacious parking available for every hotel guest at no extra cost.',
                'sort_order' => 5,
            ],
            [
                'icon' => 'bi-airplane-fill',
                'title' => 'Airport Transfer',
                'description' => 'Convenient airport shuttle service available upon request to Bhadrapur Airport.',
                'sort_order' => 6,
            ],
        ];

        foreach ($facilities as $facility) {
            Facility::updateOrCreate(['title' => $facility['title']], $facility);
        }
    }
}
