<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Driver
    |--------------------------------------------------------------------------
    |
    | Which App\Contracts\SmsSenderInterface implementation is bound (see
    | AppServiceProvider). Supported: "log" (local development — writes the
    | message to the log instead of sending it), "array" (tests — keeps
    | messages in memory), "sparrow" (Sparrow SMS, Nepal numbers only).
    | Production must use a real driver: when APP_ENV=production the log
    | driver omits message bodies (i.e. the OTP) and fails every send.
    | See docs/SMS.md for provider setup.
    |
    */

    'driver' => env('SMS_DRIVER', 'log'),

    // Log channel used by the "log" driver (null = the default channel).
    'log_channel' => env('SMS_LOG_CHANNEL'),

    // ISO 3166-1 alpha-2 region assumed when a number is typed without a
    // "+<country code>" prefix and no country was chosen.
    'default_region' => env('SMS_DEFAULT_REGION', 'NP'),

    /*
    |--------------------------------------------------------------------------
    | Sparrow SMS (https://docs.sparrowsms.com/)
    |--------------------------------------------------------------------------
    |
    | No defaults for token/from on purpose: leave them unset until real
    | credentials exist, and never commit them.
    |
    */

    'sparrow' => [
        'token' => env('SPARROW_SMS_TOKEN'),
        'from' => env('SPARROW_SMS_FROM'),
        'url' => env('SPARROW_SMS_URL', 'https://api.sparrowsms.com/v2/sms/'),
        'timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | One-time passcodes
    |--------------------------------------------------------------------------
    |
    | Security limits for phone OTPs. These are deliberately not
    | env-configurable so a stray .env value can't weaken them.
    |
    */

    'otp' => [
        'ttl_minutes' => 5,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
        'max_sends_per_hour' => 5,
        // How long a successfully verified password-reset code may be
        // used to actually set the new password.
        'reset_window_minutes' => 10,
    ],

];
