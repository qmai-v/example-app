<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SwitchTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'string', 'uuid'],
        ];
    }

    public function tenantId(): string
    {
        return (string) $this->validated('tenant_id');
    }
}
