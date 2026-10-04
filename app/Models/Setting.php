<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_phone', 'site_phone_secondary', 'site_email', 'site_address', 'facebook_url',
        'instagram_url', 'youtube_url', 'tiktok_url', 'checkin_time', 'checkout_time',
        'cancellation_window_hours', 'hotel_name', 'hotel_description', 'cancellation_policy_text',
        'bank_name', 'bank_account_name', 'bank_account_number', 'bank_branch', 'bank_qr_path', 'pan_vat_number',
        'total_rooms', 'room_price_min', 'room_price_max', 'typical_daily_guests',
        'first_booking_discount_percent', 'cancellation_deduction_percent', 'guest_satisfaction',
    ];

    protected function casts(): array
    {
        return [
            'first_booking_discount_percent' => 'decimal:2',
            'room_price_min' => 'decimal:2',
            'room_price_max' => 'decimal:2',
        ];
    }

    /**
     * Return the single active settings record.
     */
    public static function current(): self
    {
        return static::query()->orderBy('id')->firstOrNew();
    }
}
