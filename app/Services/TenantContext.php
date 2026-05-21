<?php

namespace App\Services;

use App\Exceptions\MissingTenantContextException;
use App\Models\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $actingAsSuperAdmin = false;

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function set(Tenant $tenant, bool $actingAsSuperAdmin = false): void
    {
        $this->tenant = $tenant;
        $this->actingAsSuperAdmin = $actingAsSuperAdmin;
    }

    public function tenant(): Tenant
    {
        if ($this->tenant === null) {
            throw new MissingTenantContextException;
        }

        return $this->tenant;
    }

    public function id(): string
    {
        return $this->tenant()->getKey();
    }

    public function actingAsSuperAdmin(): bool
    {
        return $this->has() && $this->actingAsSuperAdmin;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->actingAsSuperAdmin = false;
    }
}
