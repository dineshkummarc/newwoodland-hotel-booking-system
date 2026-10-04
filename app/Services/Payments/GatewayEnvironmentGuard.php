<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Log;

/**
 * Makes "the live site is still pointed at a payment sandbox" impossible
 * to miss. On a production deployment (APP_ENV=production) a gateway is
 * only usable when it is EXPLICITLY configured for production with real,
 * non-public credentials; otherwise it is switched off — hidden at
 * checkout, refused if posted to directly, and its returns/reconciliation
 * never confirm anything. Outside production, sandbox use is normal, but
 * the publicly published test credentials are still refused in
 * production MODE.
 *
 * Problems are reported as fixed descriptions that never contain a
 * credential. `php artisan payments:check-config` lists them before go-live.
 */
class GatewayEnvironmentGuard
{
    /** eSewa's published UAT merchant code / secret (developer.esewa.com.np). */
    public const ESEWA_PUBLIC_MERCHANT_CODE = 'EPAYTEST';

    public const ESEWA_PUBLIC_SECRET = '8gBm/:&EnhH.1/q';

    /** The example key printed in Khalti's public documentation. */
    public const KHALTI_PUBLIC_KEY = 'live_secret_key_68791341fdd94846a146f0457ff7b455';

    /** @var array<string, bool> problems already logged in this process */
    protected static array $logged = [];

    /**
     * Configuration problems that make $provider unsafe to use here.
     *
     * @return list<string>
     */
    public function problems(string $provider): array
    {
        $production = app()->isProduction();

        $problems = match ($provider) {
            'esewa' => $this->check(
                $production,
                (string) config('esewa.mode'),
                [
                    'merchant code (ESEWA_MERCHANT_CODE)' => config('esewa.product_code'),
                    'secret key (ESEWA_SECRET_KEY)' => config('esewa.secret_key'),
                ],
                [
                    'ESEWA_MERCHANT_CODE is eSewa\'s public test merchant code (EPAYTEST)' => config('esewa.product_code') === self::ESEWA_PUBLIC_MERCHANT_CODE,
                    'ESEWA_SECRET_KEY is eSewa\'s public test secret key' => config('esewa.secret_key') === self::ESEWA_PUBLIC_SECRET,
                ],
                [config('esewa.urls.production.form'), config('esewa.urls.production.status')],
                'ESEWA_MODE',
            ),
            'fonepay' => $this->check(
                $production,
                (string) config('fonepay.mode'),
                [
                    'merchant code (FONEPAY_MERCHANT_CODE)' => config('fonepay.merchant_code'),
                    'secret key (FONEPAY_SECRET_KEY)' => config('fonepay.secret_key'),
                ],
                [],
                [config('fonepay.urls.production.base')],
                'FONEPAY_MODE',
            ),
            'khalti' => $this->check(
                $production,
                (string) config('khalti.mode'),
                ['secret key (KHALTI_SECRET_KEY)' => config('khalti.secret_key')],
                ['KHALTI_SECRET_KEY is the example key from Khalti\'s public documentation' => config('khalti.secret_key') === self::KHALTI_PUBLIC_KEY],
                [config('khalti.urls.production.base')],
                'KHALTI_MODE',
            ),
            default => [],
        };

        return $problems;
    }

    public function isSafe(string $provider): bool
    {
        $problems = $this->problems($provider);

        if ($problems !== [] && ! isset(static::$logged[$provider])) {
            static::$logged[$provider] = true;

            // Descriptions only — never a credential value.
            Log::error("Payment gateway [{$provider}] is disabled: unsafe configuration.", ['problems' => $problems]);
        }

        return $problems === [];
    }

    /**
     * @param  array<string, mixed>  $required  label => value that must be filled
     * @param  array<string, bool>  $publicCredentials  description => whether a public test credential is in use
     * @param  list<mixed>  $productionUrls
     * @return list<string>
     */
    protected function check(bool $production, string $mode, array $required, array $publicCredentials, array $productionUrls, string $modeVariable): array
    {
        $problems = [];

        if ($production && $mode !== 'production') {
            $problems[] = "APP_ENV=production but {$modeVariable} is \"{$mode}\" — set {$modeVariable}=production with live credentials (sandbox payments must never confirm real bookings).";
        }

        if (! in_array($mode, ['sandbox', 'production'], true)) {
            $problems[] = "{$modeVariable} must be \"sandbox\" or \"production\".";
        }

        if ($mode === 'production' || $production) {
            // (A sandbox gateway without credentials is simply "not set up"
            // in development — isAvailable() hides it without an error.)
            foreach ($required as $label => $value) {
                if (! filled($value)) {
                    $problems[] = "The {$label} is not set.";
                }
            }

            foreach ($publicCredentials as $description => $inUse) {
                if ($inUse) {
                    $problems[] = $description.' — it only works in the sandbox. Use your own live credentials.';
                }
            }

            foreach ($productionUrls as $url) {
                if (! is_string($url) || ! str_starts_with($url, 'https://')) {
                    $problems[] = 'A production gateway URL is missing or not HTTPS.';
                    break;
                }
            }
        }

        return $problems;
    }

    /** For tests: forget which problems were already logged. */
    public static function resetLogged(): void
    {
        static::$logged = [];
    }
}
