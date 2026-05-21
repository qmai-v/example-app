# Contract: Super-Admin Tenant Administration

Covers User Story 5 — system-wide super admin manages every tenant and may set any tenant as their active tenant (FR-025).

## Web routes

All routes run inside the `auth`, `verified`, `ResolveActiveTenant`, `RequireSuperAdmin` middleware stack. Controllers behind `RequireSuperAdmin` are the only place that may call `withoutGlobalScope(TenantScope::class)`.

| Name | Method | Path | Purpose |
|------|--------|------|---------|
| `admin.tenants.index` | GET | `/admin/tenants` | List every tenant (active, suspended, soft-deleted, separated). |
| `admin.tenants.store` | POST | `/admin/tenants` | Create a tenant with an initial tenant admin. |
| `admin.tenants.update` | PUT/PATCH | `/admin/tenants/{tenant}` | Update tenant details (`name`, `slug`, `status`). |
| `admin.tenants.destroy` | DELETE | `/admin/tenants/{tenant}` | Soft-delete a tenant. |
| `admin.tenants.restore` | POST | `/admin/tenants/{tenant}/restore` | Restore a soft-deleted tenant. |
| `admin.tenants.members.index` | GET | `/admin/tenants/{tenant}/members` | List a tenant's members. |
| `admin.tenants.members.store` | POST | `/admin/tenants/{tenant}/members` | Add a member with role. |
| `admin.tenants.members.update` | PUT/PATCH | `/admin/tenants/{tenant}/members/{membership}` | Change role. |
| `admin.tenants.members.destroy` | DELETE | `/admin/tenants/{tenant}/members/{membership}` | Remove member. |

Route-model binding for `{tenant}` resolves by UUID against `Tenant::withTrashed()->withoutGlobalScopes()` so that soft-deleted tenants are addressable by super admins.

## Inertia pages

### `resources/js/pages/admin/tenants/index.tsx`

| Prop | Type | Description |
|------|------|-------------|
| `tenants` | Paginated `{ id, name, slug, status, deleted_at, member_count, last_used_at, created_at, updated_at }` | The list of every tenant. |
| `filters.search` | string \| null | Search by name or slug. |
| `filters.status` | `'active' | 'suspended' | 'deleted' | null` | Status tab/filter. |
| `filters.per_page` | number | Reuses `BaseService::PER_PAGE_OPTIONS`. |

UI behaviour:

- Reuse `resource-index-layout`, `generic-table`, `app-dialog`, `confirmation-dialog`.
- Tabs: All / Active / Suspended / Deleted.
- Row actions: View members, Edit, Suspend / Reactivate (toggles status), Delete (soft, with confirmation), Restore (only shown for deleted).
- Create dialog: `name`, `slug` (optional), `initial_tenant_admin_user_id` (autocomplete picker from `users`).

### `resources/js/pages/admin/tenants/members.tsx`

| Prop | Type | Description |
|------|------|-------------|
| `tenant` | `{ id, name, slug, status, deleted_at }` | The target tenant. |
| `members` | Paginated `{ id, user: { id, name, email }, role, created_at }` | The tenant's memberships. |
| `lastTenantAdminMembershipId` | uuid \| null | Used by the UI to disable demote/remove on the last admin. |

UI behaviour: mirrors the tenant-admin members page but the `tenant` is selected by URL (any tenant) and the actor is the super admin.

## Form requests

### `Admin\StoreTenantRequest`

```text
name:                            required, string, max:255
slug:                            optional, string, min:2, max:80, regex:/^[a-z0-9](-?[a-z0-9])*$/,
                                 unique:tenants,slug (CI normalized)
initial_tenant_admin_user_id:    required, exists:users,id
```

Service rules:

- Wrap the create + initial-membership insert in a single `DB::transaction`.
- The initial membership is created with `role = tenant_admin`.

### `Admin\UpdateTenantRequest`

```text
name:    required, string, max:255
slug:    optional, ...same as create...
status:  optional, in:active,suspended
```

Service rules:

- Status transition rules (FR-019):
  - `active → suspended`: emits `tenant_suspended`. Sessions referencing this tenant are evicted on their next request (handled by middleware reading fresh status).
  - `suspended → active`: emits `tenant_reactivated`.
- Cannot change status on a soft-deleted tenant; require restore first → 422.

### `Admin\StoreTenantMemberRequest`

```text
email:   required, email, max:255
role:    required, in:tenant_admin,member
```

### `Admin\UpdateTenantMemberRequest`

```text
role:    required, in:tenant_admin,member
```

Last-tenant-admin guard applies to both update and destroy via service-layer checks (FR-021d / FR-021c).

## Lifecycle actions

| Action | Path | Service emits | Side effects |
|--------|------|---------------|--------------|
| Create | `POST /admin/tenants` | `tenant_created` | Initial `tenant_admin` membership row. |
| Update settings | `PUT /admin/tenants/{tenant}` | `tenant_updated` | None beyond column writes. |
| Suspend | `PUT /admin/tenants/{tenant}` (`status=suspended`) | `tenant_suspended` | Active sessions referencing the tenant fall back on next request. |
| Reactivate | `PUT /admin/tenants/{tenant}` (`status=active`) | `tenant_reactivated` | Tenant becomes switchable again. |
| Soft-delete | `DELETE /admin/tenants/{tenant}` | `tenant_deleted` | `deleted_at` set; tenant disappears from regular switchers; active sessions fall back on next request. |
| Restore | `POST /admin/tenants/{tenant}/restore` | `tenant_restored` | `deleted_at` cleared; memberships and roles are unchanged (they were never removed), so data, memberships, and roles are recovered to the state at deletion. |

## Super-admin acting inside a tenant (FR-025)

The super admin enters a tenant via the regular `tenants.switch` route (see [tenant-switching.md](./tenant-switching.md)) with the following relaxations:

- Membership check is bypassed by the switcher's super-admin branch.
- Tenant must still be `active` and not soft-deleted (suspended/deleted tenants are not switchable; manage them from the admin area instead).
- On success, `TenantContext::actingAsSuperAdmin()` returns true and the `super-admin-banner` is rendered.
- All capabilities the super admin has inside the tenant are equivalent to a `tenant_admin` of that tenant (FR-025). The `RequireTenantAdmin` middleware short-circuits when `actingAsSuperAdmin()` is true.
- Every write performed in this mode is logged with `acted_as = 'super_admin'` (FR-025b, SC-008).

## Verification

- `tests/Feature/Tenancy/SuperAdminTenantManagementTest.php` exercises every acceptance scenario of User Story 5 including create-with-initial-tenant-admin and restore-recovers-state.
- `tests/Feature/Tenancy/SuperAdminInTenantTest.php` proves the super admin can act with tenant-admin-equivalent capabilities in non-member tenants and that audit attribution is correct.
- `tests/Feature/Tenancy/TenancyAccessDenialTest.php` proves non-super-admin users cannot reach any `admin.tenants.*` route by URL.
