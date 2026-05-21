# Research: Multi-Tenant Architecture

## Decision: Shared database, shared tables with a strict Eloquent global scope

**Rationale**: The spec mandates shared database, shared tables and explicitly names a Global Scope as the enforcement mechanism. A strict scope — one that throws when no active tenant is resolved unless explicitly bypassed via `withoutGlobalScope(TenantScope::class)` — converts a "developer must remember to filter" rule into a compile-time/runtime guarantee. The two legitimate bypass surfaces (super-admin tenant administration area and Fortify pre-login flows) are small, well-bounded, and easy to audit. Each tenant-scoped table will carry a `tenant_id` UUID column with an index, so the added `WHERE tenant_id = ?` is negligible.

**Alternatives considered**:

- **Database-per-tenant or schema-per-tenant** (e.g., Stancl Tenancy): rejected because the spec explicitly says shared database, shared tables; this would also add operational complexity (migrations × N tenants).
- **Non-throwing scope (silent skip when no context)**: rejected because the safety story collapses — any forgotten middleware would silently return a cross-tenant result set.
- **Manual per-query filtering**: rejected because it relies on developer discipline and contradicts the spec's "no opt-out for regular user-facing code paths" requirement (FR-002).

## Decision: UUID primary keys on `tenants`

**Rationale**: Tenant ids appear in client state (the tenant switcher, super-admin URLs, audit logs) and could be enumerated if they were sequential integers. UUIDs eliminate enumeration risk and keep ids stable across environments. They also keep Wayfinder route bindings clean (no need to wrap with hashids). The cost — slightly larger primary keys and slower index scans than `bigint` — is irrelevant at the scale of tenants (hundreds to thousands, not millions). Memberships and tenant-scoped tables stay on `bigIncrements` PKs but carry `tenant_id` as a `uuid` foreign key.

**Alternatives considered**:

- **`bigIncrements`**: rejected because of enumeration risk and because the spec stresses that tenant ids in URLs/client state must not be treated as a security boundary — using non-guessable ids reinforces that.
- **ULIDs**: viable alternative; UUIDs win because Laravel's `Str::uuid()` and Postgres `uuid` type are first-class and already idiomatic in this stack.

## Decision: Session-tracked active tenant, resolved by middleware into a request-scoped `TenantContext`

**Rationale**: Tenant identity is a per-session, per-device concern (per spec's edge case "concurrent sessions in different tenants"). Laravel sessions already give us isolated per-device state. A dedicated `ResolveActiveTenant` middleware reads the session key, validates membership/active/suspension, applies fallback rules (FR-011, FR-020), and writes the resolved tenant into a `TenantContext` service singleton that's bound for the lifetime of the request. The global scope and every service consume `TenantContext`, not session directly — that keeps queue jobs and console commands able to set context explicitly via `TenantContext::set()` if they ever need to.

**Alternatives considered**:

- **Tenant slug in URL** (`/t/{slug}/...`): rejected because (a) the spec calls for switching from the UI, not URL changes; (b) URLs as identity make it easy to forget to validate membership; (c) increases breadth of changes across every existing route.
- **Subdomain per tenant**: rejected for the same shared-tables reasons + DNS/operational overhead.
- **JWT-style claims**: rejected because Fortify uses session auth; introducing claim-based tenancy would diverge from the rest of the stack.

## Decision: Sign-in tenant restore via Fortify `LoginResponse` override

**Rationale**: Fortify exposes `Laravel\Fortify\Contracts\LoginResponse` as the documented hook for what happens immediately after a successful login. Binding our own `TenantAwareLoginResponse` in `FortifyServiceProvider::register()` lets us:
1. Look up the user's `last_tenant_id` and their memberships.
2. Apply the deterministic fallback rules from FR-010, FR-011, FR-012.
3. Set `active_tenant_id` on the session and update `users.last_tenant_id` if we fell back.
4. Redirect to the original intended URL or to the dashboard / no-tenant page (FR-013).
This avoids racing the `Authenticated` event and keeps everything in one testable class.

**Alternatives considered**:

- **`Authenticated` event listener**: rejected because the response has typically already been built by the time listeners run, making redirect overrides awkward.
- **Middleware that runs after auth**: rejected because it would need to special-case "just logged in vs. existing session"; the LoginResponse hook is purpose-built for "just logged in".
- **Fortify config redirects**: rejected because we need conditional logic, not a static URL.

## Decision: Per-membership role with two values (`tenant_admin`, `member`) on a pivot model

**Rationale**: The user chose Option B in clarification Q2: two in-tenant roles. A `tenant_memberships` pivot with `user_id`, `tenant_id`, `role`, and timestamps is the smallest model that captures it. We promote the pivot to a full `TenantMembership` Eloquent model so we can attach observers (e.g., last-tenant-admin guard), use it as a domain object, and back the audit log with it. The role is stored as a Postgres enum-via-`string` column with a CHECK constraint and a PHP `enum TenantMemberRole: string` cast.

**Alternatives considered**:

- **Spatie laravel-permission**: rejected because (a) adding a runtime dependency is prohibited by constitution principle V without explicit approval; (b) the role surface here is two values — a full permissions package is overkill; (c) couples permissions to global roles, whereas the spec needs per-tenant roles.
- **Boolean `is_admin` on pivot**: rejected because it doesn't extend cleanly if a third role is added later, and an explicit enum is self-documenting.

## Decision: Soft delete on tenants with `status` for suspension, separate `deleted_at` for deletion

**Rationale**: The user chose Option A in clarification Q3 — soft delete with super-admin restore. Laravel's `SoftDeletes` trait covers data retention and trivial restore (`->restore()`). Suspension is a separate, reversible state used while a tenant is still operationally present (e.g., billing dispute). Both states hide the tenant from regular users but in different ways: suspended tenants remain visible to super admins in the active-tenant administration area; soft-deleted tenants appear under a "deleted tenants" tab in the same area. This avoids overloading `deleted_at` with two meanings.

**Alternatives considered**:

- **Single status column with `deleted` value**: rejected because Laravel's `SoftDeletes` integration (query scopes, restore, etc.) is already battle-tested and matches the project's Laravel-first principle.
- **Hard delete**: rejected by Q3 outcome.

## Decision: Strict global scope refuses to run with no context; explicit bypass for admin/login paths

**Rationale**: The scope's safety only holds if it fails closed. The chosen behaviour:
- If `TenantContext::has()` is true → scope adds `WHERE tenant_id = ?`.
- If `TenantContext::has()` is false → scope throws `MissingTenantContextException` on the first query.
- Code paths that legitimately need to ignore the scope call `Model::withoutGlobalScope(TenantScope::class)` — there are exactly two such paths in this slice: (a) the super-admin tenant administration area (which queries `Tenant`/`TenantMembership` across all tenants); (b) the Fortify `TenantAwareLoginResponse`, which reads membership before the active tenant is set.
The throw is what makes "no opt-out for regular user-facing code paths" (FR-002) enforceable.

**Alternatives considered**:

- **Soft scope (skip silently)**: rejected — single forgotten middleware = data leak.
- **Throw without bypass**: rejected because legitimate admin and login flows need cross-tenant reads.

## Decision: Demonstrate the scope using a test-fixture model, not a new production tenant-scoped table

**Rationale**: The spec assumption is explicit: existing features adopt tenant scoping in later slices. This slice ships the infrastructure (scope, trait, middleware, context) plus the user-facing surfaces for membership/admin. To prove User Story 1 ("tenant-scoped reads/writes only see the active tenant"), we add a `TenantScopedTestModel` and its migration under `tests/Fixtures/`, used only in `tests/Feature/Tenancy/TenantScopeIsolationTest.php`. This avoids inventing a product surface (Notes/Projects/etc.) that the spec does not ask for, while still proving the boundary.

**Alternatives considered**:

- **Ship a real tenant-scoped feature (notes, projects, etc.)**: rejected — out of scope; adds product surface the spec doesn't request.
- **Apply scope to `TenantMembership`**: rejected because membership queries naturally span tenants (the switcher needs every membership for the current user, regardless of active tenant); shoehorning the scope here would either require constant bypasses or rule out the natural query pattern.

## Decision: Restrict the existing `/users` page to super admins in this slice

**Rationale**: The existing user management page (`UserController`) lists every user in the system without any tenancy concept. Once tenancy ships, leaving it open to all authenticated admins would expose every user across every tenant — a regression of the isolation guarantee even though the page predates tenancy. The smallest safe change is to wrap the `users.*` routes in the `RequireSuperAdmin` middleware in this slice. A future slice can introduce a tenant-scoped "members of my tenant" surface to replace it for non-super-admins (we are already shipping the tenant-admin Members page, which covers that need at the tenant-admin level).

**Alternatives considered**:

- **Leave `/users` open**: rejected — leaks every user across tenant boundaries.
- **Delete `/users`**: rejected — orthogonal scope change and removes a working super-admin tool.
- **Tenant-scope `/users` now**: rejected — out of scope per the spec assumption that existing features adopt scoping in their own slices; doing it here also requires deciding what "members of a tenant" means for `users.store/update/destroy`.

## Decision: Inertia shared props carry `activeTenant`, `availableTenants`, `isSuperAdmin`, `actingAsSuperAdmin`

**Rationale**: Every authenticated page needs to render the tenant indicator (FR-009) and the switcher when relevant (User Story 2). Putting these on Inertia shared props (`HandleInertiaRequests::share`) means the data is available on every page without per-controller wiring. Shared props are read from `TenantContext` + the authenticated user — both already resolved by middleware. The flag `actingAsSuperAdmin` is true when a super admin's active tenant is one they are not a member of; the UI uses this to render the `super-admin-banner` (FR-025a). `availableTenants` is shipped via `Inertia::optional()` only for users with more than one membership, with a skeleton loading state in the switcher dropdown.

**Alternatives considered**:

- **Per-controller props**: rejected — every page would need to opt-in, breaking FR-009.
- **Client-side fetch of switcher options**: rejected — requires an extra round-trip and an API surface the project doesn't otherwise expose.

## Decision: Audit log via the standard logging façade with a dedicated channel name

**Rationale**: FR-022 enumerates security-relevant tenancy events. The project already uses Laravel's logging stack and Pail; adding a dedicated `tenancy_audit` log channel (configured in `config/logging.php`) keeps these events grep-able and routable without introducing a new dependency or an audit table. A future slice can promote the channel to a database sink if compliance requires it.

**Alternatives considered**:

- **Dedicated `tenancy_audit_logs` table**: rejected for this slice because no requirement asks for queryable audit history yet; we can add it later without changing emitter code.
- **Third-party audit packages** (e.g., Spatie laravel-activitylog): rejected — runtime dependency change not approved.

## Decision: Use Laravel Boost MCP tooling during implementation when available

**Rationale**: Per constitution principle VI, Laravel Boost MCP (`search-docs`, `database-schema`, `database-query`, `get-absolute-url`, `browser-logs`) is the preferred verification tooling. During implementation, the tasks list will call `search-docs` for Eloquent global scopes, Fortify `LoginResponse`, Inertia v3 shared-prop patterns, and Wayfinder regeneration; `database-schema` to validate the migration shape against Postgres after each migration is added; and `browser-logs` if the new pages misbehave in the dev server.

**Alternatives considered**:

- **Skip MCP and rely on the wider web**: rejected — version-aware results from Laravel Boost match the locked dependency versions in this project; generic web docs may drift.
