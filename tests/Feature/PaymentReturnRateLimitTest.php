<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReturnRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_esewa_success_route_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get(route('payment.esewa.success'))->assertStatus(302);
        }

        $this->get(route('payment.esewa.success'))->assertStatus(429);
    }

    public function test_khalti_return_route_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get(route('payment.khalti.return'))->assertStatus(302);
        }

        $this->get(route('payment.khalti.return'))->assertStatus(429);
    }
}
