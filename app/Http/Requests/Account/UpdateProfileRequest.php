<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only the customer's own name and email are editable here. The phone has
 * its own verified flow (My Account → Phone Number); bookings, verification
 * timestamps and anything else on the account are never mass-assigned.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            // The email is the login and the password-reset channel, so
            // changing it requires the current password, not just a session.
            'current_password' => [
                Rule::requiredIf(fn () => strcasecmp(trim((string) $this->input('email')), $this->user()->email) !== 0),
                'nullable',
                'current_password',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Please enter your current password to change your email address.',
            'current_password.current_password' => 'Your current password is incorrect.',
        ];
    }
}
