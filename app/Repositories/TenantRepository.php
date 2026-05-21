<?php

namespace App\Repositories;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Tenant>
 */
class TenantRepository extends BaseRepository implements TenantRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Tenant);
    }

    public function findIncludingTrashed(string $id): ?Tenant
    {
        return Tenant::query()->withTrashed()->find($id);
    }

    public function findBySlug(string $slug): ?Tenant
    {
        return $this->query()->where('slug', $slug)->first();
    }

    /**
     * @return Builder<Tenant>
     */
    public function adminQuery(string $search, ?string $status): Builder
    {
        return Tenant::query()
            ->withTrashed()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('slug', 'ilike', "%{$search}%");
                });
            })
            ->when($status === 'active', fn (Builder $query) => $query->where('status', 'active')->whereNull('deleted_at'))
            ->when($status === 'suspended', fn (Builder $query) => $query->where('status', 'suspended')->whereNull('deleted_at'))
            ->when($status === 'deleted', fn (Builder $query) => $query->whereNotNull('deleted_at'));
    }

    /**
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function paginateForAdmin(string $search, ?string $status, int $perPage): LengthAwarePaginator
    {
        return $this->adminQuery($search, $status)
            ->withCount('memberships')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
