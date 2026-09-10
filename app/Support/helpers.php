<?php

if (! function_exists('npr')) {
    /**
     * Format an amount as Nepalese Rupees, e.g. "NPR 1,500".
     */
    function npr(int|float|string|null $amount): string
    {
        return 'NPR '.number_format((float) $amount, 0);
    }
}

if (! function_exists('payment_provider_label')) {
    /**
     * Human-readable display name for a Payment::provider value, e.g.
     * "esewa" -> "eSewa". Falls back to a capitalized version of the raw
     * value for any provider not listed in config/payments.php.
     */
    function payment_provider_label(?string $provider): string
    {
        if (! $provider) {
            return '—';
        }

        return config("payments.methods.{$provider}.label", ucfirst($provider));
    }
}
