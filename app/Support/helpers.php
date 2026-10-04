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

if (! function_exists('percent')) {
    /**
     * Format a percentage without trailing zeros, e.g. 5.00 -> "5%", 7.5 -> "7.5%".
     */
    function percent(int|float|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%';
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

        // Staff-recorded payments taken in person (no checkout method of their own).
        $inPerson = ['hotel_qr' => 'QR at Hotel (eSewa/Fonepay)'];

        return $inPerson[$provider] ?? config("payments.methods.{$provider}.label", ucfirst($provider));
    }
}

if (! function_exists('site_photo_url')) {
    /**
     * Public URL of a client-provided photo on the public disk, or null when
     * the file has not been uploaded yet (the caller then shows a labelled
     * placeholder instead of a stand-in image).
     */
    function site_photo_url(?string $path): ?string
    {
        if (blank($path) || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
    }
}
