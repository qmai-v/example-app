<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null || ! $user->isSuperAdmin()) {
            Log::channel('tenancy_audit')->info('cross_tenant_access_denied', [
                'event' => 'cross_tenant_access_denied',
                'actor_id' => $user?->getKey(),
                'acted_as' => 'member',
                'tenant_id' => null,
                'target_id' => $request->route()?->getName(),
                'metadata' => ['reason' => 'not_super_admin'],
            ]);

            abort(403);
        }

        return $next($request);
    }
}
