<?php

namespace App\Http\Middleware;

use App\Models\Enums\TenantMemberRole;
use App\Models\User;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantAdmin
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $this->context->has()) {
            abort(403);
        }

        if ($this->context->actingAsSuperAdmin()) {
            return $next($request);
        }

        $role = $user->roleInTenant($this->context->tenant());

        if ($role !== TenantMemberRole::TenantAdmin) {
            Log::channel('tenancy_audit')->info('cross_tenant_access_denied', [
                'event' => 'cross_tenant_access_denied',
                'actor_id' => $user->getKey(),
                'acted_as' => $role?->value ?? 'member',
                'tenant_id' => $this->context->id(),
                'target_id' => $request->route()?->getName(),
                'metadata' => ['reason' => 'not_tenant_admin'],
            ]);

            abort(403);
        }

        return $next($request);
    }
}
