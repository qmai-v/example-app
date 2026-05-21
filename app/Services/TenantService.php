<?php

namespace App\Services;

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantService extends BaseService
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly TenantContext $context,
    ) {}

    public function findCurrent(): Tenant
    {
        return $this->context->tenant();
    }

    public function updateName(Tenant $tenant, string $name): Tenant
    {
        DB::transaction(function () use ($tenant, $name): void {
            $tenant->forceFill(['name' => $name])->save();
        });

        $this->audit('tenant_updated', $tenant, ['name' => $name]);

        return $tenant;
    }

    /**
     * @param  array{name: string, slug?: ?string}  $attributes
     */
    public function createTenant(array $attributes, User $initialAdmin): Tenant
    {
        $name = (string) $attributes['name'];
        $slug = $this->normalizeSlug($attributes['slug'] ?? null, $name);

        return DB::transaction(function () use ($name, $slug, $initialAdmin): Tenant {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->create([
                'name' => $name,
                'slug' => $slug,
                'status' => 'active',
            ]);

            TenantMembership::query()->create([
                'user_id' => $initialAdmin->getKey(),
                'tenant_id' => $tenant->getKey(),
                'role' => TenantMemberRole::TenantAdmin->value,
            ]);

            $this->audit('tenant_created', $tenant, [
                'initial_tenant_admin_user_id' => $initialAdmin->getKey(),
            ]);

            return $tenant;
        });
    }

    /**
     * @param  array{name?: string, slug?: ?string, status?: ?string}  $attributes
     */
    public function updateTenant(Tenant $tenant, array $attributes): Tenant
    {
        if ($tenant->trashed()) {
            throw ValidationException::withMessages([
                'status' => __('Restore the tenant before updating it.'),
            ]);
        }

        $changes = [];

        if (array_key_exists('name', $attributes) && $attributes['name'] !== null) {
            $changes['name'] = (string) $attributes['name'];
        }

        if (array_key_exists('slug', $attributes) && $attributes['slug'] !== null && $attributes['slug'] !== '') {
            $changes['slug'] = Str::slug((string) $attributes['slug']);
        }

        $statusChanged = false;

        if (array_key_exists('status', $attributes) && $attributes['status'] !== null) {
            $newStatus = (string) $attributes['status'];

            if ($newStatus !== $tenant->status) {
                $changes['status'] = $newStatus;
                $statusChanged = true;
            }
        }

        if ($changes === []) {
            return $tenant;
        }

        DB::transaction(function () use ($tenant, $changes): void {
            $tenant->forceFill($changes)->save();
        });

        if ($statusChanged) {
            $event = $changes['status'] === 'suspended' ? 'tenant_suspended' : 'tenant_reactivated';
            $this->audit($event, $tenant, ['status' => $changes['status']]);
        } else {
            $this->audit('tenant_updated', $tenant, $changes);
        }

        return $tenant;
    }

    public function suspend(Tenant $tenant): Tenant
    {
        return $this->updateTenant($tenant, ['status' => 'suspended']);
    }

    public function reactivate(Tenant $tenant): Tenant
    {
        return $this->updateTenant($tenant, ['status' => 'active']);
    }

    public function softDelete(Tenant $tenant): void
    {
        if ($tenant->trashed()) {
            return;
        }

        DB::transaction(function () use ($tenant): void {
            $tenant->delete();
        });

        $this->audit('tenant_deleted', $tenant);
    }

    public function restore(Tenant $tenant): Tenant
    {
        if (! $tenant->trashed()) {
            return $tenant;
        }

        DB::transaction(function () use ($tenant): void {
            $tenant->restore();
        });

        $this->audit('tenant_restored', $tenant);

        return $tenant->refresh();
    }

    public function repository(): TenantRepositoryInterface
    {
        return $this->tenants;
    }

    private function normalizeSlug(?string $slug, string $name): string
    {
        $slug = $slug !== null && $slug !== '' ? Str::slug($slug) : Str::slug($name);

        if ($slug === '') {
            $slug = 'tenant';
        }

        $base = $slug;
        $suffix = 0;

        while ($this->tenants->findBySlug($slug) !== null) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(string $event, Tenant $tenant, array $metadata = []): void
    {
        $actor = auth()->user();

        Log::channel('tenancy_audit')->info($event, [
            'event' => $event,
            'actor_id' => $actor?->getKey(),
            'acted_as' => $this->context->actingAsSuperAdmin() || ($actor instanceof User && $actor->isSuperAdmin())
                ? 'super_admin'
                : 'tenant_admin',
            'tenant_id' => $tenant->getKey(),
            'target_id' => $tenant->getKey(),
            'metadata' => $metadata,
        ]);
    }
}
