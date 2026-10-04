<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hotel_name' => ['nullable', 'string', 'max:255'],
            'hotel_description' => ['nullable', 'string', 'max:2000'],
            'site_phone' => ['required', 'string', 'max:50'],
            'site_phone_secondary' => ['nullable', 'string', 'max:50'],
            'site_email' => ['required', 'email', 'max:255'],
            'site_address' => ['required', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'tiktok_url' => ['nullable', 'url', 'max:255'],
            'checkin_time' => ['required', 'date_format:H:i'],
            'checkout_time' => ['required', 'date_format:H:i'],
            'cancellation_window_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'cancellation_policy_text' => ['nullable', 'string', 'max:2000'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_qr' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'pan_vat_number' => ['nullable', 'string', 'max:50'],
            'remove_bank_qr' => ['nullable', 'boolean'],
            'total_rooms' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'room_price_min' => ['nullable', 'numeric', 'min:0'],
            'room_price_max' => ['nullable', 'numeric', 'min:0', 'gte:room_price_min'],
            'typical_daily_guests' => ['nullable', 'string', 'max:50'],
            'guest_satisfaction' => ['nullable', 'string', 'max:50'],
            'first_booking_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cancellation_deduction_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_price_max.gte' => 'The highest room price must be at least the lowest room price.',
        ];
    }
}
