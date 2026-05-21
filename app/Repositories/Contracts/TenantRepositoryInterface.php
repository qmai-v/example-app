<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends RepositoryInterface<Tenant>
 */
interface TenantRepositoryInterface extends RepositoryInterface
{
    public function findIncludingTrashed(string $id): ?Tenant;

    public function findBySlug(string $slug): ?Tenant;

    /**
     * @return Builder<Tenant>
     */
    public function adminQuery(string $search, ?string $status): Builder;

    /**
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function paginateForAdmin(string $search, ?string $status, int $perPage): LengthAwarePaginator;
}
