<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookingFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+1 day', '+30 days');
        $checkOut = (clone $checkIn)->modify('+'.fake()->numberBetween(1, 5).' days');
        $nights = $checkIn->diff($checkOut)->days;
        $nightlyRate = fake()->randomElement([1500, 2000, 3500]);

        return [
            'confirmation_code' => 'NW-'.now()->format('Y').'-'.Str::upper(Str::random(6)),
            'room_id' => Room::factory(),
            'check_in' => $checkIn->format('Y-m-d'),
            'check_out' => $checkOut->format('Y-m-d'),
            'nights' => $nights,
            'guests_label' => fake()->randomElement(['1 Adult', '2 Adults', 'Family']),
            'customer_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('98########'),
            'country' => fake()->country(),
            'special_requests' => null,
            'nightly_rate' => $nightlyRate,
            'total_price' => $nightlyRate * $nights,
            'status' => Booking::STATUS_PENDING,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => Booking::STATUS_CONFIRMED]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => Booking::STATUS_CANCELLED]);
    }
}
