<?php

namespace App\Http\Requests\Tenant;

use App\Models\Enums\TenantMemberRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
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
            'role' => ['required', Rule::in([TenantMemberRole::TenantAdmin->value, TenantMemberRole::Member->value])],
        ];
    }

    public function memberRole(): TenantMemberRole
    {
        return TenantMemberRole::from((string) $this->validated('role'));
    }
}
