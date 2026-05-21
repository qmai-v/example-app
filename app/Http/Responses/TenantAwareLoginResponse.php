<?php

namespace App\Http\Responses;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantMembershipService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class TenantAwareLoginResponse implements LoginResponseContract, Responsable
{
    public function __construct(private readonly TenantMembershipService $memberships) {}

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tenant = $this->resolveLoginTenant($user);

        if ($tenant === null) {
            $request->session()->forget('active_tenant_id');

            Log::channel('tenancy_audit')->info('login_no_tenant', [
                'event' => 'login_no_tenant',
                'actor_id' => $user->getKey(),
                'acted_as' => $user->isSuperAdmin() ? 'super_admin' : 'member',
                'tenant_id' => null,
                'target_id' => null,
                'metadata' => [],
            ]);

            if ($request->wantsJson()) {
                return new JsonResponse('', 204);
            }

            return redirect()->route('tenants.no-tenant');
        }

        $request->session()->put('active_tenant_id', $tenant->getKey());

        if ((string) $user->last_tenant_id !== (string) $tenant->getKey()) {
            $user->forceFill(['last_tenant_id' => $tenant->getKey()])->save();
        }

        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()->intended(config('fortify.home', '/dashboard'));
    }

    private function resolveLoginTenant(User $user): ?Tenant
    {
        $last = $user->last_tenant_id ? Tenant::query()->find($user->last_tenant_id) : null;
        if ($last && $this->memberships->assertCanUseTenant($user, $last)) {
            $this->audit('login_tenant_restored', $user, $last);

            return $last;
        }

        $fallback = $this->earliestAvailableMembershipTenant($user);
        if ($fallback) {
            $this->audit('login_tenant_fallback', $user, $fallback, [
                'previous_last_tenant_id' => $user->last_tenant_id,
            ]);

            return $fallback;
        }

        if ($user->isSuperAdmin()) {
            $first = Tenant::query()
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->orderBy('created_at')
                ->first();

            if ($first) {
                $this->audit('login_tenant_fallback', $user, $first, [
                    'previous_last_tenant_id' => $user->last_tenant_id,
                    'super_admin_default' => true,
                ]);

                return $first;
            }
        }

        return null;
    }

    private function earliestAvailableMembershipTenant(User $user): ?Tenant
    {
        $membership = $user->memberships()
            ->with('tenant')
            ->whereHas('tenant', fn ($q) => $q->where('status', 'active'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        return $membership?->tenant;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(string $event, User $user, Tenant $tenant, array $metadata = []): void
    {
        Log::channel('tenancy_audit')->info($event, [
            'event' => $event,
            'actor_id' => $user->getKey(),
            'acted_as' => $user->isSuperAdmin() && ! $user->belongsToTenant($tenant) ? 'super_admin' : ($user->roleInTenant($tenant)?->value ?? 'member'),
            'tenant_id' => $tenant->getKey(),
            'target_id' => $tenant->getKey(),
            'metadata' => $metadata,
        ]);
    }
}
