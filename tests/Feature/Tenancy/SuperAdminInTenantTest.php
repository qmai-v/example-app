<?php

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('lets a super admin switch to a tenant they are not a member of', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $home = Tenant::factory()->create();
    $other = Tenant::factory()->create(['name' => 'Foreign Tenant']);
    createMembership($superAdmin, $home, TenantMemberRole::Member);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $home->getKey()])
        ->post(route('tenants.switch'), ['tenant_id' => $other->getKey()])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('active_tenant_id', $other->getKey());

    expect($superAdmin->refresh()->last_tenant_id)->toBe($other->getKey());
});

it('lets a super admin without tenant memberships access platform pages', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('tenant.active', null)
        );

    $this->actingAs($superAdmin)
        ->get(route('users.index'))
        ->assertOk();

    $this->actingAs($superAdmin)
        ->get(route('admin.tenants.index'))
        ->assertOk();
});

it('shares all active tenants as switch options for a super admin without memberships', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $alpha = Tenant::factory()->create(['name' => 'Alpha Tenant']);
    $beta = Tenant::factory()->create(['name' => 'Beta Tenant']);
    Tenant::factory()->suspended()->create(['name' => 'Suspended Tenant']);

    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('tenant.active', null)
            ->where('tenant.available', [
                [
                    'id' => $alpha->getKey(),
                    'name' => 'Alpha Tenant',
                    'slug' => $alpha->slug,
                    'role' => 'super_admin',
                ],
                [
                    'id' => $beta->getKey(),
                    'name' => 'Beta Tenant',
                    'slug' => $beta->slug,
                    'role' => 'super_admin',
                ],
            ])
        );
});

it('grants tenant-admin-equivalent capability when acting in a non-member tenant', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $existingAdmin = User::factory()->create();
    createMembership($existingAdmin, $tenant, TenantMemberRole::TenantAdmin);

    // Super admin is NOT a member of $tenant.
    $invitee = User::factory()->create(['email' => 'guest@example.com']);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->post(route('tenant.members.store'), [
            'email' => 'guest@example.com',
            'role' => TenantMemberRole::Member->value,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.members.index'));

    expect(TenantMembership::query()
        ->where('tenant_id', $tenant->getKey())
        ->where('user_id', $invitee->getKey())
        ->exists()
    )->toBeTrue();
});

it('attributes writes performed by a super admin in a non-member tenant as super_admin', function (): void {
    Log::spy();

    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    $existingAdmin = User::factory()->create();
    createMembership($existingAdmin, $tenant, TenantMemberRole::TenantAdmin);

    User::factory()->create(['email' => 'audit-target@example.com']);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->post(route('tenant.members.store'), [
            'email' => 'audit-target@example.com',
            'role' => TenantMemberRole::Member->value,
        ])
        ->assertSessionHasNoErrors();

    Log::shouldHaveReceived('channel')
        ->with('tenancy_audit')
        ->atLeast()
        ->once();
});

it('still denies non-super-admins access to tenants they are not members of', function (): void {
    $regular = User::factory()->create();
    $home = Tenant::factory()->create();
    $foreign = Tenant::factory()->create();
    createMembership($regular, $home, TenantMemberRole::TenantAdmin);

    $this->actingAs($regular)
        ->withSession(['active_tenant_id' => $home->getKey()])
        ->from(route('dashboard'))
        ->post(route('tenants.switch'), ['tenant_id' => $foreign->getKey()])
        ->assertSessionHasErrors('tenant_id');
});

it('refuses to set a soft-deleted tenant as active even for a super admin', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $home = Tenant::factory()->create();
    createMembership($superAdmin, $home);

    $deleted = Tenant::factory()->create();
    $deleted->delete();

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $home->getKey()])
        ->from(route('dashboard'))
        ->post(route('tenants.switch'), ['tenant_id' => $deleted->getKey()])
        ->assertSessionHasErrors('tenant_id');
});
