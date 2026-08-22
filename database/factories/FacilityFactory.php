<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FacilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'icon' => 'bi-star-fill',
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'sort_order' => 0,
            'show_on_homepage' => true,
        ];
    }
}
