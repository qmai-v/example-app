# Quickstart: Multi-Tenant Architecture

## Prerequisites

- Use the feature branch `002-multi-tenancy`.
- Review [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), and the four contract files in [contracts/](./contracts/).
- Bring up the default Postgres service: `docker compose up -d postgres`.
- Before any implementation step that touches Laravel/Inertia/Wayfinder, use Laravel Boost `search-docs` when available for:
  - Eloquent global scopes
  - Fortify `LoginResponse` extension
  - Inertia v3 shared props and `Inertia::optional()`
  - Wayfinder regeneration
  - PostgreSQL UUID columns + CHECK constraints

## Implementation outline (high-level — `/speckit-tasks` will explode this into ordered steps)

1. **Migrations & models**
   - `php artisan make:migration create_tenants_table --no-interaction`
   - `php artisan make:migration create_tenant_memberships_table --no-interaction`
   - `php artisan make:migration add_tenancy_columns_to_users_table --no-interaction`
   - `php artisan make:model Tenant --no-interaction` (UUID PK, `SoftDeletes`, `casts` for `status`)
   - `php artisan make:model TenantMembership --no-interaction` (custom pivot model)
   - `php artisan make:factory TenantFactory --no-interaction`
   - `php artisan make:factory TenantMembershipFactory --no-interaction`
   - Add the `App\Enums\TenantMemberRole` PHP enum (file in `app/Enums/` — subdir of an existing base dir? No, `app/Enums/` would be a new base dir. Keep the enum in `app/Models/Enums/TenantMemberRole.php` to stay within `app/Models/`).
   - Run `php artisan migrate` against Postgres.

2. **Tenancy runtime**
   - Implement `app/Services/TenantContext.php` (singleton, exposes `has/set/tenant/id/clear/actingAsSuperAdmin`).
   - Implement `app/Models/Scopes/TenantScope.php` (strict mode — throws `MissingTenantContextException` when no context).
   - Implement `app/Models/Concerns/BelongsToTenant.php` (trait that wires the scope + creating hook).
   - `php artisan make:middleware ResolveActiveTenant --no-interaction`
   - `php artisan make:middleware RequireSuperAdmin --no-interaction`
   - `php artisan make:middleware RequireTenantAdmin --no-interaction`
   - Register `ResolveActiveTenant` on the authenticated route groups; register the two role middlewares on the respective groups.
   - Add `Gate::define('super-admin', ...)` in `AppServiceProvider::boot()`.

3. **Repositories + services + Inertia plumbing**
   - `php artisan make:class Repositories/TenantRepository --no-interaction` (or hand-write to match the existing pattern — see `UserRepository`).
   - Same for `TenantMembershipRepository` and the two interface contracts under `app/Repositories/Contracts/`.
   - Bind both interfaces in `AppServiceProvider::register()` alongside the existing `UserRepositoryInterface` binding.
   - Implement `app/Services/TenantService.php` and `app/Services/TenantMembershipService.php`, each extending `BaseService`.
   - Update `app/Http/Middleware/HandleInertiaRequests.php` to share the keys listed in [contracts/tenancy-runtime.md](./contracts/tenancy-runtime.md#inertia-shared-props). The `tenant.available` key uses `Inertia::optional()` for users with ≥ 2 memberships.

4. **Sign-in restore**
   - Implement `app/Http/Responses/TenantAwareLoginResponse.php` (implements `Laravel\Fortify\Contracts\LoginResponse`).
   - In `FortifyServiceProvider::register()`, add `app->singleton(LoginResponse::class, TenantAwareLoginResponse::class)`.

5. **Routes + controllers + form requests**
   - Add the route groups described in the four contract files to `routes/web.php`. Verify with `php artisan route:list --except-vendor --path=tenants` and `php artisan route:list --except-vendor --path=admin`.
   - `php artisan make:controller TenantSwitcherController --no-interaction`
   - `php artisan make:controller Tenant/MemberController --no-interaction`
   - `php artisan make:controller Tenant/SettingsController --no-interaction`
   - `php artisan make:controller Admin/TenantController --no-interaction`
   - `php artisan make:controller Admin/TenantMemberController --no-interaction`
   - Generate Form Requests with `php artisan make:request` for each one listed in [data-model.md](./data-model.md#form-submission-shapes).
   - Wrap the existing `users.*` routes in `RequireSuperAdmin` middleware (research decision).
   - Restart the dev server so Wayfinder regenerates `@/actions/...` and `@/routes/...` bindings.

6. **Inertia pages and shared components**
   - Build `resources/js/pages/tenants/no-tenant.tsx`.
   - Build `resources/js/pages/tenants/members/index.tsx` and `resources/js/pages/tenants/settings.tsx` using `resource-index-layout`, `generic-table`, `app-dialog`, `confirmation-dialog`, `app-select`, and existing form input primitives.
   - Build `resources/js/pages/admin/tenants/index.tsx` and `resources/js/pages/admin/tenants/members.tsx`.
   - Build `resources/js/components/tenant-switcher.tsx` and `resources/js/components/super-admin-banner.tsx`. Mount the switcher in the existing app sidebar/header; mount the banner in `app-layout.tsx` between the breadcrumbs and the page content (only when `tenant.actingAsSuperAdmin` is true).

7. **Audit log channel**
   - Add a `tenancy_audit` channel to `config/logging.php` (daily, retains 14 days by default).
   - Emit the events listed in [data-model.md → Tenancy Audit Event](./data-model.md#tenancy-audit-event) from the service layer and middlewares.

8. **Test fixtures + Pest tests**
   - Create `tests/Fixtures/TenantScopedTestModel.php` and its dedicated migration in `tests/Fixtures/` (loaded in tests only via `loadMigrationsFrom()` inside a test setUp helper).
   - Generate the test files with `php artisan make:test --pest <Name>` and place them under `tests/Feature/Tenancy/`:
     - `TenantScopeIsolationTest`
     - `TenantSwitcherTest`
     - `LoginRestoresLastTenantTest`
     - `TenantAdminMemberManagementTest`
     - `SuperAdminTenantManagementTest`
     - `SuperAdminInTenantTest`
     - `TenancyAccessDenialTest`
     - `TenancyAuditLogTest`

9. **Seeder**
   - Update `database/seeders/DatabaseSeeder.php` to:
     - Create a super-admin user (idempotent — check email before insert).
     - Create one demo tenant with one tenant admin and one member, so the dev environment is workable immediately.

## Verification — narrowest commands per layer

```bash
# Postgres up + fresh schema
docker compose up -d postgres
php artisan migrate:fresh --seed

# Routes & gates exist and resolve
php artisan route:list --except-vendor --path=tenants
php artisan route:list --except-vendor --path=admin

# Tenancy feature tests
php artisan test --compact --filter=Tenancy

# Format / static checks
vendor/bin/pint --dirty --format agent
npm run types:check
npm run lint:check
npm run format:check

# Full CI parity (run before requesting review)
composer ci:check
```

## Manual smoke flow

After the above passes:

```bash
composer dev    # server + queue + Pail + Vite
```

1. Sign in as the seeded super admin → expect to land in the demo tenant; super-admin banner visible because the super admin is not a member.
2. Open `/admin/tenants` → create a second tenant, assign a new initial tenant admin.
3. Sign out, sign back in as the tenant admin → expect to land directly in that tenant (last-tenant restore), no banner.
4. Open `/tenant/members` → add a member by email, change a role, try to demote the last admin → expect 422 with a clear message.
5. As a member of two tenants, use the sidebar switcher to swap tenants → expect the page to reload with the new tenant's data and the active-tenant label to update.
6. As any non-super-admin, try to visit `/admin/tenants` directly → expect 403.

## Troubleshooting

- **"MissingTenantContextException" on a page that should be tenant-aware**: the `ResolveActiveTenant` middleware is not on that route group. Add it.
- **Switcher dropdown shows a skeleton forever**: `Inertia::optional('tenant.available', ...)` isn't returning. Confirm `HandleInertiaRequests::share` includes the closure and that the user has ≥ 2 memberships.
- **Super-admin write inside a tenant logs as `member`**: `TenantContext::actingAsSuperAdmin()` isn't being set. Check `ResolveActiveTenant` super-admin branch.
- **Wayfinder bindings missing for new routes**: restart `npm run dev` (or `composer dev`) so the Vite plugin regenerates `resources/js/wayfinder/` files.
