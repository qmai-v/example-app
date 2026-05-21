<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\LastTenantAdminProtectedException;
use App\Exceptions\MemberAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreMemberRequest;
use App\Http\Requests\Tenant\UpdateMemberRequest;
use App\Models\Enums\TenantMemberRole;
use App\Models\TenantMembership;
use App\Services\TenantMembershipService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function __construct(
        private readonly TenantMembershipService $memberships,
        private readonly TenantService $tenants,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->memberships->normalizePerPage($request->integer('per_page', TenantMembershipService::DEFAULT_PER_PAGE));
        $roleQuery = $request->query('role');
        $role = is_string($roleQuery) ? TenantMemberRole::tryFrom($roleQuery) : null;

        $tenant = $this->tenants->findCurrent();

        return Inertia::render('tenants/members/index', [
            'members' => $this->memberships
                ->paginatedMembersForCurrentTenant($search, $role, $perPage)
                ->through(fn (TenantMembership $membership): array => $this->serializeMembership($membership)),
            'filters' => [
                'search' => $search === '' ? null : $search,
                'role' => $role?->value,
                'per_page' => $perPage,
                'per_page_options' => TenantMembershipService::PER_PAGE_OPTIONS,
            ],
            'lastTenantAdminMembershipId' => $this->memberships->lastTenantAdminMembershipId($tenant),
        ]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        $tenant = $this->tenants->findCurrent();

        try {
            $this->memberships->addMember($tenant, $request->email(), $request->memberRole());
        } catch (MemberAlreadyExistsException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member added.')]);

        return to_route('tenant.members.index');
    }

    public function update(UpdateMemberRequest $request, TenantMembership $membership): RedirectResponse
    {
        $this->assertBelongsToCurrentTenant($membership);

        try {
            $this->memberships->changeRole($membership, $request->memberRole());
        } catch (LastTenantAdminProtectedException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('tenant.members.index');
    }

    public function destroy(TenantMembership $membership): RedirectResponse
    {
        $this->assertBelongsToCurrentTenant($membership);

        try {
            $this->memberships->removeMember($membership);
        } catch (LastTenantAdminProtectedException $e) {
            return back()->withErrors(['membership' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('tenant.members.index');
    }

    private function assertBelongsToCurrentTenant(TenantMembership $membership): void
    {
        if ((string) $membership->tenant_id !== (string) $this->tenants->findCurrent()->getKey()) {
            abort(404);
        }
    }

    /**
     * @return array{id: int, user: array{id: int, name: string, email: string}, role: string, created_at: string}
     */
    private function serializeMembership(TenantMembership $membership): array
    {
        return [
            'id' => $membership->getKey(),
            'user' => [
                'id' => $membership->user->getKey(),
                'name' => $membership->user->name,
                'email' => $membership->user->email,
            ],
            'role' => $membership->role->value,
            'created_at' => $membership->created_at->toISOString(),
        ];
    }
}
