<?php

namespace App\Http\Requests\Tenant;

use App\Models\Enums\TenantMemberRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
        $isNewUser = fn (): bool => ! User::query()
            ->where('email', (string) $this->input('email'))
            ->exists();

        return [
            'name' => [Rule::requiredIf($isNewUser), 'nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [Rule::requiredIf($isNewUser), 'nullable', 'string', Password::default(), 'confirmed'],
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

    /**
     * @return array{name: ?string, email: string, password: ?string}
     */
    public function memberUserAttributes(): array
    {
        return [
            'name' => $this->validated('name'),
            'email' => $this->email(),
            'password' => $this->validated('password'),
        ];
    }
}
