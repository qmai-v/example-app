# Contract: Tenancy Runtime

This contract documents the cross-cutting tenancy layer that every other contract assumes is in place.

## Session

| Key | Type | Lifecycle |
|-----|------|-----------|
| `active_tenant_id` | UUID string \| absent | Written by `TenantSwitcherController@store` and by `TenantAwareLoginResponse`. Read by `ResolveActiveTenant` middleware. Cleared on logout. |

## Middleware

### `ResolveActiveTenant`

- **Where it runs**: applied to every authenticated route group in `routes/web.php` (i.e., the existing `auth` + `verified` group and the new admin/tenant groups).
- **Behaviour**:
  1. If the request user is unauthenticated → pass through (other middleware handles this).
  2. Look up `session('active_tenant_id')`.
  3. Validate the candidate tenant: must exist, must not be soft-deleted, must not be suspended, and either the user is a member OR the user is a super admin.
  4. If valid → set `app(TenantContext::class)` to that tenant. Set `actingAsSuperAdmin = true` if the user is a super admin and not a member.
  5. If invalid → apply the fallback algorithm in [data-model.md](../data-model.md#active-tenant-context). On success, update both session and `users.last_tenant_id`. On failure, redirect to `tenants.no-tenant`.
  6. On success, if the tenant changed from what was in session, write the new id to session and update `users.last_tenant_id`.
- **Exceptions**: this middleware is the ONLY user-facing entry point that may read or change the active tenant. Controllers and services consume `TenantContext`, never session directly.

### `RequireSuperAdmin`

- **Where it runs**: every route under the `admin/...` prefix and the existing `users.*` routes (per the research decision to restrict the global user list to super admins in this slice).
- **Behaviour**:
  1. Require `auth`.
  2. Abort 403 (`Gate::denies('super-admin')`) if the user does not carry `is_super_admin = true`.
  3. Allow `withoutGlobalScope(TenantScope::class)` calls in the controllers behind this middleware (they're whitelisted to run cross-tenant queries).
- **Audit**: every denial emits `cross_tenant_access_denied` with `acted_as = 'member'` (or `tenant_admin`) and the attempted route.

### `RequireTenantAdmin`

- **Where it runs**: routes under `tenant/members`, `tenant/settings` (current-tenant administration).
- **Behaviour**:
  1. Require `auth` and a resolved `TenantContext`.
  2. Allow if `TenantContext::actingAsSuperAdmin()` is true (super-admin acting inside a tenant has tenant-admin-equivalent capabilities, FR-025).
  3. Otherwise require the current user to be a `tenant_admin` of the active tenant; abort 403 if not.
- **Audit**: denials emit `cross_tenant_access_denied`.

## Service contracts

### `TenantContext` (singleton)

| Method | Returns | Notes |
|--------|---------|-------|
| `has(): bool` | Whether a tenant is currently set. |
| `set(Tenant $tenant, bool $actingAsSuperAdmin = false): void` | Writes the context for the rest of the request. |
| `tenant(): Tenant` | Returns the active tenant; throws `MissingTenantContextException` if none. |
| `id(): string` | UUID; throws if none. |
| `actingAsSuperAdmin(): bool` | True when the active tenant is owned by a super-admin context (non-member super admin). |
| `clear(): void` | Used by Fortify pre-login and logout. |

### `TenantScope` (Eloquent global scope)

- `apply(Builder $builder, Model $model)`:
  - If `TenantContext::has()` is false → throw `MissingTenantContextException`.
  - Else add `where("{$model->getTable()}.tenant_id", TenantContext::id())`.
- `extend(Builder $builder)`:
  - Adds a `withoutTenantScope()` macro for ergonomic bypass in admin-area code.

### `BelongsToTenant` trait

- Declares `tenant(): BelongsTo`.
- Boots `TenantScope` as a global scope.
- Hooks `creating` to set `tenant_id = TenantContext::id()` if not already set; throws if no context.

## Inertia shared props

`HandleInertiaRequests::share` adds the following keys (additive, do not break existing keys):

| Key | Shape | Notes |
|-----|-------|-------|
| `tenant.active` | `{ id: uuid, name: string, slug: string, status: 'active'|'suspended' } \| null` | Null on routes outside the authenticated/tenant-resolved zone (e.g. login, no-tenant page). |
| `tenant.available` | Inertia optional → `Array<{ id, name, slug, role: 'tenant_admin'|'member' }>` | Resolved only for users with ≥ 2 memberships. UI renders a skeleton while loading. |
| `tenant.role` | `'tenant_admin' | 'member' | null` | The current user's role in the active tenant (`null` when acting as super admin in a non-member tenant). |
| `auth.isSuperAdmin` | `bool` | Mirrors `users.is_super_admin`. |
| `tenant.actingAsSuperAdmin` | `bool` | True when the current user is super admin AND not a member of the active tenant. |

## Audit log channel

- Configured as `tenancy_audit` in `config/logging.php` (stack: `daily` by default, fan-out per environment).
- Every middleware/service decision in this contract emits one of the events listed in [data-model.md → Tenancy Audit Event](../data-model.md#tenancy-audit-event).
- Tests in `tests/Feature/Tenancy/TenancyAuditLogTest.php` capture the channel via `Log::fake('tenancy_audit')` and assert events.

## Error responses

| Condition | HTTP response | Inertia behaviour |
|-----------|---------------|-------------------|
| Unauthenticated request to a tenant-scoped route | 302 → `login` | Standard Fortify behaviour. |
| Authenticated request, no available tenant | 302 → `tenants.no-tenant` | The no-tenant page renders an explanation and a sign-out action. |
| Cross-tenant record fetch (record exists in another tenant) | 404 | Surfaced as Inertia's standard error response; never exposes the record's existence. |
| Tenant suspended while user has session in it | 302 → fallback tenant or `tenants.no-tenant` | Toast: "This tenant has been suspended." |
| Tenant soft-deleted while user has session in it | 302 → fallback tenant or `tenants.no-tenant` | Toast: "This tenant is no longer available." |
| Non-super-admin reaches super-admin route | 403 | Standard Inertia error component. |
| Non-tenant-admin reaches tenant-admin route | 403 | Standard Inertia error component. |
