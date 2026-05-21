<?php

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function loadTenancyFixtures(): void
{
    $migration = require __DIR__.'/Fixtures/2026_05_21_000099_create_tenant_scoped_test_models_table.php';
    $migration->up();
}

function createMembership(User $user, Tenant $tenant, TenantMemberRole $role = TenantMemberRole::Member): TenantMembership
{
    return TenantMembership::query()->create([
        'user_id' => $user->getKey(),
        'tenant_id' => $tenant->getKey(),
        'role' => $role->value,
    ]);
}

function actingAsMember(User $user, Tenant $tenant): User
{
    createMembership($user, $tenant, TenantMemberRole::Member);
    setActiveTenantContext($tenant, actingAsSuperAdmin: false);
    test()->actingAs($user)->withSession(['active_tenant_id' => $tenant->getKey()]);

    return $user;
}

function actingAsTenantAdmin(User $user, Tenant $tenant): User
{
    createMembership($user, $tenant, TenantMemberRole::TenantAdmin);
    setActiveTenantContext($tenant, actingAsSuperAdmin: false);
    test()->actingAs($user)->withSession(['active_tenant_id' => $tenant->getKey()]);

    return $user;
}

function actingAsSuperAdmin(User $user, ?Tenant $tenant = null): User
{
    $user->forceFill(['is_super_admin' => true])->save();

    if ($tenant !== null) {
        setActiveTenantContext($tenant, actingAsSuperAdmin: ! $user->belongsToTenant($tenant));
        test()->actingAs($user)->withSession(['active_tenant_id' => $tenant->getKey()]);
    } else {
        test()->actingAs($user);
    }

    return $user;
}

function setActiveTenantContext(Tenant $tenant, bool $actingAsSuperAdmin = false): void
{
    /** @var TenantContext $context */
    $context = app(TenantContext::class);
    $context->set($tenant, $actingAsSuperAdmin);
}
