<?php

namespace App\Http\Requests\Admin;

use App\Models\Enums\TenantMemberRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantMemberRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in([TenantMemberRole::TenantAdmin->value, TenantMemberRole::Member->value])],
        ];
    }

    public function memberRole(): TenantMemberRole
    {
        return TenantMemberRole::from((string) $this->validated('role'));
    }
}
