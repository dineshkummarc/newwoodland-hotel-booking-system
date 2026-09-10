<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------
    |
    | The single source of truth for which payment methods the booking
    | checkout offers and whether each is actually wired up to a real
    | provider yet. "enabled" methods must have a real integration behind
    | them (currently only eSewa does — see EsewaPaymentService). A method
    | left disabled here shows as a professional "coming soon" option on
    | the payment page instead of a broken/fake payment flow.
    |
    | To activate a new provider once the client supplies real credentials:
    | build its service/routes (mirroring EsewaPaymentService), add its
    | config values below, and flip its *_ENABLED env var to true.
    |
    */

    'methods' => [
        'esewa' => [
            'label' => 'eSewa',
            'icon' => 'bi-wallet2',
            'enabled' => env('ESEWA_ENABLED', true),
            'description' => 'Pay securely online via eSewa. Your booking is confirmed automatically once payment is verified.',
        ],

        'khalti' => [
            'label' => 'Khalti',
            'icon' => 'bi-phone',
            'enabled' => env('KHALTI_ENABLED', false),
            'description' => 'Khalti payment is currently unavailable. Please choose another payment method or contact the hotel.',
        ],

        'fonepay' => [
            'label' => 'Fonepay',
            'icon' => 'bi-qr-code',
            'enabled' => env('FONEPAY_ENABLED', false),
            'description' => 'Fonepay payment is currently unavailable. Please choose another payment method or contact the hotel.',
        ],

        'bank_transfer' => [
            'label' => 'Bank Transfer',
            'icon' => 'bi-bank',
            'enabled' => env('BANK_TRANSFER_ENABLED', false),
            'description' => 'Bank transfer details are currently unavailable. Please contact the hotel for payment instructions.',
        ],
    ],

];
