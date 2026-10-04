<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    |
    | The single source of truth for which payment methods the booking
    | checkout offers. Only methods that are enabled here AND actually usable
    | (credentials / account details present — see each service's
    | isAvailable()) are shown to guests; anything else is not displayed at
    | all. Methods are listed at checkout in this order.
    |
    | Hotel payment policy (client-confirmed 2026-10-04): advance bookings
    | are paid 100% in advance (QR / online / bank transfer). The optional
    | "pay part now, rest at the hotel" plan (split_payment) is OFF by
    | default. A guest who pays at the front desk partly by QR and partly in
    | cash is recorded by staff, part by part, with the actual amounts
    | (Admin → Booking → Record Payment Received at the Hotel).
    |
    | Accepted by Newwoodland Hotel & Lounge:
    |   - eSewa            — shown once merchant credentials are set (.env)
    |   - Fonepay          — shown once merchant credentials are set (.env)
    |   - Bank Transfer    — Siddhartha Bank; shown once the account number
    |                        is entered in Admin → Settings
    | Not offered at checkout:
    |   - Full payment at the hotel ("cash") — the balance of a 50% booking
    |     is still recorded by staff at the hotel (Admin → Booking)
    |   - Khalti
    |
    */

    /*
    | Split payment: the guest may pay part of the booking now (online or by
    | bank transfer) and the rest at the hotel ("Pay at Hotel"). The booking is
    | confirmed once the first part is received; staff record the balance
    | on arrival (Admin → Booking → Record Cash Payment).
    */
    'split_payment' => [
        // Off: the hotel requires 100% advance payment (set true only if the hotel changes that policy).
        'enabled' => (bool) env('SPLIT_PAYMENT_ENABLED', false),
        'percent' => (int) env('SPLIT_PAYMENT_PERCENT', 50),
        'methods' => ['esewa', 'fonepay', 'bank_transfer'],
    ],

    'methods' => [
        'esewa' => [
            'label' => 'eSewa',
            'icon' => 'bi-wallet2',
            'enabled' => env('ESEWA_ENABLED', true),
            'description' => 'Pay securely online via eSewa. Your booking is confirmed automatically once payment is verified.',
        ],

        'fonepay' => [
            'label' => 'Fonepay',
            'icon' => 'bi-qr-code',
            'enabled' => env('FONEPAY_ENABLED', true),
            'description' => 'Pay securely online via Fonepay. Your booking is confirmed automatically once payment is verified.',
        ],

        'bank_transfer' => [
            'label' => 'Bank Transfer',
            'icon' => 'bi-bank',
            'enabled' => env('BANK_TRANSFER_ENABLED', true),
            'description' => 'Transfer the amount to the hotel\'s bank account. Your booking is confirmed once our team receives it.',
        ],

        // Paying 100% at the hotel is not a hotel payment option, so this
        // is deliberately not read from .env (like Khalti below). The code
        // is kept so earlier cash bookings still display and can be settled.
        'cash' => [
            'label' => 'Pay at Hotel',
            'icon' => 'bi-cash-coin',
            'enabled' => false,
            // How long a booking stays held after the guest chooses Cash,
            // giving staff time to confirm it (Admin → Booking → status).
            'hold_hours' => (int) env('CASH_HOLD_HOURS', 24),
            'description' => 'Pay the full amount at the hotel. Not offered — see the hotel payment policy above.',
        ],

        // The hotel does not accept Khalti. Deliberately not read from .env
        // so a stray KHALTI_ENABLED=true can't put it back on the checkout;
        // the integration code (KhaltiPaymentService) is kept intact in case
        // the hotel adopts Khalti later — flip this line then.
        'khalti' => [
            'label' => 'Khalti',
            'icon' => 'bi-phone',
            'enabled' => false,
            'description' => 'Khalti is not accepted by the hotel.',
        ],
    ],

];
