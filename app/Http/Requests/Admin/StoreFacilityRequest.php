<?php

namespace App\Http\Requests\Admin;

use App\Models\Facility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'icon' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', Rule::in(array_keys(Facility::CATEGORIES))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'show_on_homepage' => ['nullable', 'boolean'],
        ];
    }
}
