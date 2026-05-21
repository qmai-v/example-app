<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<User>
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateForManagement(string $search, ?string $status, int $perPage = 10): LengthAwarePaginator;

    public function countForManagement(string $search, ?string $status): int;
}
