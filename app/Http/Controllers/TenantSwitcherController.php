<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenant\SwitchTenantRequest;
use App\Models\User;
use App\Services\TenantMembershipService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TenantSwitcherController extends Controller
{
    public function __construct(private readonly TenantMembershipService $memberships) {}

    public function store(SwitchTenantRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tenant = $this->memberships->switchTo($user, $request->tenantId());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Active tenant updated.'),
        ]);

        return to_route('dashboard');
    }
}
