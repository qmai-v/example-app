<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(private readonly TenantService $tenants) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->tenants->normalizePerPage(
            $request->integer('per_page', TenantService::DEFAULT_PER_PAGE),
        );
        $statusQuery = $request->query('status');
        $status = is_string($statusQuery) && in_array($statusQuery, ['active', 'suspended', 'deleted'], true)
            ? $statusQuery
            : null;

        $tenants = $this->tenants
            ->repository()
            ->paginateForAdmin($search, $status, $perPage)
            ->through(fn (Tenant $tenant): array => $this->serializeTenant($tenant));

        return Inertia::render('admin/tenants/index', [
            'tenants' => $tenants,
            'filters' => [
                'search' => $search === '' ? null : $search,
                'status' => $status,
                'per_page' => $perPage,
                'per_page_options' => TenantService::PER_PAGE_OPTIONS,
            ],
        ]);
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        /** @var User $initialAdmin */
        $initialAdmin = User::query()->findOrFail($request->validated('initial_tenant_admin_user_id'));

        $this->tenants->createTenant([
            'name' => (string) $request->validated('name'),
            'slug' => $request->validated('slug'),
        ], $initialAdmin);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant created.')]);

        return to_route('admin.tenants.index');
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->tenants->updateTenant($tenant, [
            'name' => (string) $request->validated('name'),
            'slug' => $request->validated('slug'),
            'status' => $request->validated('status'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant updated.')]);

        return to_route('admin.tenants.index');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->tenants->softDelete($tenant);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant deleted.')]);

        return to_route('admin.tenants.index');
    }

    public function restore(Tenant $tenant): RedirectResponse
    {
        $this->tenants->restore($tenant);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant restored.')]);

        return to_route('admin.tenants.index');
    }

    /**
     * @return array{id: string, name: string, slug: string, status: string, deleted_at: ?string, member_count: int, created_at: string, updated_at: string}
     */
    private function serializeTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->getKey(),
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'deleted_at' => $tenant->deleted_at?->toISOString(),
            'member_count' => (int) ($tenant->memberships_count ?? 0),
            'created_at' => $tenant->created_at->toISOString(),
            'updated_at' => $tenant->updated_at->toISOString(),
        ];
    }
}
