<?php

namespace App\Repositories;

use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Repositories\Contracts\TenantMembershipRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<TenantMembership>
 */
class TenantMembershipRepository extends BaseRepository implements TenantMembershipRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new TenantMembership);
    }

    /**
     * @return Collection<int, TenantMembership>
     */
    public function forUser(User $user): Collection
    {
        return $this->query()
            ->with('tenant')
            ->where('user_id', $user->getKey())
            ->orderBy('created_at')
            ->get();
    }

    public function betweenUserAndTenant(User $user, Tenant $tenant): ?TenantMembership
    {
        return $this->query()
            ->where('user_id', $user->getKey())
            ->where('tenant_id', $tenant->getKey())
            ->first();
    }

    public function lastTenantAdmin(Tenant $tenant): ?TenantMembership
    {
        if ($this->countTenantAdmins($tenant) !== 1) {
            return null;
        }

        return $this->query()
            ->where('tenant_id', $tenant->getKey())
            ->where('role', TenantMemberRole::TenantAdmin->value)
            ->first();
    }

    public function countTenantAdmins(Tenant $tenant): int
    {
        return $this->query()
            ->where('tenant_id', $tenant->getKey())
            ->where('role', TenantMemberRole::TenantAdmin->value)
            ->count();
    }

    /**
     * @return LengthAwarePaginator<int, TenantMembership>
     */
    public function paginateMembers(Tenant $tenant, string $search, ?TenantMemberRole $role, int $perPage): LengthAwarePaginator
    {
        return $this->query()
            ->with('user')
            ->where('tenant_id', $tenant->getKey())
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('user', function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->when($role !== null, fn (Builder $query) => $query->where('role', $role->value))
            ->orderBy('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
