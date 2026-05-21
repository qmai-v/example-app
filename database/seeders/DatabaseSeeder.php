<?php

namespace Database\Seeders;

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $testUser = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ],
        );

        $superAdmin = User::query()->firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
            ],
        );

        if (! $superAdmin->isSuperAdmin()) {
            $superAdmin->forceFill(['is_super_admin' => true])->save();
        }

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Tenant',
                'status' => 'active',
            ],
        );

        $tenantAdmin = User::query()->firstOrCreate(
            ['email' => 'tenantadmin@example.com'],
            [
                'name' => 'Tenant Admin',
                'password' => Hash::make('password'),
            ],
        );

        $member = User::query()->firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'name' => 'Tenant Member',
                'password' => Hash::make('password'),
            ],
        );

        TenantMembership::query()->firstOrCreate(
            ['user_id' => $tenantAdmin->getKey(), 'tenant_id' => $tenant->getKey()],
            ['role' => TenantMemberRole::TenantAdmin->value],
        );

        TenantMembership::query()->firstOrCreate(
            ['user_id' => $member->getKey(), 'tenant_id' => $tenant->getKey()],
            ['role' => TenantMemberRole::Member->value],
        );

        if ($testUser->wasRecentlyCreated && User::query()->count() < 1000) {
            User::factory(max(1000 - User::query()->count(), 0))->create();
        }
    }
}
