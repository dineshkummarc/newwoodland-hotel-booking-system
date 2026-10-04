<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $adminId = $this->route('adminUser')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($adminId)],
            'role' => ['required', Rule::in(array_keys(Admin::ROLES))],
            // Optional: only reset the password when staff actually fills this in.
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
