<?php

namespace App\Contracts;

use App\Models\Booking;

/**
 * The minimal contract a real payment gateway service must satisfy.
 *
 * Deliberately small: providers' actual callback/verification shapes differ
 * too much (eSewa's base64 "data" param vs Khalti's pidx + lookup call) to
 * force a shared handleReturn()/verify() signature without it being
 * artificial. Each service keeps its own provider-specific verification
 * logic and controller methods — this contract only standardizes the two
 * things every gateway genuinely has in common.
 */
interface PaymentGatewayInterface
{
    /**
     * Whether this gateway is currently configured and enabled for use on
     * the checkout page (see config/payments.php).
     */
    public function isAvailable(): bool;

    /**
     * Start a payment for this booking. Returns whatever the checkout view
     * needs to send the customer to the gateway — e.g. an action URL plus
     * signed form fields for a POST-redirect flow, or a direct payment URL
     * for a gateway that returns one from its own initiate API.
     *
     * @return array<string, mixed>
     */
    public function initiate(Booking $booking): array;
}
