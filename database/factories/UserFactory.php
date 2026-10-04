<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('98########'),
            'password' => Hash::make('password'),
        ];
    }

    /**
     * A user who has proven ownership of $e164 via SMS code.
     */
    public function withVerifiedPhone(string $e164 = '+9779812345678'): static
    {
        return $this->state(fn () => [
            'phone' => $e164,
            'phone_e164' => $e164,
            'phone_verified_at' => now(),
        ]);
    }
}
