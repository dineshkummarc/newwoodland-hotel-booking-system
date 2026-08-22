<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoomFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement(['Deluxe', 'Executive', 'Family', 'Superior', 'Garden View']).' Room '.fake()->unique()->numberBetween(1, 1000000);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'short_description' => fake()->sentence(12),
            'description' => fake()->paragraph(),
            'price_per_night' => fake()->randomElement([1500, 2000, 2500, 3500]),
            'max_guests' => fake()->numberBetween(1, 6),
            'bed_type' => fake()->randomElement(['King Bed', 'Queen Bed', 'Two Queen Beds']),
            'size_sqm' => fake()->numberBetween(20, 60),
            'rating' => 0,
            'review_count' => 0,
            'badge_label' => null,
            'is_featured' => true,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
