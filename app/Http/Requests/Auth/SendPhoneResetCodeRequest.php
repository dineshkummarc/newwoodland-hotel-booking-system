<?php

namespace App\Http\Requests\Auth;

use App\Rules\PhoneNumber;
use App\Services\PhoneNumberService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendPhoneResetCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_country' => ['required', 'string', Rule::in(app(PhoneNumberService::class)->supportedRegions())],
            'phone' => ['required', 'string', 'max:30', new PhoneNumber($this->input('phone_country'), mustReceiveSms: true)],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_country.required' => 'Please choose your country code.',
            'phone_country.in' => 'Please choose a valid country code.',
        ];
    }

    /**
     * The validated number in E.164 form, e.g. "+9779812345678".
     */
    public function e164(): string
    {
        return app(PhoneNumberService::class)->toE164($this->validated('phone'), $this->validated('phone_country'));
    }
}
