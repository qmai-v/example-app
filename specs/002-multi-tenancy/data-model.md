# Data Model: Multi-Tenant Architecture

## Tenant

Represents an isolated workspace that owns a set of tenant-scoped records.

### Table: `tenants`

| Column | Type | Rules |
|--------|------|-------|
| `id` | `uuid` PRIMARY KEY | Generated via `Str::uuid()` in the model `creating` boot hook. Exposed in URLs and Inertia props. |
| `name` | `string(255)` | Required; human-readable display name; trimmed. |
| `slug` | `string(80)` UNIQUE | Required; lower-kebab-case derived from `name` on create (super admin may override); used only as a display-friendly handle, not as a security boundary. |
| `status` | `string(16)` | Enum-via-string + CHECK constraint: `active`, `suspended`. Defaults to `active`. |
| `created_at` / `updated_at` | `timestamps` | Standard Eloquent timestamps. |
| `deleted_at` | `timestamp NULLABLE` | Soft delete sentinel (`SoftDeletes` trait). |

### Indexes & constraints

- PRIMARY KEY (`id`)
- UNIQUE (`slug`) — case-insensitive comparison performed in the service layer before insert.
- CHECK (`status IN ('active','suspended')`)
- INDEX (`deleted_at`) implicitly via `SoftDeletes` query patterns.

### Relationships

- `memberships()` — `hasMany(TenantMembership::class)`
- `members()` — `belongsToMany(User::class)` through `tenant_memberships`, withPivot(`role`, `created_at`, `updated_at`)
- Future tenant-scoped models declare `belongsTo(Tenant::class)`.

### Validation (Form Requests)

- **Create (super admin)**: `name` required string ≤ 255; `slug` optional string 2..80 matching `^[a-z0-9](-?[a-z0-9])*$`, defaulted from `name` and uniqueness-checked; `initial_tenant_admin_user_id` required existing `users.id`.
- **Update (super admin)**: `name` required string ≤ 255; `slug` optional matching the same pattern with uniqueness excluding the current tenant id.
- **Update settings (tenant admin, own tenant)**: `name` required string ≤ 255. Tenant admins cannot change `slug` or `status` in this slice.

### Lifecycle

```text
(create with ≥1 initial tenant admin)
        │
        ▼
   status=active, deleted_at=null
        │            │
   suspend          delete (soft)
        │            │
        ▼            ▼
   status=suspended  deleted_at=NOW
        │            │
   reactivate       restore
        │            │
        ▼            ▼
   status=active    status preserved, deleted_at=null
```

Rules:

- Suspension is reversible by the super admin and leaves memberships intact but blocks every member from using the tenant as active.
- Soft delete hides the tenant and its tenant-scoped data from regular flows. Restore returns the tenant, its memberships, and per-membership roles to the state at deletion.
- Suspension and deletion are super-admin-only (FR-021d).

---

## Tenant Membership

The link between a user and a tenant, carrying the user's in-tenant role.

### Table: `tenant_memberships`

| Column | Type | Rules |
|--------|------|-------|
| `id` | `bigIncrements` PRIMARY KEY | Internal identity. |
| `user_id` | `foreignId` → `users.id` | Required; `ON DELETE CASCADE` from `users`. |
| `tenant_id` | `uuid` → `tenants.id` | Required; `ON DELETE CASCADE` from `tenants` (the per-tenant data is removed when the tenant is hard-deleted; soft-deletes leave memberships intact). |
| `role` | `string(16)` | Enum-via-string + CHECK constraint: `tenant_admin`, `member`. |
| `created_at` / `updated_at` | `timestamps` | Standard Eloquent timestamps. |

### Indexes & constraints

- PRIMARY KEY (`id`)
- UNIQUE (`user_id`, `tenant_id`) — a user has at most one membership per tenant.
- INDEX (`tenant_id`) — supports member-list queries.
- INDEX (`user_id`) — supports switcher and login lookups.
- CHECK (`role IN ('tenant_admin','member')`)

### Relationships

- `user()` — `belongsTo(User::class)`
- `tenant()` — `belongsTo(Tenant::class)`

### Validation (Form Requests)

- **Tenant admin add member (own tenant)**: `user_id` (existing user) or `email` (resolve to user); `role` ∈ {`tenant_admin`, `member`}, default `member`. Rejects if a membership already exists.
- **Tenant admin change role (own tenant)**: `role` ∈ {`tenant_admin`, `member`}. Rejects demotion/removal of the last `tenant_admin` (FR-021d).
- **Tenant admin remove member (own tenant)**: rejects if the target is the last `tenant_admin`.
- **Super admin add/update/remove member (any tenant)**: same as above; additionally must keep ≥1 `tenant_admin` on the tenant.

### Role enum

```text
TenantMemberRole {
  tenant_admin   — manages members, member roles, and tenant settings within their tenant
  member         — uses the tenant; reads and writes tenant-scoped data per feature rules
}
```

Capability matrix (within a single tenant):

| Capability | member | tenant_admin | super_admin (acting in tenant) |
|------------|:------:|:------------:|:------------------------------:|
| Use tenant as active tenant | ✓ | ✓ | ✓ |
| Read tenant-scoped data | ✓ | ✓ | ✓ |
| Write tenant-scoped data (feature rules apply) | ✓ | ✓ | ✓ |
| Add / remove members | ✗ | ✓ | ✓ |
| Change member roles | ✗ | ✓ | ✓ |
| Update tenant settings (name) | ✗ | ✓ | ✓ |
| Suspend / delete / restore tenant | ✗ | ✗ | ✓ |
| Update slug / status | ✗ | ✗ | ✓ |

### Lifecycle

```text
(none) ── add ──▶ (member | tenant_admin) ── change role ──▶ (other role) ── remove ──▶ (none)
                                                          ▲
                                                          │
                                              (blocked if last tenant_admin)
```

---

## User (existing table — new columns)

The existing `users` table is extended with tenancy-related columns. Profile, password, and verification fields are unchanged.

### New columns (added by migration `2026_05_21_000003_add_tenancy_columns_to_users_table.php`)

| Column | Type | Rules |
|--------|------|-------|
| `is_super_admin` | `boolean` | NOT NULL, DEFAULT `false`. Set out-of-band (seeder or internal tool); not user-editable. |
| `last_tenant_id` | `uuid NULLABLE` → `tenants.id` | `ON DELETE SET NULL`. Updated on every successful tenant switch and on every successful sign-in that resolves an active tenant. |

### New relationships on `User`

- `memberships()` — `hasMany(TenantMembership::class)`
- `tenants()` — `belongsToMany(Tenant::class)` through `tenant_memberships`, withPivot(`role`)
- `lastTenant()` — `belongsTo(Tenant::class, 'last_tenant_id')`

### Derived predicates (model methods)

- `isSuperAdmin(): bool` → `$this->is_super_admin`
- `belongsToTenant(Tenant $tenant): bool` → existence of an active membership
- `roleInTenant(Tenant $tenant): ?TenantMemberRole`

### Validation

- `is_super_admin` MUST NOT be writable through any user-facing Form Request in this slice.
- `last_tenant_id` MUST NOT be writable through any user-facing Form Request; the service layer is the only writer.

---

## Tenant-scoped models (infrastructure)

In this slice no production tenant-scoped model is introduced. The mechanism is shipped as:

- `app/Models/Concerns/BelongsToTenant.php` — Eloquent trait that:
  1. Declares the `tenant()` `belongsTo` relation.
  2. Registers `TenantScope` as a global scope.
  3. Hooks `creating` to stamp `tenant_id` from `TenantContext` (throws if no context).
- `app/Models/Scopes/TenantScope.php` — `Illuminate\Database\Eloquent\Scope` implementation that:
  1. Reads the active tenant id from `TenantContext` (the service singleton).
  2. Adds `where('tenant_id', $id)` to the query.
  3. Throws `MissingTenantContextException` when `TenantContext::has()` is false (strict mode).

A test-fixture model (`tests/Fixtures/TenantScopedTestModel.php`) backed by its own migration proves the trait behaviour in `tests/Feature/Tenancy/TenantScopeIsolationTest.php`. Future production tenant-scoped models adopt the trait without changing the scope.

### Required columns on every tenant-scoped table

| Column | Type | Rules |
|--------|------|-------|
| `tenant_id` | `uuid NOT NULL` → `tenants.id` | Indexed; `ON DELETE CASCADE` (or `RESTRICT` for entities that must survive tenant deletion — caller decides). |

---

## Active Tenant Context

The transient, per-request representation of the active tenant.

- **Storage on request**: held in `app/Services/TenantContext.php`, a singleton bound for the lifetime of the request.
- **Source of truth**: the session key `active_tenant_id` (UUID string) — written by the switcher controller and the Fortify `TenantAwareLoginResponse`, read by the `ResolveActiveTenant` middleware.
- **Resolution rules** (executed by middleware on every authenticated request):
  1. If no `active_tenant_id` in session → run the fallback algorithm (FR-011).
  2. If the tenant doesn't exist, is suspended, or is soft-deleted → run the fallback algorithm.
  3. If the user is not a member of the tenant AND is not a super admin → run the fallback algorithm.
  4. Otherwise → set `TenantContext` to the resolved tenant. Mark `actingAsSuperAdmin = true` if the user is a super admin and not a member of the tenant.
- **Fallback algorithm**:
  1. If `users.last_tenant_id` exists, available, and the user belongs to it → use it. Else continue.
  2. Pick the user's earliest-joined available membership deterministically (`ORDER BY tenant_memberships.created_at ASC, tenant_memberships.id ASC LIMIT 1`). If found → use it.
  3. Else → leave `TenantContext` empty; the middleware redirects to the no-tenant page (FR-013).
- **Update rules**: On every successful resolution that selects a real tenant, set `users.last_tenant_id` to that tenant id; on every explicit switch, set both session and `users.last_tenant_id`.

---

## Super Admin Role

A system-wide marker on a user, independent of tenant membership.

- Stored as `users.is_super_admin` boolean.
- Granted out-of-band only (seeder, internal tool, or DB script). No user-facing route writes this column.
- Exposed via `User::isSuperAdmin()` and the `super-admin` Gate registered in `AppServiceProvider::boot()`.
- A super admin may set any tenant as their active tenant (FR-025); when doing so as a non-member, capabilities equal a tenant admin's of that tenant (FR-025) and `TenantContext::actingAsSuperAdmin()` returns true so the UI banner and audit log can flag the context (FR-025a, FR-025b).

---

## Tenancy Audit Event

A log record describing a security-relevant tenancy event (FR-022). Emitted to the `tenancy_audit` log channel.

### Logical shape

| Field | Type | Description |
|-------|------|-------------|
| `event` | enum string | `login_tenant_restored`, `login_tenant_fallback`, `login_no_tenant`, `tenant_switched`, `tenant_created`, `tenant_updated`, `tenant_suspended`, `tenant_reactivated`, `tenant_deleted`, `tenant_restored`, `membership_added`, `membership_removed`, `membership_role_changed`, `cross_tenant_access_denied`, `super_admin_in_tenant` |
| `actor_id` | int | `users.id` performing the action; null only for system events. |
| `acted_as` | enum string | `member`, `tenant_admin`, `super_admin` |
| `tenant_id` | uuid \| null | The tenant the event applies to. |
| `target_id` | string \| null | The affected resource id (membership id, tenant id, etc.). |
| `metadata` | object | Free-form, e.g., previous role, fallback reason. |
| `timestamp` | ISO 8601 | Provided by the log channel. |

---

## Form Submission Shapes

The user-visible write actions accept the following shapes. Each is enforced by a Form Request.

| Action | Form Request | Shape |
|--------|--------------|-------|
| Switch active tenant | `Tenant/SwitchTenantRequest` | `{ tenant_id: uuid }` — must be a tenant the current user can use (regular: member; super admin: any non-deleted, non-suspended tenant). |
| Update current tenant settings | `Tenant/UpdateTenantSettingsRequest` | `{ name: string(255) }` — current tenant must be the user's active tenant and the user must be tenant admin (or super admin). |
| Add member (own tenant) | `Tenant/StoreMemberRequest` | `{ email: string, role?: 'member'|'tenant_admin' }` — must resolve to an existing user not already in the tenant. |
| Change member role (own tenant) | `Tenant/UpdateMemberRequest` | `{ role: 'member'|'tenant_admin' }` — blocked when it would remove the last tenant admin. |
| Remove member (own tenant) | (no body) | Path: `/tenant/members/{membership}` — blocked when target is the last tenant admin. |
| Create tenant (super admin) | `Admin/StoreTenantRequest` | `{ name: string(255), slug?: string(80), initial_tenant_admin_user_id: id }` |
| Update tenant (super admin) | `Admin/UpdateTenantRequest` | `{ name: string(255), slug?: string(80) }` |
| Suspend / reactivate tenant (super admin) | `Admin/UpdateTenantRequest` (status form) | `{ status: 'active'|'suspended' }` |
| Soft-delete tenant (super admin) | (no body) | Path: `/admin/tenants/{tenant}` (DELETE) |
| Restore tenant (super admin) | (no body) | Path: `/admin/tenants/{tenant}/restore` (POST) |
| Add member to tenant (super admin) | `Admin/StoreTenantMemberRequest` | `{ email: string, role: 'member'|'tenant_admin' }` |
| Change member role on tenant (super admin) | `Admin/UpdateTenantMemberRequest` | `{ role: 'member'|'tenant_admin' }` — last-tenant-admin guard enforced. |
| Remove member on tenant (super admin) | (no body) | Path: `/admin/tenants/{tenant}/members/{membership}` (DELETE) — last-tenant-admin guard enforced. |
