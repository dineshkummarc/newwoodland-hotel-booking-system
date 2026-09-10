<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Khalti e-Payment (Web Checkout) Configuration
    |--------------------------------------------------------------------------
    |
    | Source of truth: https://docs.khalti.com/khalti-epayment/
    |
    | Unlike eSewa's EPAYTEST, Khalti has no publicly shared sandbox secret
    | key — every merchant (including for sandbox testing) must sign up at
    | test-admin.khalti.com and use the live_secret_key issued there. There
    | is intentionally no default value below: leave KHALTI_SECRET_KEY unset
    | until a real sandbox or production key exists. See config/payments.php
    | for the KHALTI_ENABLED flag that actually turns this on in the
    | checkout UI.
    |
    */

    'mode' => env('KHALTI_MODE', 'sandbox'),

    'secret_key' => env('KHALTI_SECRET_KEY'),

    'urls' => [
        'sandbox' => [
            'base' => 'https://dev.khalti.com/api/v2/',
        ],
        'production' => [
            'base' => 'https://khalti.com/api/v2/',
        ],
    ],

];
