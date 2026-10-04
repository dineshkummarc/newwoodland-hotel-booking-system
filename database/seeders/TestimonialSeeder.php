<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $testimonials = [
            [
                'name' => 'Syliv Shrestha',
                'location' => 'Lalitpur',
                'rating' => 5,
                'content' => 'Excellent hospitality and beautiful rooms. The staff were welcoming, the food was delicious, and the swimming pool was exceptionally clean. Highly recommended when visiting Bhadrapur.',
                'avatar_path' => null,
            ],
            [
                'name' => 'Alyra Shrestha',
                'location' => 'Kathmandu',
                'rating' => 5,
                'content' => 'One of the best hotels in Bhadrapur. Spacious rooms, peaceful surroundings, prompt service, and excellent customer service!',
                'avatar_path' => null,
            ],
            [
                'name' => 'Bikesh Dhakal',
                'location' => 'Jhapa',
                'rating' => 5,
                'content' => 'Wonderful stay with my family. Beautiful gardens, excellent restaurant options, and very comfortable family suite.',
                'avatar_path' => null,
            ],
        ];

        foreach ($testimonials as $testimonial) {
            Testimonial::updateOrCreate(['name' => $testimonial['name']], $testimonial);
        }
    }
}
