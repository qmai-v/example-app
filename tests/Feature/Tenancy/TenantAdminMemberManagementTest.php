<?php

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

beforeEach(function (): void {
    $this->withoutVite();
});

it('lets a tenant admin list, add, role-change, and remove members in their tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    createMembership($admin, $tenant, TenantMemberRole::TenantAdmin);
    // Second admin so the last-tenant-admin guard does not block role changes.
    $coAdmin = User::factory()->create();
    createMembership($coAdmin, $tenant, TenantMemberRole::TenantAdmin);

    $candidate = User::factory()->create(['email' => 'invitee@example.com']);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->get(route('tenant.members.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->post(route('tenant.members.store'), [
            'email' => 'invitee@example.com',
            'role' => TenantMemberRole::Member->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.members.index'));

    $membership = TenantMembership::query()
        ->where('user_id', $candidate->getKey())
        ->where('tenant_id', $tenant->getKey())
        ->first();

    expect($membership)->not->toBeNull();
    expect($membership->role)->toBe(TenantMemberRole::Member);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->put(route('tenant.members.update', $membership), [
            'role' => TenantMemberRole::TenantAdmin->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.members.index'));

    expect($membership->fresh()->role)->toBe(TenantMemberRole::TenantAdmin);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->delete(route('tenant.members.destroy', $membership))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.members.index'));

    expect($membership->fresh())->toBeNull();
});

it('rejects an attempt to add a user that does not exist', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    createMembership($admin, $tenant, TenantMemberRole::TenantAdmin);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->from(route('tenant.members.index'))
        ->post(route('tenant.members.store'), [
            'email' => 'ghost@example.com',
        ])
        ->assertSessionHasErrors('email');
});

it('rejects adding a user who is already a member', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    createMembership($admin, $tenant, TenantMemberRole::TenantAdmin);

    $existing = User::factory()->create(['email' => 'existing@example.com']);
    createMembership($existing, $tenant);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->from(route('tenant.members.index'))
        ->post(route('tenant.members.store'), [
            'email' => 'existing@example.com',
        ])
        ->assertSessionHasErrors('email');
});

it('blocks demoting the last remaining tenant admin', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $adminMembership = createMembership($admin, $tenant, TenantMemberRole::TenantAdmin);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->from(route('tenant.members.index'))
        ->put(route('tenant.members.update', $adminMembership), [
            'role' => TenantMemberRole::Member->value,
        ])
        ->assertSessionHasErrors('role');

    expect($adminMembership->fresh()->role)->toBe(TenantMemberRole::TenantAdmin);
});

it('blocks removing the last remaining tenant admin', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    $adminMembership = createMembership($admin, $tenant, TenantMemberRole::TenantAdmin);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->from(route('tenant.members.index'))
        ->delete(route('tenant.members.destroy', $adminMembership))
        ->assertSessionHasErrors('membership');

    expect($adminMembership->fresh())->not->toBeNull();
});

it('denies a regular member access to the tenant admin area', function (): void {
    $tenant = Tenant::factory()->create();
    $member = User::factory()->create();
    createMembership($member, $tenant, TenantMemberRole::Member);
    // Need at least one admin so the tenant is well-formed.
    $admin = User::factory()->create();
    createMembership($admin, $tenant, TenantMemberRole::TenantAdmin);

    $this->actingAs($member)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->get(route('tenant.members.index'))
        ->assertForbidden();

    $this->actingAs($member)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->get(route('tenant.settings.edit'))
        ->assertForbidden();
});

it('denies a tenant admin of tenant A access to tenant B membership URLs', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $adminA = User::factory()->create();
    createMembership($adminA, $tenantA, TenantMemberRole::TenantAdmin);

    $memberB = User::factory()->create();
    $membershipB = createMembership($memberB, $tenantB, TenantMemberRole::Member);
    $adminB = User::factory()->create();
    createMembership($adminB, $tenantB, TenantMemberRole::TenantAdmin);

    // Admin A is signed into tenant A but tries to manipulate tenant B's membership.
    $this->actingAs($adminA)
        ->withSession(['active_tenant_id' => $tenantA->getKey()])
        ->delete(route('tenant.members.destroy', $membershipB))
        ->assertNotFound();

    expect($membershipB->fresh())->not->toBeNull();
});

it('lets a tenant admin update the tenant display name', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Old Name']);
    $admin = User::factory()->create();
    createMembership($admin, $tenant, TenantMemberRole::TenantAdmin);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->put(route('tenant.settings.update'), ['name' => 'New Name'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.settings.edit'));

    expect($tenant->refresh()->name)->toBe('New Name');
});

it('lets a super admin acting in a non-member tenant manage members', function (): void {
    $tenant = Tenant::factory()->create();
    $existingAdmin = User::factory()->create();
    createMembership($existingAdmin, $tenant, TenantMemberRole::TenantAdmin);

    $superAdmin = User::factory()->superAdmin()->create();
    $candidate = User::factory()->create(['email' => 'invited-by-super@example.com']);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->post(route('tenant.members.store'), [
            'email' => 'invited-by-super@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.members.index'));

    expect(TenantMembership::query()
        ->where('user_id', $candidate->getKey())
        ->where('tenant_id', $tenant->getKey())
        ->exists()
    )->toBeTrue();
});
