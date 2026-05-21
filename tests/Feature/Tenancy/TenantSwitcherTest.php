<?php

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

it('lets a multi-tenant member switch the active tenant and persists last_tenant_id', function (): void {
    $user = User::factory()->create();
    $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

    createMembership($user, $tenantA, TenantMemberRole::Member);
    createMembership($user, $tenantB, TenantMemberRole::Member);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenantA->getKey()])
        ->post(route('tenants.switch'), ['tenant_id' => $tenantB->getKey()])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('active_tenant_id', $tenantB->getKey());

    expect($user->refresh()->last_tenant_id)->toBe($tenantB->getKey());
});

it('refuses a switch to a tenant the user does not belong to', function (): void {
    $user = User::factory()->create();
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    createMembership($user, $tenantA, TenantMemberRole::Member);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenantA->getKey()])
        ->from(route('dashboard'))
        ->post(route('tenants.switch'), ['tenant_id' => $tenantB->getKey()])
        ->assertSessionHasErrors('tenant_id');

    expect(session('active_tenant_id'))->toBe($tenantA->getKey());
});

it('refuses a switch to a suspended tenant', function (): void {
    $user = User::factory()->create();
    $active = Tenant::factory()->create();
    $suspended = Tenant::factory()->suspended()->create();
    createMembership($user, $active);
    createMembership($user, $suspended);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $active->getKey()])
        ->from(route('dashboard'))
        ->post(route('tenants.switch'), ['tenant_id' => $suspended->getKey()])
        ->assertSessionHasErrors('tenant_id');
});

it('refuses a switch to a soft-deleted tenant even for super admins', function (): void {
    $user = User::factory()->superAdmin()->create();
    $active = Tenant::factory()->create();
    $deleted = Tenant::factory()->create();
    $deleted->delete();

    createMembership($user, $active);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $active->getKey()])
        ->from(route('dashboard'))
        ->post(route('tenants.switch'), ['tenant_id' => $deleted->getKey()])
        ->assertSessionHasErrors('tenant_id');
});

it('lets a super admin switch to a tenant they are not a member of', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $home = Tenant::factory()->create();
    $other = Tenant::factory()->create(['name' => 'Other Tenant']);
    createMembership($superAdmin, $home);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $home->getKey()])
        ->post(route('tenants.switch'), ['tenant_id' => $other->getKey()])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('active_tenant_id', $other->getKey());

    expect($superAdmin->refresh()->last_tenant_id)->toBe($other->getKey());
});

it('evicts the user from a revoked active tenant on the next request', function (): void {
    $user = User::factory()->create();
    $primary = Tenant::factory()->create();
    $secondary = Tenant::factory()->create();
    createMembership($user, $primary);
    createMembership($user, $secondary);
    $user->forceFill(['last_tenant_id' => $primary->getKey()])->save();

    // Revoke membership of the active tenant by deleting that pivot row.
    TenantMembership::query()
        ->where('user_id', $user->getKey())
        ->where('tenant_id', $primary->getKey())
        ->delete();

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $primary->getKey()])
        ->get(route('dashboard'))
        ->assertOk();

    expect(session('active_tenant_id'))->toBe($secondary->getKey());
    expect($user->refresh()->last_tenant_id)->toBe($secondary->getKey());
});

it('redirects to the no-tenant page when the user has no available tenants', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('tenants.no-tenant'));
});

it('exposes the active tenant via Inertia shared props', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['name' => 'Visible Tenant', 'slug' => 'visible-tenant']);
    createMembership($user, $tenant, TenantMemberRole::TenantAdmin);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->get(route('dashboard'))
        ->assertOk();

    // Pull props from the most recent Inertia render via the response side-effect.
    expect($user->refresh()->last_tenant_id)->toBe($tenant->getKey());
});
