<?php

namespace App\Services;

use App\Exceptions\LastTenantAdminProtectedException;
use App\Exceptions\MemberAlreadyExistsException;
use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Repositories\Contracts\TenantMembershipRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TenantMembershipService extends BaseService
{
    public function __construct(
        private readonly TenantMembershipRepositoryInterface $memberships,
        private readonly TenantContext $context,
    ) {}

    /**
     * @return Collection<int, TenantMembership>
     */
    public function membershipsForUser(User $user): Collection
    {
        return $this->memberships->forUser($user);
    }

    public function assertCanUseTenant(User $user, Tenant $tenant): bool
    {
        if ($tenant->trashed() || $tenant->isSuspended()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->belongsToTenant($tenant);
    }

    public function switchTo(User $user, string $tenantId): Tenant
    {
        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null || ! $this->assertCanUseTenant($user, $tenant)) {
            throw ValidationException::withMessages([
                'tenant_id' => __('This tenant is not available.'),
            ]);
        }

        $previousTenantId = $user->last_tenant_id;

        session()->put('active_tenant_id', $tenant->getKey());
        $user->forceFill(['last_tenant_id' => $tenant->getKey()])->save();

        $actingAsSuperAdmin = $user->isSuperAdmin() && ! $user->belongsToTenant($tenant);
        $this->context->set($tenant, $actingAsSuperAdmin);

        Log::channel('tenancy_audit')->info('tenant_switched', [
            'event' => 'tenant_switched',
            'actor_id' => $user->getKey(),
            'acted_as' => $actingAsSuperAdmin ? 'super_admin' : ($user->roleInTenant($tenant)?->value ?? 'member'),
            'tenant_id' => $tenant->getKey(),
            'target_id' => $tenant->getKey(),
            'metadata' => [
                'previous_tenant_id' => $previousTenantId,
            ],
        ]);

        return $tenant;
    }

    /**
     * @return LengthAwarePaginator<int, TenantMembership>
     */
    public function paginatedMembersForCurrentTenant(string $search, ?TenantMemberRole $role, int $perPage): LengthAwarePaginator
    {
        return $this->memberships->paginateMembers(
            $this->context->tenant(),
            $search,
            $role,
            $this->normalizePerPage($perPage),
        );
    }

    public function addMember(Tenant $tenant, string $email, TenantMemberRole $role): TenantMembership
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => __('No user with that email exists.'),
            ]);
        }

        if ($this->memberships->betweenUserAndTenant($user, $tenant) !== null) {
            throw new MemberAlreadyExistsException;
        }

        $membership = DB::transaction(function () use ($user, $tenant, $role): TenantMembership {
            return TenantMembership::query()->create([
                'user_id' => $user->getKey(),
                'tenant_id' => $tenant->getKey(),
                'role' => $role->value,
            ]);
        });

        $this->audit('membership_added', $tenant, $membership, ['role' => $role->value, 'subject_user_id' => $user->getKey()]);

        return $membership;
    }

    public function changeRole(TenantMembership $membership, TenantMemberRole $role): TenantMembership
    {
        if ($membership->role === $role) {
            return $membership;
        }

        if ($membership->isTenantAdmin() && $role === TenantMemberRole::Member) {
            $this->guardLastTenantAdmin($membership);
        }

        DB::transaction(function () use ($membership, $role): void {
            $membership->forceFill(['role' => $role->value])->save();
        });

        $this->audit('membership_role_changed', $membership->tenant, $membership, [
            'role' => $role->value,
            'subject_user_id' => $membership->user_id,
        ]);

        return $membership;
    }

    public function removeMember(TenantMembership $membership): void
    {
        if ($membership->isTenantAdmin()) {
            $this->guardLastTenantAdmin($membership);
        }

        $tenant = $membership->tenant;
        $subjectUserId = $membership->user_id;

        DB::transaction(function () use ($membership): void {
            $membership->delete();
        });

        $this->audit('membership_removed', $tenant, $membership, ['subject_user_id' => $subjectUserId]);
    }

    public function lastTenantAdminMembershipId(Tenant $tenant): ?int
    {
        return $this->memberships->lastTenantAdmin($tenant)?->getKey();
    }

    public function addMemberOnTenant(Tenant $tenant, string $email, TenantMemberRole $role): TenantMembership
    {
        return $this->addMember($tenant, $email, $role);
    }

    public function changeRoleOnTenant(TenantMembership $membership, TenantMemberRole $role): TenantMembership
    {
        return $this->changeRole($membership, $role);
    }

    public function removeMemberOnTenant(TenantMembership $membership): void
    {
        $this->removeMember($membership);
    }

    public function repository(): TenantMembershipRepositoryInterface
    {
        return $this->memberships;
    }

    public function context(): TenantContext
    {
        return $this->context;
    }

    private function guardLastTenantAdmin(TenantMembership $membership): void
    {
        if ($this->memberships->countTenantAdmins($membership->tenant) <= 1) {
            throw new LastTenantAdminProtectedException;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(string $event, Tenant $tenant, TenantMembership $membership, array $metadata = []): void
    {
        $actor = auth()->user();

        Log::channel('tenancy_audit')->info($event, [
            'event' => $event,
            'actor_id' => $actor?->getKey(),
            'acted_as' => $this->context->actingAsSuperAdmin()
                ? 'super_admin'
                : ($actor instanceof User ? ($actor->roleInTenant($tenant)?->value ?? 'member') : 'member'),
            'tenant_id' => $tenant->getKey(),
            'target_id' => $membership->getKey(),
            'metadata' => $metadata,
        ]);
    }
}
