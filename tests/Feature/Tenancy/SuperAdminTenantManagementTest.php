<?php

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

beforeEach(function (): void {
    $this->withoutVite();
});

it('lets a super admin list every tenant including suspended and deleted', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $homeTenant = Tenant::factory()->create();
    createMembership($superAdmin, $homeTenant, TenantMemberRole::Member);

    Tenant::factory()->create(['name' => 'Active Tenant']);
    Tenant::factory()->suspended()->create(['name' => 'Suspended Tenant']);
    $deleted = Tenant::factory()->create(['name' => 'Deleted Tenant']);
    $deleted->delete();

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $homeTenant->getKey()])
        ->get(route('admin.tenants.index'))
        ->assertOk();
});

it('lets a super admin create a tenant with an initial tenant admin', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $homeTenant = Tenant::factory()->create();
    createMembership($superAdmin, $homeTenant);

    $designatedAdmin = User::factory()->create();

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $homeTenant->getKey()])
        ->post(route('admin.tenants.store'), [
            'name' => 'Brand New Tenant',
            'initial_tenant_admin_user_id' => $designatedAdmin->getKey(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.tenants.index'));

    $tenant = Tenant::query()->where('name', 'Brand New Tenant')->first();
    expect($tenant)->not->toBeNull();

    expect(TenantMembership::query()
        ->where('user_id', $designatedAdmin->getKey())
        ->where('tenant_id', $tenant->getKey())
        ->where('role', TenantMemberRole::TenantAdmin->value)
        ->exists()
    )->toBeTrue();
});

it('rejects tenant creation without an initial tenant admin', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $homeTenant = Tenant::factory()->create();
    createMembership($superAdmin, $homeTenant);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $homeTenant->getKey()])
        ->from(route('admin.tenants.index'))
        ->post(route('admin.tenants.store'), [
            'name' => 'Missing Admin Tenant',
        ])
        ->assertSessionHasErrors('initial_tenant_admin_user_id');

    expect(Tenant::query()->where('name', 'Missing Admin Tenant')->exists())->toBeFalse();
});

it('lets a super admin suspend and reactivate a tenant', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $homeTenant = Tenant::factory()->create();
    createMembership($superAdmin, $homeTenant);

    $target = Tenant::factory()->create();

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $homeTenant->getKey()])
        ->put(route('admin.tenants.update', $target), [
            'name' => $target->name,
            'status' => 'suspended',
        ])
        ->assertSessionHasNoErrors();

    expect($target->refresh()->status)->toBe('suspended');

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $homeTenant->getKey()])
        ->put(route('admin.tenants.update', $target), [
            'name' => $target->name,
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors();

    expect($target->refresh()->status)->toBe('active');
});

it('lets a super admin soft-delete a tenant and restore it with memberships intact', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $homeTenant = Tenant::factory()->create();
    createMembership($superAdmin, $homeTenant);

    $target = Tenant::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    createMembership($admin, $target, TenantMemberRole::TenantAdmin);
    createMembership($member, $target, TenantMemberRole::Member);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $homeTenant->getKey()])
        ->delete(route('admin.tenants.destroy', $target))
        ->assertSessionHasNoErrors();

    expect($target->refresh()->trashed())->toBeTrue();

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $homeTenant->getKey()])
        ->post(route('admin.tenants.restore', $target))
        ->assertSessionHasNoErrors();

    $restored = Tenant::query()->find($target->getKey());
    expect($restored)->not->toBeNull();
    expect($restored->trashed())->toBeFalse();

    expect(TenantMembership::query()
        ->where('tenant_id', $target->getKey())
        ->where('user_id', $admin->getKey())
        ->where('role', TenantMemberRole::TenantAdmin->value)
        ->exists()
    )->toBeTrue();

    expect(TenantMembership::query()
        ->where('tenant_id', $target->getKey())
        ->where('user_id', $member->getKey())
        ->where('role', TenantMemberRole::Member->value)
        ->exists()
    )->toBeTrue();
});

it('lets a super admin add, change role, and remove members on any tenant', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $homeTenant = Tenant::factory()->create();
    createMembership($superAdmin, $homeTenant);

    $target = Tenant::factory()->create();
    $existingAdmin = User::factory()->create();
    createMembership($existingAdmin, $target, TenantMemberRole::TenantAdmin);

    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $homeTenant->getKey()])
        ->post(route('admin.tenants.members.store', $target), [
            'email' => 'invitee@example.com',
            'role' => TenantMemberRole::Member->value,
        ])
        ->assertSessionHasNoErrors();

    $membership = TenantMembership::query()
        ->where('user_id', $invitee->getKey())
        ->where('tenant_id', $target->getKey())
        ->first();

    expect($membership)->not->toBeNull();

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $homeTenant->getKey()])
        ->put(route('admin.tenants.members.update', ['tenant' => $target, 'membership' => $membership]), [
            'role' => TenantMemberRole::TenantAdmin->value,
        ])
        ->assertSessionHasNoErrors();

    expect($membership->fresh()->role)->toBe(TenantMemberRole::TenantAdmin);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $homeTenant->getKey()])
        ->delete(route('admin.tenants.members.destroy', ['tenant' => $target, 'membership' => $membership]))
        ->assertSessionHasNoErrors();

    expect($membership->fresh())->toBeNull();
});

it('blocks non-super-admins from every admin tenants route', function (): void {
    $member = User::factory()->create();
    $tenant = Tenant::factory()->create();
    createMembership($member, $tenant, TenantMemberRole::TenantAdmin);

    $this->actingAs($member)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->get(route('admin.tenants.index'))
        ->assertForbidden();

    $this->actingAs($member)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->post(route('admin.tenants.store'), [
            'name' => 'Sneak',
            'initial_tenant_admin_user_id' => $member->getKey(),
        ])
        ->assertForbidden();
});

it('redirects members of a suspended active tenant on the next request', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $homeTenant = Tenant::factory()->create();
    createMembership($superAdmin, $homeTenant);

    $tenant = Tenant::factory()->create();
    $member = User::factory()->create();
    createMembership($member, $tenant, TenantMemberRole::Member);

    // Super admin suspends the tenant.
    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $homeTenant->getKey()])
        ->put(route('admin.tenants.update', $tenant), [
            'name' => $tenant->name,
            'status' => 'suspended',
        ])
        ->assertSessionHasNoErrors();

    // Member tries to keep working in the now-suspended tenant.
    $this->actingAs($member)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->get(route('dashboard'))
        ->assertRedirect(route('tenants.no-tenant'));
});
