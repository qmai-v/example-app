<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\LastTenantAdminProtectedException;
use App\Exceptions\MemberAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantMemberRequest;
use App\Http\Requests\Admin\UpdateTenantMemberRequest;
use App\Models\Enums\TenantMemberRole;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Services\TenantMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantMemberController extends Controller
{
    public function __construct(private readonly TenantMembershipService $memberships) {}

    public function index(Request $request, Tenant $tenant): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->memberships->normalizePerPage(
            $request->integer('per_page', TenantMembershipService::DEFAULT_PER_PAGE),
        );
        $roleQuery = $request->query('role');
        $role = is_string($roleQuery) ? TenantMemberRole::tryFrom($roleQuery) : null;

        $members = $this->memberships
            ->repository()
            ->paginateMembers($tenant, $search, $role, $perPage)
            ->through(fn (TenantMembership $membership): array => [
                'id' => $membership->getKey(),
                'user' => [
                    'id' => $membership->user->getKey(),
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                ],
                'role' => $membership->role->value,
                'created_at' => $membership->created_at->toISOString(),
            ]);

        return Inertia::render('admin/tenants/members', [
            'targetTenant' => [
                'id' => $tenant->getKey(),
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'deleted_at' => $tenant->deleted_at?->toISOString(),
            ],
            'members' => $members,
            'filters' => [
                'search' => $search === '' ? null : $search,
                'role' => $role?->value,
                'per_page' => $perPage,
                'per_page_options' => TenantMembershipService::PER_PAGE_OPTIONS,
            ],
            'lastTenantAdminMembershipId' => $this->memberships->lastTenantAdminMembershipId($tenant),
        ]);
    }

    public function store(StoreTenantMemberRequest $request, Tenant $tenant): RedirectResponse
    {
        try {
            $this->memberships->addMemberOnTenant($tenant, $request->memberUserAttributes(), $request->memberRole());
        } catch (MemberAlreadyExistsException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member added.')]);

        return to_route('admin.tenants.members.index', $tenant);
    }

    public function update(UpdateTenantMemberRequest $request, Tenant $tenant, TenantMembership $membership): RedirectResponse
    {
        $this->assertMembershipBelongsToTenant($membership, $tenant);

        try {
            $this->memberships->changeRoleOnTenant($membership, $request->memberRole());
        } catch (LastTenantAdminProtectedException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('admin.tenants.members.index', $tenant);
    }

    public function destroy(Tenant $tenant, TenantMembership $membership): RedirectResponse
    {
        $this->assertMembershipBelongsToTenant($membership, $tenant);

        try {
            $this->memberships->removeMemberOnTenant($membership);
        } catch (LastTenantAdminProtectedException $e) {
            return back()->withErrors(['membership' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('admin.tenants.members.index', $tenant);
    }

    private function assertMembershipBelongsToTenant(TenantMembership $membership, Tenant $tenant): void
    {
        if ((string) $membership->tenant_id !== (string) $tenant->getKey()) {
            abort(404);
        }
    }
}
