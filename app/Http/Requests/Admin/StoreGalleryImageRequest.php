<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGalleryImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $imageRule = $this->isMethod('POST')
            ? ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']
            : ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::in(['rooms', 'pool', 'restaurant', 'garden'])],
            'image' => $imageRule,
        ];
    }
}
