<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly TenantContext $context) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        $activeTenant = $this->context->has()
            ? $this->context->tenant()
            : $this->resolveSharedActiveTenant($request, $user);
        $actingAsSuperAdmin = $user !== null
            && $activeTenant !== null
            && ($this->context->actingAsSuperAdmin() || ($user->isSuperAdmin() && ! $user->belongsToTenant($activeTenant)));
        $tenantRole = $user !== null && $activeTenant !== null && ! $actingAsSuperAdmin
            ? $user->roleInTenant($activeTenant)
            : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'isSuperAdmin' => $user?->isSuperAdmin() ?? false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'tenant' => [
                'active' => $activeTenant ? $this->serializeTenant($activeTenant) : null,
                'role' => $tenantRole?->value,
                'actingAsSuperAdmin' => $actingAsSuperAdmin,
                'available' => fn () => $user ? $this->availableTenantsFor($user) : [],
            ],
        ];
    }

    private function resolveSharedActiveTenant(Request $request, ?User $user): ?Tenant
    {
        if ($user === null) {
            return null;
        }

        $sessionTenantId = $request->session()->get('active_tenant_id');
        $sessionTenant = $sessionTenantId
            ? Tenant::query()->find($sessionTenantId)
            : null;

        if ($sessionTenant !== null && $this->canUseSharedTenant($user, $sessionTenant)) {
            return $sessionTenant;
        }

        $lastTenant = $user->last_tenant_id
            ? Tenant::query()->find($user->last_tenant_id)
            : null;

        if ($lastTenant !== null && $this->canUseSharedTenant($user, $lastTenant)) {
            return $lastTenant;
        }

        /** @var TenantMembership|null $membership */
        $membership = $user->memberships()
            ->with('tenant')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        return $membership?->tenant;
    }

    private function canUseSharedTenant(User $user, Tenant $tenant): bool
    {
        if ($tenant->trashed() || ! $tenant->isActive()) {
            return false;
        }

        return $user->isSuperAdmin() || $user->belongsToTenant($tenant);
    }

    /**
     * @return array<int, array{id: string, name: string, slug: string, role: string}>
     */
    private function availableTenantsFor(User $user): array
    {
        if ($user->isSuperAdmin()) {
            $membershipsByTenant = $user->memberships()
                ->get()
                ->keyBy('tenant_id');

            return Tenant::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->map(fn (Tenant $tenant): array => [
                    'id' => $tenant->getKey(),
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'role' => $membershipsByTenant->get($tenant->getKey())?->role->value ?? 'super_admin',
                ])
                ->values()
                ->all();
        }

        return $user->memberships()
            ->with('tenant')
            ->get()
            ->filter(fn (TenantMembership $membership): bool => $membership->tenant !== null
                && $membership->tenant->isActive()
            )
            ->map(fn (TenantMembership $membership): array => [
                'id' => $membership->tenant->getKey(),
                'name' => $membership->tenant->name,
                'slug' => $membership->tenant->slug,
                'role' => $membership->role->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, name: string, slug: string, status: string}
     */
    private function serializeTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->getKey(),
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
        ];
    }
}
