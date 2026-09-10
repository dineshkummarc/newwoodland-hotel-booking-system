<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'provider' => 'esewa',
            'transaction_uuid' => 'NW-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'reference_id' => null,
            'amount' => 3000,
            'currency' => 'NPR',
            'status' => Payment::STATUS_PENDING,
            'gateway_response' => null,
            'paid_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => Payment::STATUS_COMPLETED,
            'reference_id' => strtoupper(fake()->bothify('REF#######')),
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(['status' => Payment::STATUS_FAILED]);
    }
}
