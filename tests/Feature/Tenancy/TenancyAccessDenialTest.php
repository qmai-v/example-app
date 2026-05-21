<?php

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('denies a regular member every tenant-admin route in their own tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $member = User::factory()->create();
    createMembership($member, $tenant, TenantMemberRole::Member);
    // Ensure tenant has at least one admin so it is well-formed.
    createMembership(User::factory()->create(), $tenant, TenantMemberRole::TenantAdmin);

    $session = ['active_tenant_id' => $tenant->getKey()];

    $this->actingAs($member)->withSession($session)
        ->get(route('tenant.members.index'))->assertForbidden();
    $this->actingAs($member)->withSession($session)
        ->post(route('tenant.members.store'), ['email' => 'x@example.com'])->assertForbidden();
    $this->actingAs($member)->withSession($session)
        ->get(route('tenant.settings.edit'))->assertForbidden();
    $this->actingAs($member)->withSession($session)
        ->put(route('tenant.settings.update'), ['name' => 'Hack'])->assertForbidden();
});

it('denies a tenant admin every super-admin route', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    createMembership($admin, $tenant, TenantMemberRole::TenantAdmin);

    $session = ['active_tenant_id' => $tenant->getKey()];

    $this->actingAs($admin)->withSession($session)
        ->get(route('admin.tenants.index'))->assertForbidden();
    $this->actingAs($admin)->withSession($session)
        ->post(route('admin.tenants.store'), [
            'name' => 'Hack Tenant',
            'initial_tenant_admin_user_id' => $admin->getKey(),
        ])->assertForbidden();
    $this->actingAs($admin)->withSession($session)
        ->put(route('admin.tenants.update', $tenant), ['name' => 'Hack'])->assertForbidden();
    $this->actingAs($admin)->withSession($session)
        ->delete(route('admin.tenants.destroy', $tenant))->assertForbidden();

    $foreign = Tenant::factory()->create();
    $foreignMembership = createMembership(
        User::factory()->create(),
        $foreign,
        TenantMemberRole::TenantAdmin,
    );

    $this->actingAs($admin)->withSession($session)
        ->get(route('admin.tenants.members.index', $foreign))->assertForbidden();
    $this->actingAs($admin)->withSession($session)
        ->post(route('admin.tenants.members.store', $foreign), [
            'email' => 'x@example.com',
            'role' => TenantMemberRole::Member->value,
        ])->assertForbidden();
    $this->actingAs($admin)->withSession($session)
        ->put(route('admin.tenants.members.update', ['tenant' => $foreign, 'membership' => $foreignMembership]), [
            'role' => TenantMemberRole::Member->value,
        ])->assertForbidden();
    $this->actingAs($admin)->withSession($session)
        ->delete(route('admin.tenants.members.destroy', ['tenant' => $foreign, 'membership' => $foreignMembership]))
        ->assertForbidden();
});

it('denies a tenant admin of tenant A access to tenant B membership URLs', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $adminA = User::factory()->create();
    createMembership($adminA, $tenantA, TenantMemberRole::TenantAdmin);

    $memberB = User::factory()->create();
    $membershipB = createMembership($memberB, $tenantB, TenantMemberRole::Member);
    createMembership(User::factory()->create(), $tenantB, TenantMemberRole::TenantAdmin);

    $session = ['active_tenant_id' => $tenantA->getKey()];

    $this->actingAs($adminA)->withSession($session)
        ->put(route('tenant.members.update', $membershipB), [
            'role' => TenantMemberRole::TenantAdmin->value,
        ])->assertNotFound();

    $this->actingAs($adminA)->withSession($session)
        ->delete(route('tenant.members.destroy', $membershipB))
        ->assertNotFound();

    expect($membershipB->fresh())->not->toBeNull();
    expect($membershipB->fresh()->role)->toBe(TenantMemberRole::Member);
});

it('denies guests every tenant-scoped route by redirecting to login', function (): void {
    $tenant = Tenant::factory()->create();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('tenant.members.index'))->assertRedirect(route('login'));
    $this->get(route('tenant.settings.edit'))->assertRedirect(route('login'));
    $this->get(route('admin.tenants.index'))->assertRedirect(route('login'));
    $this->post(route('tenants.switch'), ['tenant_id' => $tenant->getKey()])
        ->assertRedirect(route('login'));
});

it('treats route-bound memberships from foreign tenants as not found, never leaking existence', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $adminA = User::factory()->create();
    createMembership($adminA, $tenantA, TenantMemberRole::TenantAdmin);

    $memberB = User::factory()->create();
    $membershipB = createMembership($memberB, $tenantB, TenantMemberRole::Member);

    // The response is 404 ("not found") rather than 403 ("forbidden") — admin A
    // gets no hint that the foreign membership actually exists in tenant B.
    $this->actingAs($adminA)
        ->withSession(['active_tenant_id' => $tenantA->getKey()])
        ->delete(route('tenant.members.destroy', $membershipB))
        ->assertNotFound();
});

it('keeps suspended and soft-deleted tenants out of the available-tenants prop for regular users', function (): void {
    $user = User::factory()->create();
    $active = Tenant::factory()->create(['name' => 'Active One']);
    $suspended = Tenant::factory()->suspended()->create(['name' => 'Suspended One']);
    $deleted = Tenant::factory()->create(['name' => 'Deleted One']);

    createMembership($user, $active, TenantMemberRole::Member);
    createMembership($user, $suspended, TenantMemberRole::Member);
    createMembership($user, $deleted, TenantMemberRole::Member);

    $deleted->delete();

    // The switcher refuses to set suspended/deleted as active.
    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $active->getKey()])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('tenant.available', [[
                'id' => $active->getKey(),
                'name' => 'Active One',
                'slug' => $active->slug,
                'role' => TenantMemberRole::Member->value,
            ]])
        );

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $active->getKey()])
        ->from(route('dashboard'))
        ->post(route('tenants.switch'), ['tenant_id' => $suspended->getKey()])
        ->assertSessionHasErrors('tenant_id');

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $active->getKey()])
        ->from(route('dashboard'))
        ->post(route('tenants.switch'), ['tenant_id' => $deleted->getKey()])
        ->assertSessionHasErrors('tenant_id');
});
