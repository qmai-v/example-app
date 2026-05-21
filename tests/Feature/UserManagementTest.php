<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected from the user management page', function () {
    $this->get(route('users.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can view the user management page', function () {
    $user = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($user, $tenant);

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users.data')
            ->where('filters.search', null)
        );
});

test('users are paginated', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);
    User::factory()->count(30)->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users.data', 25)
            ->where('users.current_page', 1)
            ->where('users.per_page', 25)
        );
});

test('users can choose rows per page', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);
    User::factory()->count(30)->create();

    $this->actingAs($admin)
        ->get(route('users.index', ['per_page' => 25]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users.data', 25)
            ->where('users.per_page', 25)
            ->where('filters.per_page', 25)
            ->where('filters.per_page_options', [10, 25, 50, 100])
        );
});

test('invalid rows per page falls back to the default', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);
    User::factory()->count(30)->create();

    $this->actingAs($admin)
        ->get(route('users.index', ['per_page' => 999]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users.data', 25)
            ->where('users.per_page', 25)
            ->where('filters.per_page', 25)
        );
});

test('users can be searched by name or email', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);
    User::factory()->create(['name' => 'Alice Searchable', 'email' => 'alice@example.com']);
    User::factory()->create(['name' => 'Bob Person', 'email' => 'bob-searchable@example.com']);
    User::factory()->create(['name' => 'Charlie Person', 'email' => 'charlie@example.com']);

    $this->actingAs($admin)
        ->get(route('users.index', ['search' => 'searchable']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users.data', 2)
            ->where('filters.search', 'searchable')
            ->where('users.data.0.name', 'Alice Searchable')
            ->where('users.data.1.name', 'Bob Person')
        );
});

test('users can be filtered by status', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);
    User::factory()->create(['name' => 'Verified Person']);
    User::factory()->unverified()->create(['name' => 'Unverified Person']);

    $this->actingAs($admin)
        ->get(route('users.index', ['status' => 'unverified']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users.data', 1)
            ->where('filters.status', 'unverified')
            ->where('users.data.0.name', 'Unverified Person')
        );
});

test('empty search results are returned clearly', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);
    User::factory()->create(['name' => 'Visible User', 'email' => 'visible@example.com']);

    $this->actingAs($admin)
        ->get(route('users.index', ['search' => 'missing']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users.data', 0)
            ->where('filters.search', 'missing')
        );
});

test('users can be created', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'new-user@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'));

    expect(User::where('email', 'new-user@example.com')->exists())->toBeTrue();
});

test('user mutations preserve selected rows per page', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Rows Per Page User',
            'email' => 'rows-per-page@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'per_page' => 25,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index', ['per_page' => 25]));

    expect(User::where('email', 'rows-per-page@example.com')->exists())->toBeTrue();
});

test('user mutations preserve selected status filter', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Status Filter User',
            'email' => 'status-filter@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'status' => 'verified',
            'per_page' => 25,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index', ['status' => 'verified', 'per_page' => 25]));

    expect(User::where('email', 'status-filter@example.com')->exists())->toBeTrue();
});

test('user creation validates required fields and duplicate email addresses', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);
    User::factory()->create(['email' => 'duplicate@example.com']);

    $this->actingAs($admin)
        ->from(route('users.index'))
        ->post(route('users.store'), [
            'name' => '',
            'email' => 'duplicate@example.com',
            'password' => '',
        ])
        ->assertSessionHasErrors(['name', 'email', 'password'])
        ->assertRedirect(route('users.index'));
});

test('users can be updated', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('users.update', $user), [
            'name' => 'Updated User',
            'email' => 'updated@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'));

    $user->refresh();

    expect($user->name)->toBe('Updated User');
    expect($user->email)->toBe('updated@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('user updates validate required fields and duplicate email addresses', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);
    $user = User::factory()->create();
    User::factory()->create(['email' => 'duplicate@example.com']);

    $this->actingAs($admin)
        ->from(route('users.index'))
        ->put(route('users.update', $user), [
            'name' => '',
            'email' => 'duplicate@example.com',
        ])
        ->assertSessionHasErrors(['name', 'email'])
        ->assertRedirect(route('users.index'));
});

test('eligible users can be deleted', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $user))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.index'));

    expect($user->fresh())->toBeNull();
});

test('canceling deletion makes no server-side change', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk();

    expect($user->fresh())->not->toBeNull();
});

test('current user cannot delete their own account', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertRedirect(route('users.index'));

    expect($admin->fresh())->not->toBeNull();
});

test('deleting the last user on a page redirects to the nearest available page', function () {
    $admin = User::factory()->superAdmin()->create();
    $tenant = Tenant::factory()->create();
    actingAsSuperAdmin($admin, $tenant);
    $target = User::factory()->create();
    User::factory()->count(9)->create();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target), ['page' => 2])
        ->assertRedirect(route('users.index'));

    expect($target->fresh())->toBeNull();
});
