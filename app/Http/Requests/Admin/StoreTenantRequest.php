<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'min:2', 'max:80', 'regex:/^[a-z0-9](-?[a-z0-9])*$/', 'unique:tenants,slug'],
            'initial_tenant_admin_user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
