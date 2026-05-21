<?php

use App\Exceptions\MissingTenantContextException;
use App\Models\Tenant;
use App\Services\TenantContext;
use Tests\Fixtures\TenantScopedTestModel;

beforeEach(function (): void {
    loadTenancyFixtures();
});

it('stamps tenant_id on creating writes from the active tenant context', function (): void {
    $tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    setActiveTenantContext($tenantA);

    $row = TenantScopedTestModel::query()->create(['label' => 'hello']);

    expect($row->tenant_id)->toBe($tenantA->getKey());
});

it('returns only rows belonging to the active tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    setActiveTenantContext($tenantA);
    $a1 = TenantScopedTestModel::query()->create(['label' => 'a1']);
    $a2 = TenantScopedTestModel::query()->create(['label' => 'a2']);

    setActiveTenantContext($tenantB);
    TenantScopedTestModel::query()->create(['label' => 'b1']);

    setActiveTenantContext($tenantA);
    $visible = TenantScopedTestModel::query()->get();

    expect($visible)->toHaveCount(2);
    expect($visible->pluck('id')->all())->toEqualCanonicalizing([$a1->id, $a2->id]);
});

it('treats a foreign tenant row id as not found', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    setActiveTenantContext($tenantB);
    $b1 = TenantScopedTestModel::query()->create(['label' => 'b1']);

    setActiveTenantContext($tenantA);

    expect(TenantScopedTestModel::query()->find($b1->id))->toBeNull();
});

it('cannot update a foreign-tenant row through the scoped query', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    setActiveTenantContext($tenantB);
    $b1 = TenantScopedTestModel::query()->create(['label' => 'b1']);

    setActiveTenantContext($tenantA);

    $affected = TenantScopedTestModel::query()->where('id', $b1->id)->update(['label' => 'tampered']);

    expect($affected)->toBe(0);
    expect(TenantScopedTestModel::query()->withoutGlobalScopes()->find($b1->id)->label)->toBe('b1');
});

it('cannot delete a foreign-tenant row through the scoped query', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    setActiveTenantContext($tenantB);
    $b1 = TenantScopedTestModel::query()->create(['label' => 'b1']);

    setActiveTenantContext($tenantA);

    $affected = TenantScopedTestModel::query()->where('id', $b1->id)->delete();

    expect($affected)->toBe(0);
    expect(TenantScopedTestModel::query()->withoutGlobalScopes()->find($b1->id))->not->toBeNull();
});

it('throws when querying a tenant-scoped model with no active tenant context', function (): void {
    /** @var TenantContext $context */
    $context = app(TenantContext::class);
    $context->clear();

    TenantScopedTestModel::query()->get();
})->throws(MissingTenantContextException::class);

it('throws when creating a tenant-scoped model with no active tenant context', function (): void {
    /** @var TenantContext $context */
    $context = app(TenantContext::class);
    $context->clear();

    TenantScopedTestModel::query()->withoutGlobalScopes()->create(['label' => 'orphan']);
})->throws(MissingTenantContextException::class);

it('allows admin-style cross-tenant reads via withoutGlobalScope bypass', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    setActiveTenantContext($tenantA);
    TenantScopedTestModel::query()->create(['label' => 'a1']);
    setActiveTenantContext($tenantB);
    TenantScopedTestModel::query()->create(['label' => 'b1']);

    // Bypass for admin code paths
    $all = TenantScopedTestModel::query()->withoutGlobalScopes()->get();

    expect($all)->toHaveCount(2);
});
