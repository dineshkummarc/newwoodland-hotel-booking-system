<?php

namespace App\Services;

use Giggsey\Locale\Locale;
use Illuminate\Support\Facades\Cache;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * International phone-number parsing/normalization, backed by Google's
 * libphonenumber metadata. Everything the app stores or compares for
 * authentication uses the E.164 form this service returns
 * (e.g. "+9779812345678").
 */
class PhoneNumberService
{
    protected PhoneNumberUtil $util;

    public function __construct()
    {
        $this->util = PhoneNumberUtil::getInstance();
    }

    public function defaultRegion(): string
    {
        return strtoupper(config('sms.default_region', 'NP'));
    }

    /**
     * Parse user input into a valid number. $region is only used when the
     * input has no "+<country code>" prefix. Returns null when the input
     * cannot be a real, dialable number.
     */
    public function parse(?string $number, ?string $region = null): ?PhoneNumber
    {
        if (! filled($number) || mb_strlen($number) > 50) {
            return null;
        }

        try {
            $parsed = $this->util->parse($number, strtoupper($region ?: $this->defaultRegion()));
        } catch (NumberParseException) {
            return null;
        }

        return $this->util->isValidNumber($parsed) ? $parsed : null;
    }

    public function toE164(?string $number, ?string $region = null): ?string
    {
        $parsed = $this->parse($number, $region);

        return $parsed ? $this->util->format($parsed, PhoneNumberFormat::E164) : null;
    }

    /**
     * Whether the number is of a type that can receive SMS. Some regions
     * (e.g. the US) don't distinguish fixed-line from mobile, which
     * libphonenumber reports as FIXED_LINE_OR_MOBILE — allowed here.
     */
    public function isSmsCapable(?string $number, ?string $region = null): bool
    {
        $parsed = $this->parse($number, $region);

        return $parsed !== null && in_array($this->util->getNumberType($parsed), [
            PhoneNumberType::MOBILE,
            PhoneNumberType::FIXED_LINE_OR_MOBILE,
        ], true);
    }

    public function regionFor(string $e164): ?string
    {
        $parsed = $this->parse($e164);

        return $parsed ? $this->util->getRegionCodeForNumber($parsed) : null;
    }

    /**
     * The number without its country code, e.g. "+9779812345678" -> "9812345678".
     */
    public function nationalNumber(string $e164): string
    {
        $parsed = $this->parse($e164);

        return $parsed ? $this->util->getNationalSignificantNumber($parsed) : ltrim($e164, '+');
    }

    public function formatInternational(string $e164): string
    {
        $parsed = $this->parse($e164);

        return $parsed ? $this->util->format($parsed, PhoneNumberFormat::INTERNATIONAL) : $e164;
    }

    /**
     * Partially hidden form for display/logging, e.g. "+977 98•••••678".
     */
    public function mask(string $e164): string
    {
        $parsed = $this->parse($e164);

        if (! $parsed) {
            return str_repeat('•', max(strlen($e164) - 3, 0)).substr($e164, -3);
        }

        $national = $this->util->getNationalSignificantNumber($parsed);
        $visibleStart = min(2, max(strlen($national) - 3, 0));
        $hidden = max(strlen($national) - $visibleStart - 3, 0);

        return '+'.$parsed->getCountryCode().' '
            .substr($national, 0, $visibleStart)
            .str_repeat('•', $hidden)
            .substr($national, $visibleStart + $hidden);
    }

    /**
     * @return array<int, string> ISO region codes libphonenumber supports
     */
    public function supportedRegions(): array
    {
        return $this->util->getSupportedRegions();
    }

    /**
     * Options for the country-code <select>: region => "Nepal (+977)",
     * sorted by name with the default region first.
     *
     * @return array<string, string>
     */
    public function countryOptions(): array
    {
        return Cache::rememberForever('phone.country-options.'.$this->defaultRegion(), function () {
            $options = [];

            foreach ($this->supportedRegions() as $region) {
                $name = Locale::getDisplayRegion('-'.$region, 'en') ?: $region;
                $options[$region] = $name.' (+'.$this->util->getCountryCodeForRegion($region).')';
            }

            asort($options, SORT_NATURAL | SORT_FLAG_CASE);

            $default = $this->defaultRegion();

            if (isset($options[$default])) {
                $options = [$default => $options[$default]] + $options;
            }

            return $options;
        });
    }
}
