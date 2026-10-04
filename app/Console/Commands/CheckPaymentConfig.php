<?php

namespace App\Console\Commands;

use App\Services\Payments\GatewayEnvironmentGuard;
use Illuminate\Console\Command;

/**
 * Pre-go-live / deploy check: lists every ENABLED payment gateway whose
 * configuration is unsafe for this environment (e.g. APP_ENV=production
 * with a sandbox mode or eSewa's public EPAYTEST credentials). Exits
 * non-zero when anything is wrong, so a deploy script can stop on it.
 * Never prints a credential value.
 */
class CheckPaymentConfig extends Command
{
    protected $signature = 'payments:check-config';

    protected $description = 'Check that enabled payment gateways are safely configured for this environment.';

    public function handle(GatewayEnvironmentGuard $guard): int
    {
        $this->line('Environment: '.app()->environment());
        $failed = false;

        foreach (['esewa', 'fonepay', 'khalti'] as $provider) {
            if (! config("payments.methods.{$provider}.enabled")) {
                $this->line("  {$provider}: disabled");

                continue;
            }

            $problems = $guard->problems($provider);

            if (app()->isProduction() || config("{$provider}.mode") === 'production') {
                // In production every enabled gateway must be fully configured.
            } elseif (! $this->hasCredentials($provider)) {
                $this->line("  {$provider}: enabled but not configured (hidden at checkout)");

                continue;
            }

            if ($problems === []) {
                $this->info("  {$provider}: OK (".config("{$provider}.mode").' mode)');

                continue;
            }

            $failed = true;
            $this->error("  {$provider}: UNSAFE — this method is switched off until fixed:");
            foreach ($problems as $problem) {
                $this->line("    - {$problem}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function hasCredentials(string $provider): bool
    {
        return match ($provider) {
            'esewa' => filled(config('esewa.product_code')) && filled(config('esewa.secret_key')),
            'fonepay' => filled(config('fonepay.merchant_code')) && filled(config('fonepay.secret_key')),
            'khalti' => filled(config('khalti.secret_key')),
            default => false,
        };
    }
}
