# Implementation Plan: Multi-Tenant Architecture

**Branch**: `002-multi-tenancy` | **Date**: 2026-05-21 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-multi-tenancy/spec.md`

**Note**: This plan is filled in by the `/speckit-plan` command. See `.specify/templates/plan-template.md` for the execution workflow.

## Summary

Introduce shared-database, shared-table multi-tenancy by adding a `Tenant` model with a UUID-keyed surrogate, a `TenantMembership` pivot carrying a per-tenant role (`tenant_admin` or `member`), and a session-tracked active tenant. A new `TenantScope` global Eloquent scope plus a `BelongsToTenant` trait scope every tenant-scoped read to the active tenant and stamp `tenant_id` on writes — the trait is shipped as infrastructure now; existing features (e.g. user management) adopt it in later slices. A `ResolveActiveTenant` middleware enforces presence/freshness of the active tenant on every authenticated request, with deterministic fallback rules when the most-recently-used tenant becomes unavailable. Sign-in restores the user's last tenant via a Fortify `LoginResponse` override. The UI shares an active-tenant banner + switcher via Inertia shared props, ships a no-tenant landing page, a tenant-admin Members/Settings area, and a super-admin tenant administration area. All work flows through the existing Controller → Service → Repository → Eloquent layering and uses Wayfinder bindings for client routing.

## Technical Context

**Language/Version**: PHP 8.4, TypeScript 5.7, React 19

**Primary Dependencies**: Laravel 13, Fortify 1, Inertia Laravel 3, Inertia React 3, Wayfinder 0.x, Tailwind CSS 4, existing Radix-based UI primitives in `resources/js/components/ui/`, Pest 4, Pint 1, ESLint 9, Prettier 3, Laravel Boost 2

**Storage**: PostgreSQL 16 (via `docker-compose.yml` service `postgres`, database `example_app`); SQLite remains acceptable for any test that already relies on it. New tables: `tenants`, `tenant_memberships`. New columns on `users`: `is_super_admin` (boolean, default false), `last_tenant_id` (nullable foreign key → `tenants.id`).

**Testing**: Pest 4 feature tests under `tests/Feature/Tenancy/`, with a dedicated `TenantScopedTestModel` fixture under `tests/Fixtures/` to prove the global-scope behaviour without coupling to any production model. Frontend type/lint/format checks via `npm run types:check`, `npm run lint:check`, `npm run format:check`. Full CI parity via `composer ci:check`.

**Target Platform**: Laravel 13 web application served as an Inertia React SPA. Same runtime as the existing app — no new processes.

**Project Type**: Web application infrastructure feature (tenancy boundary + admin UI) that ships alongside the existing single-tenant features.

**Performance Goals**: Tenant switching round-trip under 2 s (SC-002) under normal load; sign-in adds at most one extra query (membership + last-tenant lookup) on top of the existing Fortify flow. The global scope adds a single indexed `WHERE tenant_id = ?` to tenant-scoped queries — kept negligible by an index on every `tenant_id` column.

**Constraints**:

- Shared database, shared tables (per spec) — no per-tenant database or schema.
- Tenant identity is a server-side session concern; tenant ids in URLs or client state MUST NOT be trusted as a security boundary.
- The global scope MUST refuse to run when no active tenant is resolved (strict mode), with explicit `withoutGlobalScope(TenantScope::class)` bypasses for super-admin admin-area queries and Fortify pre-login flows. This is the only acceptable bypass surface.
- No new base directories in `app/` or `resources/js/` and no new runtime dependencies in `composer.json` / `package.json`.
- Routes MUST be named and resolved via `route()` / `to_route()` server-side and Wayfinder imports client-side.
- Inertia v3: `Inertia::optional()`, `router.cancelAll()`, built-in XHR client. No Axios, no `Inertia::lazy()`, no `router.cancel()`.
- Soft-deleted tenants MUST remain visible to super admins and recoverable; regular flows MUST NOT surface them.

**Scale/Scope**: This slice ships the tenancy infrastructure plus three user-facing surfaces (no-tenant state, tenant-admin Members/Settings, super-admin Tenant administration), Fortify login override, Inertia shared-prop additions, plus full Pest coverage. Existing `users` page remains operational but is restricted to super admins in this slice (the global user list is now a super-admin concept). All other existing pages are unchanged.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **I. Laravel-first architecture**: Use Artisan generators (`make:controller`, `make:request`, `make:model -mfs`, `make:middleware`, `make:test --pest`, `make:migration`) with `--no-interaction` for every new artefact. Routes declared by name in `routes/web.php`; URLs resolved via `route()` / `to_route()` server-side and `@/routes/...` / `@/actions/...` Wayfinder bindings client-side. Authentication remains Fortify; the sign-in tenant restore is implemented by overriding `Laravel\Fortify\Contracts\LoginResponse` (the documented extension point). Authorization uses route middleware (`RequireSuperAdmin`, `RequireTenantAdmin`) and `Gate::define()` registered in `AppServiceProvider::boot()` for `super-admin` and `tenant-admin` — Laravel-native primitives, no bespoke ACL.
  - PASS.

- **II. Layered service + repository pattern**: Every user-visible action flows Controller → Service → Repository → Eloquent. Mirrors the existing `UserController` / `UserService` / `UserRepository` / `UserRepositoryInterface` triad. New triads:
  - `TenantController` (super-admin) / `TenantService` / `TenantRepository` / `TenantRepositoryInterface`
  - `Tenant\MemberController` (tenant-admin in current tenant) / `TenantMembershipService` / `TenantMembershipRepository` / `TenantMembershipRepositoryInterface`
  - `Admin\TenantMemberController` (super-admin on any tenant) reuses `TenantMembershipService`/`TenantMembershipRepository`
  - `TenantSwitcherController` reuses `TenantMembershipService` to validate and switch
  - Controllers stay thin (request parsing → service call → `Inertia::render` or `to_route`).
  - Services extend `BaseService` and reuse `DEFAULT_PER_PAGE` / `PER_PAGE_OPTIONS`. Repositories extend `BaseRepository<TModel>` and implement typed contracts under `app/Repositories/Contracts/`. Bindings added to `AppServiceProvider::register()` alongside the existing `UserRepositoryInterface` binding.
  - PASS.

- **III. Inertia React contracts**: New pages live under `resources/js/pages/`:
  - `resources/js/pages/tenants/no-tenant.tsx` (no-tenant landing)
  - `resources/js/pages/tenants/members/index.tsx` (current-tenant member management, tenant-admin)
  - `resources/js/pages/tenants/settings.tsx` (current-tenant settings, tenant-admin)
  - `resources/js/pages/admin/tenants/index.tsx` (super-admin tenant list + dialogs for create/edit/suspend/delete/restore)
  - `resources/js/pages/admin/tenants/members.tsx` (super-admin member management for a selected tenant)
  - All rendered through `Inertia::render()`. Reuse `generic-table`, `resource-index-layout`, `confirmation-dialog`, `app-dialog`, `app-select`, and form input primitives. New shared components: `resources/js/components/tenant-switcher.tsx` (rendered in the sidebar or app header) and `resources/js/components/super-admin-banner.tsx` (shown when a super admin is acting inside a tenant they don't belong to). Wayfinder bindings under `@/routes/tenants`, `@/routes/admin/tenants`, `@/actions/...` are imported, not hand-built.
  - No Inertia v3-removed APIs are introduced.
  - PASS.

- **IV. Programmatic testing**: New Pest feature tests under `tests/Feature/Tenancy/`:
  - `TenantScopeIsolationTest.php` — proves `TenantScope` filters reads, stamps writes, and treats out-of-tenant ids as not-found (uses `TenantScopedTestModel` fixture).
  - `TenantSwitcherTest.php` — proves switching is allowed for current members only, updates session and `users.last_tenant_id`, rejects switches to suspended/foreign tenants.
  - `LoginRestoresLastTenantTest.php` — proves Fortify post-login resolves the active tenant per FR-010 / FR-011 / FR-012 and the no-tenant fallback (FR-013).
  - `TenantAdminMemberManagementTest.php` — proves tenant admins can add/remove/role-change members in their own tenant only, with last-tenant-admin protection.
  - `SuperAdminTenantManagementTest.php` — proves super-admin CRUD, soft-delete, restore (data + memberships + roles recovered), and create-requires-initial-tenant-admin.
  - `SuperAdminInTenantTest.php` — proves a super admin can set any tenant active, gain tenant-admin-equivalent capabilities, and that audit attribution records super-admin context.
  - `TenancyAccessDenialTest.php` — proves access-denial for non-super-admin/non-tenant-admin requests across every protected route and that suspended/deleted tenants don't appear in switchers.
  - `TenancyAuditLogTest.php` — proves the security log captures the events listed in FR-022.
  - Minimum verification command set per the constitution: `php artisan test --compact --filter=Tenancy`, `vendor/bin/pint --dirty --format agent`, `npm run types:check`, `npm run lint:check`, `npm run format:check`. Full CI parity: `composer ci:check`.
  - PASS.

- **V. Existing structure and dependencies are stable**: All new code lands inside existing base directories:
  - `app/Http/Controllers/{Admin,Tenant}/` — subdirectories of an existing base dir.
  - `app/Http/Requests/{Admin,Tenant}/` — subdirectories of the existing requests dir.
  - `app/Http/Middleware/` — adds three new middleware files in the existing dir.
  - `app/Models/` — adds `Tenant.php`, `TenantMembership.php`; adds `app/Models/Scopes/TenantScope.php` and `app/Models/Concerns/BelongsToTenant.php` (subdirectories of an existing base dir).
  - `app/Services/` — adds `TenantService.php`, `TenantMembershipService.php`, `TenantContext.php` (a request-scoped singleton holding the active tenant). All three extend or sit alongside `BaseService`.
  - `app/Repositories/` and `app/Repositories/Contracts/` — adds repositories and contracts following the existing pattern.
  - `app/Http/Responses/TenantAwareLoginResponse.php` — `app/Http/Responses` is a subdirectory of an existing base dir.
  - `app/Listeners/` is NOT introduced; the Fortify hook is wired by binding `LoginResponse::class` in `FortifyServiceProvider`.
  - `database/{migrations,factories,seeders}/` — adds three migrations, two factories, optional seeder updates.
  - `resources/js/{pages,components,routes,actions}/` — pages and components in existing dirs; Wayfinder regenerates the rest.
  - `tests/{Feature,Fixtures}/` — `tests/Feature/Tenancy/` is a subdirectory of an existing base dir; `tests/Fixtures/` is a new subdirectory under `tests/` (an existing base dir) used only for the test-fixture model and its migration. Both are subdirectories of an existing base dir and do not introduce a new top-level layout.
  - No `composer.json` or `package.json` runtime dependency changes.
  - PASS.

- **VI. Tooling, formatting, and observability**: Use Laravel Boost MCP `search-docs` for Eloquent global scopes, Fortify `LoginResponse`, Inertia v3 shared props, and Wayfinder regeneration when available; `database-schema` to confirm the planned migrations match Postgres conventions; `database-query` to spot-check tenancy isolation in development. Use `php artisan route:list --except-vendor` to verify the new named routes after each change. PHP changes formatted with `vendor/bin/pint --dirty --format agent`. Frontend changes pass `npm run lint:check`, `npm run format:check`, `npm run types:check`. Use `browser-logs` if Inertia page rendering misbehaves locally. Audit log writes (FR-022) go through Laravel's logging façade so they appear in Pail.
  - PASS.

## Project Structure

### Documentation (this feature)

```text
specs/002-multi-tenancy/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
│   ├── tenant-switching.md
│   ├── tenant-admin-area.md
│   ├── super-admin-tenants.md
│   └── tenancy-runtime.md
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── TenantSwitcherController.php
│   │   ├── Tenant/
│   │   │   ├── MemberController.php
│   │   │   └── SettingsController.php
│   │   └── Admin/
│   │       ├── TenantController.php
│   │       └── TenantMemberController.php
│   ├── Middleware/
│   │   ├── ResolveActiveTenant.php
│   │   ├── RequireSuperAdmin.php
│   │   └── RequireTenantAdmin.php
│   ├── Requests/
│   │   ├── Tenant/
│   │   │   ├── SwitchTenantRequest.php
│   │   │   ├── StoreMemberRequest.php
│   │   │   ├── UpdateMemberRequest.php
│   │   │   └── UpdateTenantSettingsRequest.php
│   │   └── Admin/
│   │       ├── StoreTenantRequest.php
│   │       ├── UpdateTenantRequest.php
│   │       ├── StoreTenantMemberRequest.php
│   │       └── UpdateTenantMemberRequest.php
│   └── Responses/
│       └── TenantAwareLoginResponse.php
├── Models/
│   ├── Tenant.php
│   ├── TenantMembership.php
│   ├── Concerns/
│   │   └── BelongsToTenant.php
│   └── Scopes/
│       └── TenantScope.php
├── Providers/
│   ├── AppServiceProvider.php          # add repository bindings + gates
│   └── FortifyServiceProvider.php      # bind LoginResponse::class
├── Repositories/
│   ├── TenantRepository.php
│   ├── TenantMembershipRepository.php
│   └── Contracts/
│       ├── TenantRepositoryInterface.php
│       └── TenantMembershipRepositoryInterface.php
└── Services/
    ├── TenantContext.php
    ├── TenantService.php
    └── TenantMembershipService.php

database/
├── factories/
│   ├── TenantFactory.php
│   └── TenantMembershipFactory.php
├── migrations/
│   ├── 2026_05_21_000001_create_tenants_table.php
│   ├── 2026_05_21_000002_create_tenant_memberships_table.php
│   └── 2026_05_21_000003_add_tenancy_columns_to_users_table.php
└── seeders/
    └── DatabaseSeeder.php              # seed a default super admin + demo tenant

resources/js/
├── components/
│   ├── tenant-switcher.tsx
│   └── super-admin-banner.tsx
├── layouts/
│   └── app/                            # existing; tenant-switcher mounted in app shell
└── pages/
    ├── tenants/
    │   ├── no-tenant.tsx
    │   ├── members/
    │   │   └── index.tsx
    │   └── settings.tsx
    └── admin/
        └── tenants/
            ├── index.tsx
            └── members.tsx

routes/
└── web.php                             # add tenant + admin route groups

tests/
├── Feature/
│   └── Tenancy/
│       ├── TenantScopeIsolationTest.php
│       ├── TenantSwitcherTest.php
│       ├── LoginRestoresLastTenantTest.php
│       ├── TenantAdminMemberManagementTest.php
│       ├── SuperAdminTenantManagementTest.php
│       ├── SuperAdminInTenantTest.php
│       ├── TenancyAccessDenialTest.php
│       └── TenancyAuditLogTest.php
└── Fixtures/
    ├── TenantScopedTestModel.php
    └── 2026_05_21_000099_create_tenant_scoped_test_models_table.php
```

**Structure Decision**: Implement within the existing Laravel/Inertia application layout. Controllers and requests live under namespaced subdirectories (`Admin/`, `Tenant/`) inside the existing base dirs; the tenancy scope and trait live under `app/Models/{Scopes,Concerns}/` (subdirs of `app/Models/`); the runtime tenancy singleton (`TenantContext`) is a service in `app/Services/`. The Fortify `LoginResponse` override lives in `app/Http/Responses/` and is bound from `FortifyServiceProvider::register()`. Tests are organised under `tests/Feature/Tenancy/`, with a dedicated test-fixture model and migration under `tests/Fixtures/`. No new top-level directories are introduced.

## Phase 0: Research

Completed in [research.md](./research.md). Key decisions:

- Tenant primary key is UUID (`Str::uuid()`), exposed as the only id in URLs and props; this removes enumeration risk and is friendlier to the Inertia/Wayfinder bindings that surface tenant ids on the client.
- Active tenant is held in the session under the key `active_tenant_id` and resolved per request by `ResolveActiveTenant` middleware into a request-scoped `TenantContext` singleton.
- Tenancy is enforced via a strict global scope (`TenantScope`) on every tenant-scoped model. The scope refuses to run when `TenantContext::has()` is false; bypass is allowed only via `withoutGlobalScope(TenantScope::class)`, used exclusively in the super-admin admin area and Fortify pre-login.
- Sign-in tenant restore is implemented by overriding the bound `Laravel\Fortify\Contracts\LoginResponse` in `FortifyServiceProvider::register()`. This avoids relying on the `Login` event order and keeps tenancy resolution in a documented Laravel extension point.
- `tenants.deleted_at` (soft delete) + a denormalised `status` column (`active` | `suspended`) cover the lifecycle. Suspension is distinct from deletion (FR-019 + FR-018).
- Super admin acting inside a non-member tenant is implemented by allowing `ResolveActiveTenant` to accept any tenant id for super admins (regardless of membership), while emitting a "super_admin_context" flag on `TenantContext` that the audit log + UI banner consume (FR-025a, FR-025b).
- "Last tenant" is stored on `users.last_tenant_id` and updated on every successful switch and on every successful sign-in that resolves an active tenant.

## Phase 1: Design

Generated artifacts:

- [data-model.md](./data-model.md)
- [contracts/tenant-switching.md](./contracts/tenant-switching.md)
- [contracts/tenant-admin-area.md](./contracts/tenant-admin-area.md)
- [contracts/super-admin-tenants.md](./contracts/super-admin-tenants.md)
- [contracts/tenancy-runtime.md](./contracts/tenancy-runtime.md)
- [quickstart.md](./quickstart.md)

## Post-Design Constitution Check

- **I. Laravel-first architecture**: PASS. Artisan-generated artefacts; named routes; Fortify `LoginResponse` extension; Gates + middleware for authorization; Form Request validation for every write; Eloquent for persistence; `Hash::make()` (where applicable for service-layer hashing).
- **II. Layered service + repository pattern**: PASS. Every controller delegates to a service; every service that touches data delegates to a repository implementing a typed contract bound in `AppServiceProvider::register()`. `TenantContext` is a service-layer concern, never read directly from controllers.
- **III. Inertia React contracts**: PASS. New SPA pages in `resources/js/pages/`; shared switcher/banner components in `resources/js/components/`; Wayfinder bindings for every named route; deferred props (`Inertia::optional()`) used only with explicit loading states (the tenant switcher dropdown).
- **IV. Programmatic testing**: PASS. Eight Pest feature test files cover every functional requirement and the success criteria that can be expressed as automated assertions (SC-001 isolation, SC-003 tenant restore rate, SC-004 fallback completeness, SC-006 unauthorized-access denial, SC-008 super-admin attribution). The verification command set is documented in `quickstart.md`.
- **V. Existing structure and dependencies are stable**: PASS. No new base directories. No `composer.json` / `package.json` dependency changes. Sibling patterns cited: `UserController`, `UserService`, `UserRepository`, `UserRepositoryInterface`, `HandleInertiaRequests`, `FortifyServiceProvider`.
- **VI. Tooling, formatting, and observability**: PASS. Pint, ESLint, Prettier, TypeScript, `composer ci:check`, and the Laravel Boost MCP tools when available, plus `php artisan route:list` for route verification and audit logs through the standard logging façade.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No constitution violations identified.
