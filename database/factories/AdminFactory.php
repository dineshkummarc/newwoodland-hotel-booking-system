<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class AdminFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => Admin::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => Admin::ROLE_ADMIN]);
    }

    public function frontDesk(): static
    {
        return $this->state(['role' => Admin::ROLE_FRONT_DESK]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
