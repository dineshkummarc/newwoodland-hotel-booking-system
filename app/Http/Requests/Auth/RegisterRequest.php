<?php

namespace App\Http\Requests\Auth;

use App\Rules\PhoneNumber;
use App\Services\PhoneNumberService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_country' => ['nullable', 'string', Rule::in(app(PhoneNumberService::class)->supportedRegions())],
            'phone' => ['nullable', 'string', 'max:30', new PhoneNumber($this->input('phone_country'))],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * The optional contact phone normalized to E.164, or null if not given.
     * It is not trusted for account recovery until verified in My Account.
     */
    public function e164Phone(): ?string
    {
        return app(PhoneNumberService::class)->toE164($this->validated('phone'), $this->validated('phone_country'));
    }
}
