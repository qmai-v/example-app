<?php

use App\Http\Controllers\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Admin\TenantMemberController as AdminTenantMemberController;
use App\Http\Controllers\Tenant\MemberController as TenantMemberController;
use App\Http\Controllers\Tenant\SettingsController as TenantSettingsController;
use App\Http\Controllers\TenantSwitcherController;
use App\Http\Controllers\UserController;
use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::bind('tenant', function (string $value): Tenant {
    return Tenant::withTrashed()->findOrFail($value);
});

Route::inertia('/', 'welcome')->name('home');

// Authenticated, but tenant resolution intentionally NOT applied — this is the
// destination when the user has no available tenant.
Route::middleware(['auth'])->group(function () {
    Route::get('tenants/none', fn () => Inertia::render('tenants/no-tenant', [
        'reason' => 'no_membership',
    ]))->name('tenants.no-tenant');
});

Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::post('tenants/switch', [TenantSwitcherController::class, 'store'])->name('tenants.switch');

    Route::middleware('tenant-admin')->group(function () {
        Route::get('tenant/members', [TenantMemberController::class, 'index'])->name('tenant.members.index');
        Route::post('tenant/members', [TenantMemberController::class, 'store'])->name('tenant.members.store');
        Route::match(['put', 'patch'], 'tenant/members/{membership}', [TenantMemberController::class, 'update'])->name('tenant.members.update');
        Route::delete('tenant/members/{membership}', [TenantMemberController::class, 'destroy'])->name('tenant.members.destroy');

        Route::get('tenant/settings', [TenantSettingsController::class, 'edit'])->name('tenant.settings.edit');
        Route::match(['put', 'patch'], 'tenant/settings', [TenantSettingsController::class, 'update'])->name('tenant.settings.update');
    });

    Route::middleware('super-admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('admin/tenants', [AdminTenantController::class, 'index'])->name('admin.tenants.index');
        Route::post('admin/tenants', [AdminTenantController::class, 'store'])->name('admin.tenants.store');
        Route::match(['put', 'patch'], 'admin/tenants/{tenant}', [AdminTenantController::class, 'update'])->name('admin.tenants.update');
        Route::delete('admin/tenants/{tenant}', [AdminTenantController::class, 'destroy'])->name('admin.tenants.destroy');
        Route::post('admin/tenants/{tenant}/restore', [AdminTenantController::class, 'restore'])->name('admin.tenants.restore');

        Route::get('admin/tenants/{tenant}/members', [AdminTenantMemberController::class, 'index'])->name('admin.tenants.members.index');
        Route::post('admin/tenants/{tenant}/members', [AdminTenantMemberController::class, 'store'])->name('admin.tenants.members.store');
        Route::match(['put', 'patch'], 'admin/tenants/{tenant}/members/{membership}', [AdminTenantMemberController::class, 'update'])->name('admin.tenants.members.update');
        Route::delete('admin/tenants/{tenant}/members/{membership}', [AdminTenantMemberController::class, 'destroy'])->name('admin.tenants.members.destroy');
    });
});

require __DIR__.'/settings.php';
