<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => [
                'required',
                Rule::exists('rooms', 'id')->where('is_active', true),
            ],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests_label' => ['required', 'string', 'max:50'],
            'customer_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'country' => ['nullable', 'string', 'max:100'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.exists' => 'The selected room is unavailable.',
            'check_in.after_or_equal' => 'Check-in date cannot be in the past.',
            'check_out.after' => 'Check-out date must be after the check-in date.',
            'phone.regex' => 'Please enter a valid phone number.',
        ];
    }

    public function room(): Room
    {
        return Room::findOrFail($this->validated('room_id'));
    }
}
