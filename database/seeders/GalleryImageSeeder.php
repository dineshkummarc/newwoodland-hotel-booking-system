<?php

namespace Database\Seeders;

use App\Models\GalleryImage;
use Illuminate\Database\Seeder;

class GalleryImageSeeder extends Seeder
{
    public function run(): void
    {
        $images = [
            ['title' => 'Deluxe Room', 'category' => 'rooms', 'path' => 'gallery/deluxe-room.jpg', 'sort_order' => 1],
            ['title' => 'Executive Room', 'category' => 'rooms', 'path' => 'gallery/executive-room.jpg', 'sort_order' => 2],
            ['title' => 'Family Suite', 'category' => 'rooms', 'path' => 'gallery/family-suite.jpg', 'sort_order' => 3],
            ['title' => 'Outdoor Swimming Pool', 'category' => 'pool', 'path' => 'gallery/pool.jpg', 'sort_order' => 4],
            ['title' => 'Restaurant & Bar', 'category' => 'restaurant', 'path' => 'gallery/restaurant.jpg', 'sort_order' => 5],
            ['title' => 'Hotel Garden', 'category' => 'garden', 'path' => 'gallery/garden.jpg', 'sort_order' => 6],
        ];

        foreach ($images as $image) {
            GalleryImage::updateOrCreate(['title' => $image['title']], $image);
        }
    }
}
