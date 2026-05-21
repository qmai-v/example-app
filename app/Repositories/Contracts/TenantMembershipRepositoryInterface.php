<?php

namespace App\Repositories\Contracts;

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<TenantMembership>
 */
interface TenantMembershipRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, TenantMembership>
     */
    public function forUser(User $user): Collection;

    public function betweenUserAndTenant(User $user, Tenant $tenant): ?TenantMembership;

    public function lastTenantAdmin(Tenant $tenant): ?TenantMembership;

    public function countTenantAdmins(Tenant $tenant): int;

    /**
     * @return LengthAwarePaginator<int, TenantMembership>
     */
    public function paginateMembers(Tenant $tenant, string $search, ?TenantMemberRole $role, int $perPage): LengthAwarePaginator;
}
