<?php

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->withoutVite();
});

it('restores the last tenant on successful sign-in', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $user = User::factory()->create([
        'email' => 'restore@example.com',
        'password' => Hash::make('password-123'),
    ]);

    createMembership($user, $tenantA, TenantMemberRole::Member);
    createMembership($user, $tenantB, TenantMemberRole::Member);
    $user->forceFill(['last_tenant_id' => $tenantB->getKey()])->save();

    $this->post('/login', [
        'email' => 'restore@example.com',
        'password' => 'password-123',
    ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('active_tenant_id', $tenantB->getKey());

    expect($user->refresh()->last_tenant_id)->toBe($tenantB->getKey());
});

it('falls back to the earliest membership when the last tenant is suspended', function (): void {
    $earlier = Tenant::factory()->create(['name' => 'Earlier']);
    $later = Tenant::factory()->suspended()->create(['name' => 'Later']);

    $user = User::factory()->create([
        'email' => 'fallback@example.com',
        'password' => Hash::make('password-123'),
    ]);

    $earlierMembership = createMembership($user, $earlier);
    // Force `later` membership to be created strictly after `earlier`.
    $laterMembership = createMembership($user, $later);

    expect($earlierMembership->created_at)->toBeLessThanOrEqual($laterMembership->created_at);

    $user->forceFill(['last_tenant_id' => $later->getKey()])->save();

    $this->post('/login', [
        'email' => 'fallback@example.com',
        'password' => 'password-123',
    ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('active_tenant_id', $earlier->getKey());

    expect($user->refresh()->last_tenant_id)->toBe($earlier->getKey());
});

it('picks a deterministic default on first-ever sign-in', function (): void {
    $earlier = Tenant::factory()->create(['name' => 'First']);
    $later = Tenant::factory()->create(['name' => 'Second']);

    $user = User::factory()->create([
        'email' => 'newcomer@example.com',
        'password' => Hash::make('password-123'),
        'last_tenant_id' => null,
    ]);

    createMembership($user, $earlier);
    createMembership($user, $later);

    $this->post('/login', [
        'email' => 'newcomer@example.com',
        'password' => 'password-123',
    ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('active_tenant_id', $earlier->getKey());

    expect($user->refresh()->last_tenant_id)->toBe($earlier->getKey());
});

it('redirects to the no-tenant page when the user has no memberships', function (): void {
    User::factory()->create([
        'email' => 'orphan@example.com',
        'password' => Hash::make('password-123'),
    ]);

    $this->post('/login', [
        'email' => 'orphan@example.com',
        'password' => 'password-123',
    ])
        ->assertRedirect(route('tenants.no-tenant'))
        ->assertSessionMissing('active_tenant_id');
});

it('puts a super admin with no memberships into the earliest active tenant', function (): void {
    $first = Tenant::factory()->create(['name' => 'First']);
    Tenant::factory()->create(['name' => 'Second']);

    $superAdmin = User::factory()->superAdmin()->create([
        'email' => 'sa@example.com',
        'password' => Hash::make('password-123'),
    ]);

    $this->post('/login', [
        'email' => 'sa@example.com',
        'password' => 'password-123',
    ])
        ->assertRedirect('/dashboard')
        ->assertSessionHas('active_tenant_id', $first->getKey());

    expect($superAdmin->refresh()->last_tenant_id)->toBe($first->getKey());
});
