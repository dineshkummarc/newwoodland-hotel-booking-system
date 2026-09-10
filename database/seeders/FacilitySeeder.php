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
                'category' => 'wellness_recreation',
                'title' => 'Outdoor Swimming Pool',
                'description' => 'Relax beside our refreshing outdoor swimming pool surrounded by peaceful greenery.',
                'sort_order' => 1,
            ],
            [
                'icon' => 'bi-cup-hot-fill',
                'category' => 'dining',
                'title' => 'Restaurant & Bar',
                'description' => 'Enjoy delicious local Nepalese and international cuisine prepared by experienced chefs.',
                'sort_order' => 2,
            ],
            [
                'icon' => 'bi-house-heart-fill',
                'category' => 'guest_services',
                'title' => 'Luxury Rooms',
                'description' => 'Spacious rooms with elegant interiors, premium bedding and modern amenities.',
                'sort_order' => 3,
            ],
            [
                'icon' => 'bi-wifi',
                'category' => 'connectivity',
                'title' => 'Free High-Speed Wi-Fi',
                'description' => 'Complimentary wireless internet throughout the hotel for all guests.',
                'sort_order' => 4,
            ],
            [
                'icon' => 'bi-p-square-fill',
                'category' => 'transportation_parking',
                'title' => 'Free Safe Parking',
                'description' => 'Safe and spacious parking available for every hotel guest at no extra cost.',
                'sort_order' => 5,
            ],
            [
                'icon' => 'bi-airplane-fill',
                'category' => 'transportation_parking',
                'title' => 'Airport Transportation',
                'description' => 'Bhadrapur Airport is just minutes away, with taxis and local transport readily available for a smooth ride to the hotel.',
                'sort_order' => 6,
            ],
            [
                'icon' => 'bi-heart-pulse-fill',
                'category' => 'wellness_recreation',
                'title' => 'Fitness Center',
                'description' => 'Stay active during your stay with our on-site gym and fitness facilities.',
                'sort_order' => 7,
                'show_on_homepage' => false,
            ],
            [
                'icon' => 'bi-flower1',
                'category' => 'wellness_recreation',
                'title' => 'Garden',
                'description' => 'Unwind in our landscaped garden, a peaceful green space on the property.',
                'sort_order' => 8,
                'show_on_homepage' => false,
            ],
            [
                'icon' => 'bi-cup-straw',
                'category' => 'dining',
                'title' => 'Room Service',
                'description' => 'Enjoy meals and refreshments delivered directly to your room.',
                'sort_order' => 9,
                'show_on_homepage' => false,
            ],
            [
                'icon' => 'bi-clock-history',
                'category' => 'guest_services',
                'title' => '24-Hour Front Desk',
                'description' => 'Our front desk team is available around the clock to assist with anything you need.',
                'sort_order' => 10,
                'show_on_homepage' => false,
            ],
            [
                'icon' => 'bi-alarm-fill',
                'category' => 'guest_services',
                'title' => 'Wake-Up Service',
                'description' => 'Request a wake-up call from the front desk so you never miss an early start.',
                'sort_order' => 11,
                'show_on_homepage' => false,
            ],
            [
                'icon' => 'bi-telephone-fill',
                'category' => 'guest_services',
                'title' => 'Telephone',
                'description' => 'In-house telephone line for reaching the front desk and hotel services directly.',
                'sort_order' => 12,
                'show_on_homepage' => false,
            ],
            [
                'icon' => 'bi-snow',
                'category' => 'property_accessibility',
                'title' => 'Air Conditioning in Public Areas',
                'description' => 'Common areas throughout the hotel are climate-controlled for year-round comfort.',
                'sort_order' => 13,
                'show_on_homepage' => false,
            ],
            [
                'icon' => 'bi-arrow-up-square-fill',
                'category' => 'property_accessibility',
                'title' => 'Elevator',
                'description' => 'Easy access to every floor of the hotel.',
                'sort_order' => 14,
                'show_on_homepage' => false,
            ],
            [
                'icon' => 'bi-slash-circle',
                'category' => 'property_accessibility',
                'title' => 'Smoke-Free Property',
                'description' => 'Newwoodland Hotel and Lounge maintains smoke-free indoor spaces for the comfort of all guests.',
                'sort_order' => 15,
                'show_on_homepage' => false,
            ],
            [
                'icon' => 'bi-geo-alt-fill',
                'category' => 'property_accessibility',
                'title' => 'Designated Smoking Area',
                'description' => 'A designated outdoor smoking area is available on the property.',
                'sort_order' => 16,
                'show_on_homepage' => false,
            ],
            [
                'icon' => 'bi-brightness-high-fill',
                'category' => 'property_accessibility',
                'title' => 'Terrace',
                'description' => 'Relax outdoors on the hotel terrace.',
                'sort_order' => 17,
                'show_on_homepage' => false,
            ],
            [
                'icon' => 'bi-heart-fill',
                'category' => 'property_accessibility',
                'title' => 'Pets Allowed',
                'description' => 'We welcome guests traveling with pets — please contact us ahead of your stay for details.',
                'sort_order' => 18,
                'show_on_homepage' => false,
            ],
            [
                'icon' => 'bi-people-fill',
                'category' => 'property_accessibility',
                'title' => 'Family & Child Friendly',
                'description' => 'A welcoming property for guests traveling with children, including our Family Suite room.',
                'sort_order' => 19,
                'show_on_homepage' => false,
            ],
        ];

        foreach ($facilities as $facility) {
            Facility::updateOrCreate(['title' => $facility['title']], $facility);
        }
    }
}
