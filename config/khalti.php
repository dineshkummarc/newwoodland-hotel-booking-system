<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Khalti e-Payment (Web Checkout) Configuration
    |--------------------------------------------------------------------------
    |
    | Source of truth: https://docs.khalti.com/khalti-epayment/
    |
    | Sandbox: Khalti's docs print an example key
    | (KhaltiPaymentService::PUBLIC_SANDBOX_KEY) that dev.khalti.com accepts,
    | so it can be used for local testing like eSewa's EPAYTEST. Your own
    | sandbox key from test-admin.khalti.com works too.
    | Production: KHALTI_MODE=production with YOUR live_secret_key from
    | admin.khalti.com. The public example key is refused in production
    | mode. There is no default below; set KHALTI_SECRET_KEY in .env. See
    | config/payments.php for the KHALTI_ENABLED flag.
    |
    | Only the SECRET key is used: Khalti's current Web Checkout (KPG-2) is
    | entirely server-to-server (initiate + lookup), so there is no public
    | key and nothing Khalti-related is ever sent to the browser except the
    | payment_url Khalti itself returns.
    |
    */

    'mode' => env('KHALTI_MODE', 'sandbox'),

    'secret_key' => env('KHALTI_SECRET_KEY'),

    // Seconds to wait for Khalti's API before treating it as unreachable.
    'timeout' => 15,

    // Khalti rejects amounts below Rs. 10 (1000 paisa).
    'minimum_amount_paisa' => 1000,

    'urls' => [
        'sandbox' => [
            'base' => 'https://dev.khalti.com/api/v2/',
        ],
        'production' => [
            'base' => 'https://khalti.com/api/v2/',
        ],
    ],

];
