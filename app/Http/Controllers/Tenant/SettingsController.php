<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantSettingsRequest;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly TenantService $tenants) {}

    public function edit(): Response
    {
        $tenant = $this->tenants->findCurrent();

        return Inertia::render('tenants/settings', [
            'currentTenant' => [
                'id' => $tenant->getKey(),
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
            ],
            'canEditSlug' => false,
        ]);
    }

    public function update(UpdateTenantSettingsRequest $request): RedirectResponse
    {
        $tenant = $this->tenants->findCurrent();

        $this->tenants->updateName($tenant, $request->tenantName());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant settings updated.')]);

        return to_route('tenant.settings.edit');
    }
}
