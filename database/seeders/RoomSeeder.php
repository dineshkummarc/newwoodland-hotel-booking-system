<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            [
                'name' => 'Deluxe Room',
                'slug' => 'deluxe-room',
                'short_description' => 'Elegant interiors, king-size bed, balcony, complimentary breakfast, and peaceful garden views.',
                'description' => 'Elegant interiors, king-size bed, balcony, complimentary breakfast, and peaceful garden views.',
                'price_per_night' => 1500,
                'max_guests' => 5,
                'bed_type' => 'King Bed',
                'size_sqm' => 35,
                'badge_label' => 'Best Seller',
                'is_featured' => true,
                'is_active' => true,
                'image' => 'rooms/deluxe-room.jpg',
                'amenities' => ['Free Wi-Fi', 'Breakfast Included'],
            ],
            [
                'name' => 'Executive Room',
                'slug' => 'executive-room',
                'short_description' => 'Spacious executive room with dedicated workspace, smart TV and premium amenities.',
                'description' => 'Spacious executive room with dedicated workspace, smart TV and premium amenities.',
                'price_per_night' => 2000,
                'max_guests' => 4,
                'bed_type' => 'Queen Bed',
                'size_sqm' => 35,
                'badge_label' => 'Luxury',
                'is_featured' => true,
                'is_active' => true,
                'image' => 'rooms/executive-room.jpg',
                'amenities' => ['Wi-Fi', 'Coffee Maker'],
            ],
            [
                'name' => 'Family Suite',
                'slug' => 'family-suite',
                'short_description' => 'Spacious family suite featuring two bedrooms, living area, bathtub, and premium comfort.',
                'description' => 'Spacious family suite featuring two bedrooms, living area, bathtub, and premium comfort.',
                'price_per_night' => 3500,
                'max_guests' => 5,
                'bed_type' => 'Two Queen Beds',
                'size_sqm' => 35,
                'badge_label' => 'Family Choice',
                'is_featured' => true,
                'is_active' => true,
                'image' => 'rooms/family-suite.jpg',
                'amenities' => ['Bathtub', 'Breakfast Included'],
            ],
        ];

        foreach ($rooms as $data) {
            $image = $data['image'];
            $amenities = $data['amenities'];
            unset($data['image'], $data['amenities']);

            $room = Room::updateOrCreate(['slug' => $data['slug']], $data);

            $room->images()->firstOrCreate(
                ['path' => $image],
                ['is_primary' => true, 'sort_order' => 0]
            );

            foreach ($amenities as $label) {
                $room->amenities()->firstOrCreate(['label' => $label], ['icon' => null]);
            }
        }
    }
}
