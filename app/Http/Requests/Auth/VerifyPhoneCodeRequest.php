<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Tolerate "123 456" / "123-456" pasted from the SMS.
        $this->merge(['code' => preg_replace('/\D/', '', (string) $this->input('code'))]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Please enter the 6-digit code we sent you.',
            'code.digits' => 'The code must be exactly 6 digits.',
        ];
    }
}
