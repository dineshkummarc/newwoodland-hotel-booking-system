<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_phone', 'site_email', 'site_address', 'facebook_url',
        'instagram_url', 'youtube_url', 'tiktok_url', 'checkin_time', 'checkout_time',
    ];

    /**
     * Return the single active settings record.
     */
    public static function current(): self
    {
        return static::query()->orderBy('id')->firstOrNew();
    }
}
