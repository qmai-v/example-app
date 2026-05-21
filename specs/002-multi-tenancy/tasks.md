# Tasks: Multi-Tenant Architecture

**Input**: Design documents in [specs/002-multi-tenancy/](./)

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md)

**Tests**: Pest 4 feature tests are REQUIRED per the constitution. Every user-story phase ships a Pest test first; cross-cutting tests live in the Polish phase.

**Organization**: Tasks are grouped by user story so each can be implemented, tested, and demoed independently after the Foundational phase completes.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: US1 / US2 / US3 / US4 / US5 — maps to user stories from [spec.md](./spec.md)
- Includes exact file paths

## Path Conventions

- **Laravel app**: `app/`, `database/`, `routes/`, `config/`, `bootstrap/app.php`
- **Inertia pages**: `resources/js/pages/`
- **React components**: `resources/js/components/`
- **Wayfinder imports**: `resources/js/actions/`, `resources/js/routes/` (auto-regenerated)
- **Tests**: `tests/Feature/Tenancy/` for user-visible behavior; `tests/Fixtures/` for the scope test model

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm the environment is ready before adding tenancy code.

- [x] T001 Verify PostgreSQL 16 is up via `docker compose up -d postgres` and `php artisan migrate:status` runs cleanly against it
- [x] T002 [P] Confirm Laravel Boost MCP availability for `search-docs`, `database-schema`, `database-query`; if unavailable, fall back to read-only inspection via `php artisan` and proceed
- [x] T003 [P] Confirm Wayfinder regenerates on save by inspecting `resources/js/routes/` and `resources/js/actions/` after touching `routes/web.php`
- [x] T004 Confirm sibling patterns to mirror: [UserController.php](app/Http/Controllers/UserController.php), [UserService.php](app/Services/UserService.php), [UserRepository.php](app/Repositories/UserRepository.php), [UserRepositoryInterface.php](app/Repositories/Contracts/UserRepositoryInterface.php), [HandleInertiaRequests.php](app/Http/Middleware/HandleInertiaRequests.php), [FortifyServiceProvider.php](app/Providers/FortifyServiceProvider.php)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, models, runtime tenancy infrastructure, Inertia plumbing, audit channel, and the no-tenant landing — every user story depends on these.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

### Migrations & schema

- [x] T005 Create migration in `database/migrations/2026_05_21_000001_create_tenants_table.php` via `php artisan make:migration create_tenants_table --no-interaction`, then implement the `tenants` schema per [data-model.md](./data-model.md#tenant) (UUID PK, `name`, `slug` unique, `status` with CHECK, `SoftDeletes`)
- [x] T006 Create migration in `database/migrations/2026_05_21_000002_create_tenant_memberships_table.php` via `php artisan make:migration create_tenant_memberships_table --no-interaction`, then implement the `tenant_memberships` schema per [data-model.md](./data-model.md#tenant-membership) (UNIQUE(user_id, tenant_id), CHECK on role, indexes on each FK)
- [x] T007 Create migration in `database/migrations/2026_05_21_000003_add_tenancy_columns_to_users_table.php` via `php artisan make:migration add_tenancy_columns_to_users_table --no-interaction`, then add `is_super_admin` (bool, default false) and `last_tenant_id` (uuid nullable, `ON DELETE SET NULL`) to `users`
- [x] T008 Run `php artisan migrate` against Postgres and verify the schema with `php artisan db:show tenants` and `php artisan db:show tenant_memberships`

### Enum & models

- [x] T009 [P] Create `app/Models/Enums/TenantMemberRole.php` — `string` PHP enum with cases `tenant_admin` and `member`
- [x] T010 [P] Create `app/Models/Tenant.php` via `php artisan make:model Tenant --no-interaction`; configure `$incrementing = false`, `$keyType = 'string'`, `creating` boot hook to assign `Str::uuid()`, `SoftDeletes` trait, `$casts['status' => 'string']`, and the `memberships`/`members` relations from [data-model.md](./data-model.md#tenant)
- [x] T011 [P] Create `app/Models/TenantMembership.php` via `php artisan make:model TenantMembership --no-interaction`; extend `Pivot` (custom pivot model), cast `role` to `TenantMemberRole`, expose `user()` and `tenant()` relations
- [x] T012 Update `app/Models/User.php` to add the `is_super_admin` and `last_tenant_id` `Fillable`/`Hidden` attributes (as appropriate), the `memberships()`, `tenants()`, `lastTenant()` relations, and helper methods `isSuperAdmin()`, `belongsToTenant(Tenant)`, `roleInTenant(Tenant)` per [data-model.md](./data-model.md#user-existing-table--new-columns)

### Factories

- [x] T013 [P] Create `database/factories/TenantFactory.php` via `php artisan make:factory TenantFactory --no-interaction`; default to `status = active`, `name` faker company, `slug` derived
- [x] T014 [P] Create `database/factories/TenantMembershipFactory.php` via `php artisan make:factory TenantMembershipFactory --no-interaction`; default `role = member`, named states `tenantAdmin()` and `member()`
- [x] T015 [P] Extend `database/factories/UserFactory.php` with a `superAdmin()` state that sets `is_super_admin = true`

### Tenancy runtime (services, scope, trait, exception)

- [x] T016 Create `app/Exceptions/MissingTenantContextException.php` (subdirectory of an existing base `app/` dir; create alongside the existing exceptions surface)
- [x] T017 Create `app/Services/TenantContext.php` — request-scoped singleton exposing `has`, `set`, `tenant`, `id`, `clear`, `actingAsSuperAdmin` per [contracts/tenancy-runtime.md](./contracts/tenancy-runtime.md#tenantcontext-singleton)
- [x] T018 Bind `TenantContext` as a singleton in `app/Providers/AppServiceProvider.php::register()` so every resolver returns the same instance for the request
- [x] T019 Create `app/Models/Scopes/TenantScope.php` — implements `Illuminate\Database\Eloquent\Scope`; throws `MissingTenantContextException` when `TenantContext::has()` is false; adds `where("{$model->getTable()}.tenant_id", TenantContext::id())` otherwise; registers a `withoutTenantScope()` macro via `extend()`
- [x] T020 Create `app/Models/Concerns/BelongsToTenant.php` — trait that declares `tenant()` relation, calls `static::addGlobalScope(new TenantScope)` in `bootBelongsToTenant`, and stamps `tenant_id` on the `creating` event when not set (throws if no context)

### Middleware & gates

- [x] T021 [P] Create `app/Http/Middleware/RequireSuperAdmin.php` via `php artisan make:middleware RequireSuperAdmin --no-interaction`; aborts 403 with audit when `Gate::denies('super-admin')`
- [x] T022 [P] Create `app/Http/Middleware/RequireTenantAdmin.php` via `php artisan make:middleware RequireTenantAdmin --no-interaction`; allows when `TenantContext::actingAsSuperAdmin()` is true, otherwise requires `tenant_admin` role on the active tenant
- [x] T023 Create `app/Http/Middleware/ResolveActiveTenant.php` via `php artisan make:middleware ResolveActiveTenant --no-interaction`; resolves the active tenant per [contracts/tenancy-runtime.md](./contracts/tenancy-runtime.md#resolveactivetenant), sets `TenantContext`, applies fallback rules, redirects to `tenants.no-tenant` when no tenant is available
- [x] T024 Register the three new middlewares in `bootstrap/app.php` under appropriate aliases (`tenant`, `super-admin`, `tenant-admin`) and apply `ResolveActiveTenant` to the existing authenticated web group
- [x] T025 Register the `super-admin` Gate in `app/Providers/AppServiceProvider.php::boot()` — closure returns `$user->isSuperAdmin()`

### Repositories & service skeletons

- [x] T026 [P] Create `app/Repositories/Contracts/TenantRepositoryInterface.php` mirroring [UserRepositoryInterface.php](app/Repositories/Contracts/UserRepositoryInterface.php) — declare typed queries used by the runtime (find by id including soft-deleted, find by slug, etc.)
- [x] T027 [P] Create `app/Repositories/Contracts/TenantMembershipRepositoryInterface.php` mirroring the same pattern — declare `forUser`, `betweenUserAndTenant`, `lastTenantAdmin`, `paginateMembers`
- [x] T028 Create `app/Repositories/TenantRepository.php` extending `BaseRepository<Tenant>` and implementing `TenantRepositoryInterface`
- [x] T029 Create `app/Repositories/TenantMembershipRepository.php` extending `BaseRepository<TenantMembership>` and implementing `TenantMembershipRepositoryInterface`
- [x] T030 Bind both repository interfaces in `app/Providers/AppServiceProvider.php::register()` alongside the existing `UserRepositoryInterface` binding
- [x] T031 Create `app/Services/TenantService.php` extending `BaseService`; constructor takes `TenantRepositoryInterface`; ship the `findCurrent()` helper now (the rest grows in US4/US5)
- [x] T032 Create `app/Services/TenantMembershipService.php` extending `BaseService`; constructor takes `TenantMembershipRepositoryInterface` and `TenantContext`; ship `membershipsForUser($user)` + `assertCanUseTenant($user, $tenant)` helpers (the rest grows in US2/US3/US4/US5)

### Inertia, logging, no-tenant landing, route-restriction migration

- [x] T033 Update `app/Http/Middleware/HandleInertiaRequests.php` to share `tenant.active`, `tenant.role`, `tenant.actingAsSuperAdmin`, `auth.isSuperAdmin` per [contracts/tenancy-runtime.md](./contracts/tenancy-runtime.md#inertia-shared-props); `tenant.available` is added in T042 (US2)
- [x] T034 Add the `tenancy_audit` channel to `config/logging.php` (daily stack, 14-day retention) and document it inline with a comment
- [x] T035 Add the named route `tenants.no-tenant` (GET `/tenants/none`) in `routes/web.php` outside the `ResolveActiveTenant` group and inside `auth`; render `resources/js/pages/tenants/no-tenant.tsx`
- [x] T036 [P] Build `resources/js/pages/tenants/no-tenant.tsx` with the prop shape from [contracts/tenant-switching.md](./contracts/tenant-switching.md#no-tenant-page-contract); minimal action surface (sign out, refresh)
- [x] T037 Wrap the existing `users.*` routes in `routes/web.php` with the `super-admin` middleware (research decision to restrict the global user list to super admins in this slice)
- [x] T038 Update `database/seeders/DatabaseSeeder.php` to idempotently seed: one super-admin user, one demo tenant, one tenant-admin user, one member user; ensure passwords use `Hash::make()` (service-layer hashing)

### Test fixtures & helpers

- [x] T039 [P] Create `tests/Fixtures/TenantScopedTestModel.php` — minimal Eloquent model with `BelongsToTenant` trait, table `tenant_scoped_test_models`
- [x] T040 [P] Create `tests/Fixtures/2026_05_21_000099_create_tenant_scoped_test_models_table.php` — migration loaded via `loadMigrationsFrom(__DIR__.'/../Fixtures')` from a Pest helper
- [x] T041 Update `tests/Pest.php` (or `tests/TestCase.php`) with: `actingAsMember($user, $tenant)`, `actingAsTenantAdmin($user, $tenant)`, `actingAsSuperAdmin($user)` helpers; `loadTenancyFixtures()` helper that calls `loadMigrationsFrom` for the fixtures directory

**Checkpoint**: Foundation ready — user-story implementation can begin in parallel.

---

## Phase 3: User Story 1 — Tenant-Scoped Data Isolation (Priority: P1) 🎯 MVP

**Goal**: Prove that the `TenantScope` + `BelongsToTenant` infrastructure isolates reads and writes by active tenant, treats cross-tenant access as not-found, and refuses to run with no context.

**Independent Test**: With two seeded tenants and overlapping fixture rows, signed-in as a single-tenant user, every list/find/create/update/delete on `TenantScopedTestModel` operates within the active tenant only — and direct id lookups for foreign-tenant rows return null.

### Tests for User Story 1 (REQUIRED) ⚠️

- [x] T042 [P] [US1] Create Pest feature test `tests/Feature/Tenancy/TenantScopeIsolationTest.php` covering FR-001 (write stamps tenant_id), FR-002 (read returns only active-tenant rows), FR-003 (write to foreign tenant rejected), FR-004 (foreign id lookup returns null / 404), plus the strict-mode throw when `TenantContext::has()` is false

### Implementation for User Story 1

- [x] T043 [US1] In `TenantScope::apply`, ensure the table-qualified `where("{$model->getTable()}.tenant_id", ...)` form is used (verifies join-safe behaviour)
- [x] T044 [US1] In `BelongsToTenant::bootBelongsToTenant`, register the `creating` listener that stamps `tenant_id` from `TenantContext` and throws when missing
- [x] T045 [US1] Verify the test runs green with `php artisan test --compact --filter=TenantScopeIsolationTest`; capture the FAIL output before implementation if writing tests-first

**Checkpoint**: Tenancy isolation is proven by automated tests — every later story rests on this guarantee.

---

## Phase 4: User Story 2 — Switch Between Tenants From the UI (Priority: P2)

**Goal**: Multi-tenant users see the active-tenant indicator, see a switcher of their other tenants, and successfully change the active tenant for the session — with the new tenant persisted as `last_tenant_id`.

**Independent Test**: Assigning a user to two tenants, signing in, switching via the UI, and confirming Inertia shared `tenant.active` updates plus `users.last_tenant_id` is written.

### Tests for User Story 2 (REQUIRED) ⚠️

- [x] T046 [P] [US2] Create Pest feature test `tests/Feature/Tenancy/TenantSwitcherTest.php` covering happy-path switch, member-only restriction, suspended/soft-deleted refusal, single-tenant users see no chooser, removed-from-active-tenant eviction on next request, and per-session isolation across devices

### Implementation for User Story 2

- [x] T047 [P] [US2] Create `app/Http/Requests/Tenant/SwitchTenantRequest.php` via `php artisan make:request Tenant/SwitchTenantRequest --no-interaction`; validates `tenant_id` as required UUID
- [x] T048 [US2] Implement `TenantMembershipService::switchTo(User $user, string $tenantId): Tenant` — looks up membership/super-admin status, refuses suspended/deleted, writes session and `users.last_tenant_id`, emits `tenant_switched` audit event
- [x] T049 [US2] Create `app/Http/Controllers/TenantSwitcherController.php` via `php artisan make:controller TenantSwitcherController --no-interaction` — single `store` action; thin controller delegating to the service
- [x] T050 [US2] Add the `tenants.switch` named route to `routes/web.php` inside the authenticated + `tenant` middleware group
- [x] T051 [US2] Extend `app/Http/Middleware/HandleInertiaRequests.php::share` to ship `tenant.available` via `Inertia::optional()` — returning the user's memberships when they have ≥ 2; skeleton-friendly closure
- [x] T052 [US2] Extend `TenantMembershipRepository` with a typed `forUser(User $user): Collection<int, TenantMembership>` query returning eager-loaded tenant + role
- [x] T053 [P] [US2] Build `resources/js/components/tenant-switcher.tsx` per [contracts/tenant-switching.md](./contracts/tenant-switching.md#switcher-ui) using the existing `app-select` / dropdown primitives; render only the active label when the user has ≤ 1 membership
- [x] T054 [US2] Mount `<TenantSwitcher />` in `resources/js/components/app-sidebar.tsx` (or `app-sidebar-header.tsx`) so it appears on every authenticated page
- [x] T055 [US2] Restart the dev server (or `composer dev`) and verify Wayfinder regenerated `@/actions/App/Http/Controllers/TenantSwitcherController.ts` and `@/routes/tenants.ts`
- [x] T056 [US2] Run `php artisan test --compact --filter=TenantSwitcherTest` and `npm run types:check` to prove the slice

**Checkpoint**: Multi-tenant users can switch tenants from the UI; single-tenant users see only the indicator.

---

## Phase 5: User Story 3 — Resume Last Tenant on Sign-In (Priority: P3)

**Goal**: After successful authentication, the user lands directly in `users.last_tenant_id` when available, falls back deterministically when not, and reaches the no-tenant page only when truly no tenant is available.

**Independent Test**: Signing in as a multi-tenant user who last used tenant B reopens the app with tenant B active; making tenant B unavailable falls back to the next tenant without an error page.

### Tests for User Story 3 (REQUIRED) ⚠️

- [x] T057 [P] [US3] Create Pest feature test `tests/Feature/Tenancy/LoginRestoresLastTenantTest.php` covering FR-010 (restore), FR-011 (fallback when last is suspended/deleted/membership revoked), FR-012 (first-ever sign-in deterministic default), FR-013 (no-tenant fallback)

### Implementation for User Story 3

- [x] T058 [US3] Implement `TenantMembershipService::resolveLoginTenant(User $user): ?Tenant` — encodes the algorithm from [contracts/tenant-switching.md](./contracts/tenant-switching.md#sign-in-flow); emits `login_tenant_restored` / `login_tenant_fallback` / `login_no_tenant` audit events
- [x] T059 [US3] Create `app/Http/Responses/TenantAwareLoginResponse.php` implementing `Laravel\Fortify\Contracts\LoginResponse`; calls the service, writes `session('active_tenant_id')`, updates `users.last_tenant_id`, and redirects to `session('url.intended')` ?? `dashboard` or `tenants.no-tenant`
- [x] T060 [US3] Bind `Laravel\Fortify\Contracts\LoginResponse` to `TenantAwareLoginResponse` in `app/Providers/FortifyServiceProvider.php::register()`
- [x] T061 [US3] Refine `resources/js/pages/tenants/no-tenant.tsx` to switch its explanation copy on the `reason` prop (`no_membership` | `all_unavailable` | `pending_invite`)
- [x] T062 [US3] Run `php artisan test --compact --filter=LoginRestoresLastTenantTest` to prove the slice

**Checkpoint**: Sign-in continuity works for returning users; new and lost-membership users see the right fallback.

---

## Phase 6: User Story 4 — Tenant Admin Manages Their Own Tenant (Priority: P4)

**Goal**: Tenant admins manage their own tenant's membership (add/remove/role-change) and update the tenant's display name — strictly scoped to their tenant — with last-tenant-admin protection enforced.

**Independent Test**: As tenant admin of tenant A, add and remove members and change roles; attempt the same on tenant B is denied; demoting/removing the last admin is rejected with a clear message.

### Tests for User Story 4 (REQUIRED) ⚠️

- [x] T063 [P] [US4] Create Pest feature test `tests/Feature/Tenancy/TenantAdminMemberManagementTest.php` covering all four User Story 4 acceptance scenarios including the last-tenant-admin protection (FR-021d) and tenant-admin-of-A denied on tenant B

### Implementation for User Story 4

- [x] T064 [P] [US4] Create `app/Http/Requests/Tenant/StoreMemberRequest.php` via `php artisan make:request Tenant/StoreMemberRequest --no-interaction`; validates `email` (required, email, max:255), `role` (in:tenant_admin,member, default `member`)
- [x] T065 [P] [US4] Create `app/Http/Requests/Tenant/UpdateMemberRequest.php`; validates `role` (required, in:tenant_admin,member)
- [x] T066 [P] [US4] Create `app/Http/Requests/Tenant/UpdateTenantSettingsRequest.php`; validates `name` (required, string, max:255)
- [x] T067 [US4] Extend `TenantMembershipRepository` with `paginateMembers(Tenant $tenant, string $search, ?TenantMemberRole $role, int $perPage): LengthAwarePaginator<int, TenantMembership>` and `lastTenantAdmin(Tenant $tenant): ?TenantMembership`
- [x] T068 [US4] Extend `TenantMembershipService` with `paginatedMembersForCurrentTenant`, `addMember`, `changeRole`, `removeMember`, `lastTenantAdminMembershipId` per [contracts/tenant-admin-area.md](./contracts/tenant-admin-area.md#service-contracts); wrap writes in `DB::transaction`; throw `LastTenantAdminProtectedException` and `MemberAlreadyExistsException` as appropriate; emit `membership_added` / `membership_removed` / `membership_role_changed` audit events
- [x] T069 [US4] Extend `TenantService` with `updateName(Tenant $tenant, string $name): Tenant`; emit `tenant_updated`
- [x] T070 [US4] Create `app/Http/Controllers/Tenant/MemberController.php` via `php artisan make:controller Tenant/MemberController --no-interaction` with `index`, `store`, `update`, `destroy` — all delegate to `TenantMembershipService`
- [x] T071 [US4] Create `app/Http/Controllers/Tenant/SettingsController.php` via `php artisan make:controller Tenant/SettingsController --no-interaction` with `edit`, `update` — `update` delegates to `TenantService::updateName`
- [x] T072 [US4] Add the `tenant.members.*` and `tenant.settings.*` named routes in `routes/web.php` inside an authenticated + `tenant` + `tenant-admin` middleware group
- [x] T073 [P] [US4] Build `resources/js/pages/tenants/members/index.tsx` using `resource-index-layout`, `generic-table`, `app-dialog`, `confirmation-dialog`, `app-select`, and form input primitives; disable demote/remove on `lastTenantAdminMembershipId`
- [x] T074 [P] [US4] Build `resources/js/pages/tenants/settings.tsx` reusing the existing settings layout; only `name` editable; render `slug` and `status` read-only
- [x] T075 [US4] Verify Wayfinder regenerated `@/actions/App/Http/Controllers/Tenant/MemberController.ts`, `SettingsController.ts`, and `@/routes/tenant/*`
- [x] T076 [US4] Run `php artisan test --compact --filter=TenantAdminMemberManagementTest`

**Checkpoint**: Tenant admins fully manage their own tenant; cross-tenant attempts are denied.

---

## Phase 7: User Story 5 — Super Admin Manages All Tenants (Priority: P5)

**Goal**: Super admins list every tenant (active/suspended/deleted), create tenants with an initial tenant admin, update/suspend/reactivate/soft-delete/restore tenants, manage membership and roles on any tenant, and may set any tenant active to operate with tenant-admin-equivalent capabilities.

**Independent Test**: Sign in as a super admin, create and assign a new tenant, suspend it and confirm members fall back, soft-delete and restore it (data + memberships + roles preserved), enter a non-member tenant and write data attributable to super admin in the audit log.

### Tests for User Story 5 (REQUIRED) ⚠️

- [x] T077 [P] [US5] Create Pest feature test `tests/Feature/Tenancy/SuperAdminTenantManagementTest.php` covering all eight User Story 5 acceptance scenarios including create-with-initial-tenant-admin (FR-021c), suspend/reactivate (FR-019), and soft-delete + restore preserving data/memberships/roles (FR-018)
- [x] T078 [P] [US5] Create Pest feature test `tests/Feature/Tenancy/SuperAdminInTenantTest.php` covering FR-025 (super admin can switch to any active, non-deleted tenant), FR-025a (banner indicates super-admin context), FR-025b (audit attribution as `super_admin`)

### Implementation for User Story 5

- [x] T079 [P] [US5] Create `app/Http/Requests/Admin/StoreTenantRequest.php`; validates `name`, optional `slug` (regex `/^[a-z0-9](-?[a-z0-9])*$/`, unique CI), `initial_tenant_admin_user_id` exists
- [x] T080 [P] [US5] Create `app/Http/Requests/Admin/UpdateTenantRequest.php`; validates `name`, optional `slug`, optional `status` in `active,suspended`
- [x] T081 [P] [US5] Create `app/Http/Requests/Admin/StoreTenantMemberRequest.php`; validates `email`, `role` required
- [x] T082 [P] [US5] Create `app/Http/Requests/Admin/UpdateTenantMemberRequest.php`; validates `role` required
- [x] T083 [US5] Extend `TenantRepository` with admin queries: `paginateForAdmin(string $search, ?string $status, int $perPage)` (uses `withTrashed`), `findIncludingTrashed(string $id)`, `restore(Tenant $tenant)` — all use `withoutGlobalScope(TenantScope::class)` where applicable
- [x] T084 [US5] Extend `TenantService` with `createTenant(array $attributes, User $initialAdmin)`, `updateTenant(Tenant $tenant, array $attributes)`, `suspend(Tenant $tenant)`, `reactivate(Tenant $tenant)`, `softDelete(Tenant $tenant)`, `restore(Tenant $tenant)` — all wrap writes in `DB::transaction` and emit the matching audit events
- [x] T085 [US5] Extend `TenantMembershipService` with `addMemberOnTenant(Tenant $tenant, string $email, TenantMemberRole $role)`, `changeRoleOnTenant(TenantMembership $membership, TenantMemberRole $role)`, `removeMemberOnTenant(TenantMembership $membership)` — all enforce the last-tenant-admin guard
- [x] T086 [US5] Update `TenantMembershipService::switchTo` and `ResolveActiveTenant` middleware to honour the super-admin branch: a super admin may set any `active`, non-soft-deleted tenant as active; `TenantContext::actingAsSuperAdmin` is true when the user is not a member; emit `super_admin_in_tenant` on the first request inside such a tenant
- [x] T087 [US5] Create `app/Http/Controllers/Admin/TenantController.php` via `php artisan make:controller Admin/TenantController --no-interaction` with `index`, `store`, `update`, `destroy`, `restore`
- [x] T088 [US5] Create `app/Http/Controllers/Admin/TenantMemberController.php` via `php artisan make:controller Admin/TenantMemberController --no-interaction` with `index`, `store`, `update`, `destroy`
- [x] T089 [US5] Add the `admin.tenants.*` and `admin.tenants.members.*` named routes in `routes/web.php` inside an authenticated + `super-admin` middleware group; configure route-model binding for `{tenant}` to use UUID + `withTrashed`
- [x] T090 [P] [US5] Build `resources/js/pages/admin/tenants/index.tsx` with All/Active/Suspended/Deleted tabs, create/edit dialogs, suspend/reactivate toggle, soft-delete confirmation, restore action
- [x] T091 [P] [US5] Build `resources/js/pages/admin/tenants/members.tsx` mirroring the tenant-admin members page but parameterised by `{tenant}`
- [x] T092 [P] [US5] Build `resources/js/components/super-admin-banner.tsx` showing "Acting as super admin in {tenant.name}"
- [x] T093 [US5] Mount `<SuperAdminBanner />` in `resources/js/layouts/app-layout.tsx` between breadcrumbs and content, conditioned on `tenant.actingAsSuperAdmin`
- [x] T094 [US5] Add a sidebar nav entry ("Tenant administration" → `admin.tenants.index`) in `resources/js/components/app-sidebar.tsx`, conditioned on `auth.isSuperAdmin`
- [x] T095 [US5] Verify Wayfinder regenerated `@/actions/App/Http/Controllers/Admin/*.ts` and `@/routes/admin/*`
- [x] T096 [US5] Run `php artisan test --compact --filter=SuperAdmin` (matches both new test files)

**Checkpoint**: Super admins operate the system from end to end; super-admin actions inside non-member tenants are attributed correctly.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Cross-cutting verifications, formatting, type/lint/format checks, and a final manual smoke pass.

- [x] T097 [P] Create Pest feature test `tests/Feature/Tenancy/TenancyAccessDenialTest.php` covering FR-017 + FR-021d denial across every protected route (regular user → super-admin routes, member → tenant-admin routes, tenant-admin-of-A → tenant-admin routes of B), proving 403 with no information leakage and audit `cross_tenant_access_denied` events
- [x] T098 [P] Create Pest feature test `tests/Feature/Tenancy/TenancyAuditLogTest.php` covering FR-022: assert each event listed in [data-model.md](./data-model.md#tenancy-audit-event) is emitted on its triggering action, with `Log::fake('tenancy_audit')`
- [x] T099 Run `vendor/bin/pint --dirty --format agent` for all touched PHP files
- [x] T100 Run `npm run types:check`, `npm run lint:check`, and `npm run format:check` for all touched frontend files
- [x] T101 Run `php artisan test --compact --filter=Tenancy` (entire tenancy suite) and confirm green
- [x] T102 Run `composer ci:check` for full CI parity before requesting review
- [x] T103 Execute the manual smoke flow in [quickstart.md](./quickstart.md#manual-smoke-flow): seed → login as super admin → create second tenant → switch tenants → tenant-admin member ops → super-admin restrict 403

---

## Dependencies & Execution Order

### Phase dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately.
- **Foundational (Phase 2)**: Depends on Setup. **Blocks every user story.** Inside Phase 2, dependencies are:
  - T005–T008 (migrations) before T009–T015 (models/factories that reference tables)
  - T009–T011 (enum + models) before T012 (User model relations)
  - T016–T020 (exception, context, scope, trait) before T023 (middleware reads context)
  - T021–T025 (middleware + gate) before T024 (registration in `bootstrap/app.php`)
  - T026–T030 (repos + bindings) before T031–T032 (services that inject contracts)
  - T033 (Inertia share) after T017 (`TenantContext`) so the share closure can read context
  - T035–T036 (no-tenant route/page) after T024 (middleware) so redirects resolve
  - T037 (restrict `/users`) after T021/T024 (super-admin middleware available)
  - T038 (seeder) after T010/T011/T012 (models exist)
  - T039–T041 (test fixtures + helpers) can run in parallel after T020 (trait exists)
- **User Stories (Phase 3+)**: All depend on Foundational completion; can then proceed in parallel if staffed.
- **Polish (Phase 8)**: Depends on the user stories whose audit events / denial surfaces it covers (US2, US4, US5 in particular).

### User-story dependencies

- **US1 (P1)**: Depends only on Foundational. No dependency on other stories.
- **US2 (P2)**: Depends on Foundational. Adds to `HandleInertiaRequests` share (T051) — independent of US1 implementation but builds on the same shared-prop foundation.
- **US3 (P3)**: Depends on Foundational. The `TenantAwareLoginResponse` (T059) consumes `TenantMembershipService::resolveLoginTenant` (T058) — both are new methods on the existing service shipped in Foundational.
- **US4 (P4)**: Depends on Foundational. Does not depend on US2/US3 but benefits from US2's switcher when validating multi-tenant flows.
- **US5 (P5)**: Depends on Foundational. T086 modifies `TenantMembershipService::switchTo` introduced by US2 (T048); if US2 has not shipped yet, T086 introduces the method together with the super-admin branch.

### Within each user story

- Tests are written first (US1: T042, US2: T046, US3: T057, US4: T063, US5: T077–T078). Confirm they FAIL before implementation.
- Models before services before controllers before pages.
- Wayfinder regeneration is verified after route additions.

### Parallel opportunities

- Setup: T002, T003 in parallel.
- Foundational: T009–T011 in parallel (different files); T013–T015 in parallel (factories); T021–T022 in parallel; T026–T027 in parallel; T039–T040 in parallel.
- US1: T042 can be written before/parallel to verifying T043/T044 behaviour.
- US2: T046, T047, T053 are all parallelisable.
- US3: T057 parallel to T058 once the service method is stubbed.
- US4: T063, T064, T065, T066, T073, T074 are all parallelisable.
- US5: T077, T078, T079, T080, T081, T082, T090, T091, T092 are all parallelisable.
- Polish: T097, T098 in parallel.
- Across stories: once Foundational completes, US1, US2, US3, US4, US5 can be assigned to different developers.

---

## Parallel Example: User Story 4

```bash
# Write the test and create the three form requests in parallel:
Task: "Create Pest feature test tests/Feature/Tenancy/TenantAdminMemberManagementTest.php" (T063)
Task: "Create app/Http/Requests/Tenant/StoreMemberRequest.php"                                (T064)
Task: "Create app/Http/Requests/Tenant/UpdateMemberRequest.php"                               (T065)
Task: "Create app/Http/Requests/Tenant/UpdateTenantSettingsRequest.php"                       (T066)

# Build the two Inertia pages in parallel:
Task: "Build resources/js/pages/tenants/members/index.tsx"                                    (T073)
Task: "Build resources/js/pages/tenants/settings.tsx"                                         (T074)
```

---

## Implementation Strategy

### MVP first (User Story 1 only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks every story)
3. Complete Phase 3: User Story 1 — isolation proven via the test-fixture model
4. **STOP and VALIDATE**: `php artisan test --compact --filter=TenantScopeIsolationTest` green; existing `users` page accessible only to super admins; no regressions in current pages
5. The MVP demonstrates the tenancy boundary even though no production feature is tenant-scoped yet — every later slice that adopts the trait inherits the proven guarantee

### Incremental delivery

1. Setup + Foundational → infrastructure ready
2. US1 → isolation proven → demo / commit
3. US2 → users with ≥ 2 tenants can switch → demo / commit
4. US3 → sign-in restores last tenant → demo / commit
5. US4 → tenant admins manage their tenant → demo / commit
6. US5 → super admins manage all tenants → demo / commit
7. Polish → cross-cutting tests + `composer ci:check`

### Parallel team strategy

After Foundational completes:

- Developer A: US2 (switching) — owns `TenantSwitcherController`, switcher component, Inertia shared `tenant.available`
- Developer B: US3 (login restore) — owns `TenantAwareLoginResponse` and the resolve algorithm
- Developer C: US4 (tenant admin) — owns the in-tenant member/settings surfaces
- Developer D: US5 (super admin) — owns the admin area and super-admin-in-tenant flow
- All converge on Polish (T097–T103) once their stories are green

---

## Notes

- [P] tasks operate on different files and have no incomplete dependencies.
- Every user-story phase begins with a Pest test; confirm the test FAILS before implementing.
- Commit after each task or logical task group; do not skip Pint between PHP changes.
- Stop at any checkpoint to validate the slice end-to-end via the manual smoke flow in [quickstart.md](./quickstart.md).
- Avoid editing more than one user story's controllers/pages in a single task to keep parallelism viable.
- Do not introduce new base directories or `composer.json` / `package.json` dependencies — every new file lives inside an existing base dir per the constitution.
