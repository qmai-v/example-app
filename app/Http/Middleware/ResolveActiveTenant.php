<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $sessionTenantId = $request->session()->get('active_tenant_id');
        $candidate = $sessionTenantId
            ? Tenant::query()->find($sessionTenantId)
            : null;

        $resolved = $this->resolveTenant($user, $candidate);

        if ($resolved === null) {
            $request->session()->forget('active_tenant_id');

            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            if ($request->routeIs('tenants.no-tenant', 'logout')) {
                return $next($request);
            }

            return redirect()->route('tenants.no-tenant');
        }

        $actingAsSuperAdmin = $user->isSuperAdmin() && ! $user->belongsToTenant($resolved);

        $this->context->set($resolved, $actingAsSuperAdmin);

        if ((string) $sessionTenantId !== (string) $resolved->getKey()) {
            $request->session()->put('active_tenant_id', $resolved->getKey());
        }

        if ((string) $user->last_tenant_id !== (string) $resolved->getKey()) {
            $user->forceFill(['last_tenant_id' => $resolved->getKey()])->save();
        }

        if ($actingAsSuperAdmin) {
            Log::channel('tenancy_audit')->info('super_admin_in_tenant', [
                'event' => 'super_admin_in_tenant',
                'actor_id' => $user->getKey(),
                'acted_as' => 'super_admin',
                'tenant_id' => $resolved->getKey(),
                'target_id' => null,
                'metadata' => ['path' => $request->path()],
            ]);
        }

        return $next($request);
    }

    private function resolveTenant(User $user, ?Tenant $candidate): ?Tenant
    {
        if ($candidate && $this->canUse($user, $candidate)) {
            return $candidate;
        }

        $last = $user->last_tenant_id ? Tenant::query()->find($user->last_tenant_id) : null;
        if ($last && $this->canUse($user, $last)) {
            return $last;
        }

        $earliestMembership = TenantMembership::query()
            ->where('user_id', $user->getKey())
            ->whereHas('tenant', fn ($q) => $q->where('status', 'active'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if ($earliestMembership) {
            $tenant = Tenant::query()->find($earliestMembership->tenant_id);
            if ($tenant && $this->canUse($user, $tenant)) {
                return $tenant;
            }
        }

        return null;
    }

    private function canUse(User $user, Tenant $tenant): bool
    {
        if ($tenant->trashed() || $tenant->isSuspended()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->belongsToTenant($tenant);
    }
}
