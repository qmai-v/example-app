<?php

namespace Database\Factories;

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantMembership>
 */
class TenantMembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'role' => TenantMemberRole::Member->value,
        ];
    }

    public function tenantAdmin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TenantMemberRole::TenantAdmin->value,
        ]);
    }

    public function member(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => TenantMemberRole::Member->value,
        ]);
    }
}
