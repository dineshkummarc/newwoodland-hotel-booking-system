<?php

return [

    /*
    |--------------------------------------------------------------------------
    | eSewa ePay v2 Configuration
    |--------------------------------------------------------------------------
    |
    | "mode" switches between eSewa's UAT/sandbox environment and the live
    | production environment. The sandbox product_code/secret below are
    | eSewa's own publicly documented test credentials (developer.esewa.com.np)
    | — they are not secret and are safe to keep here as defaults. They must
    | be replaced with real merchant credentials (from eSewa's merchant
    | portal) via the .env file before accepting real payments.
    |
    */

    'mode' => env('ESEWA_MODE', 'sandbox'),

    // ESEWA_MERCHANT_CODE is the current/preferred name for this value.
    // ESEWA_PRODUCT_CODE is kept as a fallback so an existing .env from
    // before this rename keeps working without any manual edit.
    'product_code' => env('ESEWA_MERCHANT_CODE', env('ESEWA_PRODUCT_CODE', 'EPAYTEST')),

    'secret_key' => env('ESEWA_SECRET_KEY', '8gBm/:&EnhH.1/q'),

    // An attempt eSewa still reports as NOT_FOUND after this many minutes
    // is closed as failed by reconciliation (the guest can no longer be on
    // eSewa's checkout page). Until then it stays pending and is re-checked.
    'abandon_after_minutes' => 180,

    'urls' => [
        'sandbox' => [
            'form' => 'https://rc-epay.esewa.com.np/api/epay/main/v2/form',
            'status' => 'https://rc.esewa.com.np/api/epay/transaction/status/',
        ],
        'production' => [
            'form' => env('ESEWA_PRODUCTION_FORM_URL', 'https://epay.esewa.com.np/api/epay/main/v2/form'),
            'status' => env('ESEWA_PRODUCTION_STATUS_URL', 'https://epay.esewa.com.np/api/epay/transaction/status/'),
        ],
    ],

];
