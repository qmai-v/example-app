<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService extends BaseService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * @return LengthAwarePaginator<int, array{id: int, name: string, email: string, email_verified_at: ?string, created_at: string, updated_at: string}>
     */
    public function paginatedManagementList(string $search, ?string $status = null, int $perPage = self::DEFAULT_PER_PAGE): LengthAwarePaginator
    {
        return $this->users
            ->paginateForManagement($search, $this->normalizeStatus($status), $this->normalizePerPage($perPage))
            ->through(fn (User $user): array => $this->serializeUser($user));
    }

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function createUser(array $attributes): User
    {
        /** @var User $user */
        $user = $this->users->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => Hash::make($attributes['password']),
        ]);

        return $user;
    }

    /**
     * @param  array{name: string, email: string}  $attributes
     */
    public function updateUser(User $user, array $attributes): User
    {
        if ($attributes['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        /** @var User $updatedUser */
        $updatedUser = $this->users->update($user, $attributes);

        return $updatedUser;
    }

    public function deleteUser(User $user, User $currentUser): bool
    {
        if ($currentUser->is($user)) {
            return false;
        }

        $this->users->delete($user);

        return true;
    }

    public function lastManagementPage(string $search, ?string $status = null, int $perPage = self::DEFAULT_PER_PAGE): int
    {
        return $this->lastPageFromTotal(
            $this->users->countForManagement($search, $this->normalizeStatus($status)),
            $this->normalizePerPage($perPage),
        );
    }

    public function normalizePerPage(int $perPage): int
    {
        return in_array($perPage, self::PER_PAGE_OPTIONS, true)
            ? $perPage
            : self::DEFAULT_PER_PAGE;
    }

    public function normalizeStatus(?string $status): ?string
    {
        return in_array($status, ['verified', 'unverified'], true)
            ? $status
            : null;
    }

    /**
     * @return array{id: int, name: string, email: string, email_verified_at: ?string, created_at: string, updated_at: string}
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'created_at' => $user->created_at->toISOString(),
            'updated_at' => $user->updated_at->toISOString(),
        ];
    }
}
