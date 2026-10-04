<?php

namespace App\Http\Requests\Account;

use App\Rules\PhoneNumber;
use App\Services\PhoneNumberService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendPhoneVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'phone_country' => ['required', 'string', Rule::in(app(PhoneNumberService::class)->supportedRegions())],
            'phone' => ['required', 'string', 'max:30', new PhoneNumber($this->input('phone_country'), mustReceiveSms: true)],
            // The verified phone can reset the password, so changing it
            // requires the current password (not just a live session).
            'current_password' => ['required', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_country.required' => 'Please choose your country code.',
            'phone_country.in' => 'Please choose a valid country code.',
            'current_password.current_password' => 'Your current password is incorrect.',
        ];
    }

    public function e164(): string
    {
        return app(PhoneNumberService::class)->toE164($this->validated('phone'), $this->validated('phone_country'));
    }
}
