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
