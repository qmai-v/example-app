<?php

namespace App\Http\Requests\Tenant;

use App\Models\Enums\TenantMemberRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'role' => ['nullable', Rule::in([TenantMemberRole::TenantAdmin->value, TenantMemberRole::Member->value])],
        ];
    }

    public function memberRole(): TenantMemberRole
    {
        $value = $this->validated('role') ?? TenantMemberRole::Member->value;

        return TenantMemberRole::from((string) $value);
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }
}
