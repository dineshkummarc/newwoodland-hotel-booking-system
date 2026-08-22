<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public const CACHE_KEY = 'site.settings';

    public function current(): Setting
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->orderBy('id')->first() ?? new Setting);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
