<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fonepay Web Payment (redirect) Configuration
    |--------------------------------------------------------------------------
    |
    | Source of truth: Fonepay's own published integration code —
    | github.com/fonepay/Fonepay-Java (request/verification URLs and the DV
    | signing strings) and github.com/fonepay/Fonepay-Spring-Boot-Integration-Sample
    | (dev/live hosts and the return-URL handling). See docs/PAYMENTS.md.
    |
    | Fonepay issues a merchant code (PID) and a secret key per merchant, on
    | the dev merchant portal (dev-merchant-login.fonepay.com) for testing and
    | the live portal (login.fonepay.com) for production. There is
    | intentionally no default for either value: leave them unset until real
    | credentials exist. See config/payments.php for the FONEPAY_ENABLED flag
    | that actually turns this on in the checkout UI.
    |
    */

    'mode' => env('FONEPAY_MODE', 'sandbox'),

    'merchant_code' => env('FONEPAY_MERCHANT_CODE'),

    'secret_key' => env('FONEPAY_SECRET_KEY'),

    // Fonepay's CRN field. Its own sample library defaults this to "NP"
    // while most integration guides use "NPR" — it is configurable so it can
    // be matched to whatever your merchant account is set up for.
    'currency' => env('FONEPAY_CURRENCY', 'NPR'),

    'urls' => [
        'sandbox' => [
            'base' => 'https://dev-clientapi.fonepay.com',
        ],
        'production' => [
            'base' => 'https://clientapi.fonepay.com',
        ],
    ],

];
