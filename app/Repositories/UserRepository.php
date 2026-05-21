<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new User);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateForManagement(string $search, ?string $status, int $perPage = 10): LengthAwarePaginator
    {
        return $this->managementQuery($search, $status)
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countForManagement(string $search, ?string $status): int
    {
        return $this->countQuery($this->managementQuery($search, $status));
    }

    /**
     * @return Builder<User>
     */
    private function managementQuery(string $search, ?string $status): Builder
    {
        return $this->query()
            ->select(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status === 'verified', function (Builder $query): void {
                $query->whereNotNull('email_verified_at');
            })
            ->when($status === 'unverified', function (Builder $query): void {
                $query->whereNull('email_verified_at');
            });
    }
}
