<?php

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->withoutVite();
    $this->captured = [];
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')->andReturnUsing(function (string $event, array $context) {
        $this->captured[] = ['event' => $event, 'context' => $context];
    });
    $logger->shouldIgnoreMissing();
    Log::shouldReceive('channel')->with('tenancy_audit')->andReturn($logger);
});

it('logs login_tenant_restored when the previous tenant is still available', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'email' => 'audit-login@example.com',
        'password' => Hash::make('password-123'),
    ]);
    createMembership($user, $tenant);
    $user->forceFill(['last_tenant_id' => $tenant->getKey()])->save();

    $this->post('/login', [
        'email' => 'audit-login@example.com',
        'password' => 'password-123',
    ])->assertRedirect('/dashboard');

    $events = collect($this->captured)->pluck('event')->all();
    expect($events)->toContain('login_tenant_restored');
});

it('logs login_tenant_fallback when the previous tenant is unavailable', function (): void {
    $earlier = Tenant::factory()->create();
    $suspended = Tenant::factory()->suspended()->create();
    $user = User::factory()->create([
        'email' => 'audit-fallback@example.com',
        'password' => Hash::make('password-123'),
    ]);
    createMembership($user, $earlier);
    createMembership($user, $suspended);
    $user->forceFill(['last_tenant_id' => $suspended->getKey()])->save();

    $this->post('/login', [
        'email' => 'audit-fallback@example.com',
        'password' => 'password-123',
    ])->assertRedirect('/dashboard');

    $events = collect($this->captured)->pluck('event')->all();
    expect($events)->toContain('login_tenant_fallback');
});

it('logs login_no_tenant when no membership exists', function (): void {
    User::factory()->create([
        'email' => 'audit-orphan@example.com',
        'password' => Hash::make('password-123'),
    ]);

    $this->post('/login', [
        'email' => 'audit-orphan@example.com',
        'password' => 'password-123',
    ])->assertRedirect(route('tenants.no-tenant'));

    $events = collect($this->captured)->pluck('event')->all();
    expect($events)->toContain('login_no_tenant');
});

it('logs tenant_switched on a successful tenant switch', function (): void {
    $user = User::factory()->create();
    $a = Tenant::factory()->create();
    $b = Tenant::factory()->create();
    createMembership($user, $a);
    createMembership($user, $b);

    $this->actingAs($user)
        ->withSession(['active_tenant_id' => $a->getKey()])
        ->post(route('tenants.switch'), ['tenant_id' => $b->getKey()])
        ->assertRedirect(route('dashboard'));

    $events = collect($this->captured)->pluck('event')->all();
    expect($events)->toContain('tenant_switched');
});

it('logs cross_tenant_access_denied when a non-super-admin hits the admin area', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    createMembership($admin, $tenant, TenantMemberRole::TenantAdmin);

    $this->actingAs($admin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->get(route('admin.tenants.index'))
        ->assertForbidden();

    $events = collect($this->captured)->pluck('event')->all();
    expect($events)->toContain('cross_tenant_access_denied');
});

it('logs the super-admin tenant lifecycle events (created/updated/suspended/reactivated/deleted/restored)', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $home = Tenant::factory()->create();
    createMembership($superAdmin, $home);

    $designatedAdmin = User::factory()->create();
    $session = ['active_tenant_id' => $home->getKey()];

    // tenant_created
    $this->actingAs($superAdmin)->withSession($session)
        ->post(route('admin.tenants.store'), [
            'name' => 'Audit Tenant',
            'initial_tenant_admin_user_id' => $designatedAdmin->getKey(),
        ])->assertSessionHasNoErrors();

    $created = Tenant::query()->where('name', 'Audit Tenant')->first();

    // tenant_updated
    $this->actingAs($superAdmin)->withSession($session)
        ->put(route('admin.tenants.update', $created), [
            'name' => 'Audit Tenant Renamed',
        ])->assertSessionHasNoErrors();

    // tenant_suspended
    $this->actingAs($superAdmin)->withSession($session)
        ->put(route('admin.tenants.update', $created), [
            'name' => 'Audit Tenant Renamed',
            'status' => 'suspended',
        ])->assertSessionHasNoErrors();

    // tenant_reactivated
    $this->actingAs($superAdmin)->withSession($session)
        ->put(route('admin.tenants.update', $created), [
            'name' => 'Audit Tenant Renamed',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

    // tenant_deleted
    $this->actingAs($superAdmin)->withSession($session)
        ->delete(route('admin.tenants.destroy', $created))
        ->assertSessionHasNoErrors();

    // tenant_restored
    $this->actingAs($superAdmin)->withSession($session)
        ->post(route('admin.tenants.restore', $created))
        ->assertSessionHasNoErrors();

    $events = collect($this->captured)->pluck('event')->all();
    expect($events)->toContain(
        'tenant_created',
        'tenant_updated',
        'tenant_suspended',
        'tenant_reactivated',
        'tenant_deleted',
        'tenant_restored',
    );
});

it('logs membership lifecycle events (membership_added/role_changed/removed)', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create();
    createMembership($admin, $tenant, TenantMemberRole::TenantAdmin);
    createMembership(User::factory()->create(), $tenant, TenantMemberRole::TenantAdmin);

    $invitee = User::factory()->create(['email' => 'audit-membership@example.com']);
    $session = ['active_tenant_id' => $tenant->getKey()];

    // membership_added
    $this->actingAs($admin)->withSession($session)
        ->post(route('tenant.members.store'), [
            'email' => 'audit-membership@example.com',
            'role' => TenantMemberRole::Member->value,
        ])->assertSessionHasNoErrors();

    $membership = TenantMembership::query()
        ->where('user_id', $invitee->getKey())
        ->where('tenant_id', $tenant->getKey())
        ->first();

    // membership_role_changed
    $this->actingAs($admin)->withSession($session)
        ->put(route('tenant.members.update', $membership), [
            'role' => TenantMemberRole::TenantAdmin->value,
        ])->assertSessionHasNoErrors();

    // membership_removed
    $this->actingAs($admin)->withSession($session)
        ->delete(route('tenant.members.destroy', $membership))
        ->assertSessionHasNoErrors();

    $events = collect($this->captured)->pluck('event')->all();
    expect($events)->toContain(
        'membership_added',
        'membership_role_changed',
        'membership_removed',
    );
});

it('logs super_admin_in_tenant when a super admin operates inside a non-member tenant', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    // Super admin is NOT a member.
    createMembership(User::factory()->create(), $tenant, TenantMemberRole::TenantAdmin);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->get(route('tenant.members.index'))
        ->assertOk();

    $events = collect($this->captured)->pluck('event')->all();
    expect($events)->toContain('super_admin_in_tenant');
});

it('attributes super-admin writes inside a non-member tenant as super_admin', function (): void {
    $superAdmin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    createMembership(User::factory()->create(), $tenant, TenantMemberRole::TenantAdmin);

    User::factory()->create(['email' => 'audit-attrib@example.com']);

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $tenant->getKey()])
        ->post(route('tenant.members.store'), [
            'email' => 'audit-attrib@example.com',
            'role' => TenantMemberRole::Member->value,
        ])->assertSessionHasNoErrors();

    $addedEntry = collect($this->captured)
        ->first(fn (array $entry): bool => $entry['event'] === 'membership_added');

    expect($addedEntry)->not->toBeNull();
    expect($addedEntry['context']['acted_as'])->toBe('super_admin');
    expect($addedEntry['context']['actor_id'])->toBe($superAdmin->getKey());
    expect($addedEntry['context']['tenant_id'])->toBe($tenant->getKey());
});
