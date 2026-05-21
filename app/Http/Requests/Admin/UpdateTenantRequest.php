<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
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
        $tenantId = $this->route('tenant')?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'min:2',
                'max:80',
                'regex:/^[a-z0-9](-?[a-z0-9])*$/',
                Rule::unique('tenants', 'slug')->ignore($tenantId),
            ],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
        ];
    }
}
