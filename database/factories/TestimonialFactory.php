<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TestimonialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'location' => fake()->city(),
            'rating' => 5,
            'content' => fake()->paragraph(),
            'avatar_path' => null,
        ];
    }
}
