<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Booking;
use App\Services\Concerns\RecordsOfflinePaymentIntent;

/**
 * Cash, paid in person at the hotel. Like Bank Transfer there is nothing to
 * verify online: initiate() only records that the guest chose Cash (holding
 * the booking for config('payments.methods.cash.hold_hours')). Staff then
 * confirm the booking from the admin and record the cash on arrival
 * (Admin → Booking → "Record Payment Received at the Hotel"), which completes this same row.
 */
class CashPaymentService implements PaymentGatewayInterface
{
    use RecordsOfflinePaymentIntent;

    public function isAvailable(): bool
    {
        return (bool) config('payments.methods.cash.enabled');
    }

    /**
     * @return array{payment: \App\Models\Payment, created: bool}
     *
     * @throws \App\Services\Payments\HoldExpiredException if the booking's hold has lapsed
     * @throws \RuntimeException if the booking is already paid
     */
    public function initiate(Booking $booking): array
    {
        return $this->recordPendingIntent($booking, 'cash', 'CASH');
    }
}
