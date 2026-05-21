# Contract: Tenant Administration (Current Tenant)

Covers User Story 4 — tenant admin manages their own tenant's membership and settings, without super-admin involvement.

## Web routes

All routes run inside the `auth`, `verified`, `ResolveActiveTenant`, `RequireTenantAdmin` middleware stack. The active tenant is always the current user's `TenantContext::tenant()`.

| Name | Method | Path | Purpose |
|------|--------|------|---------|
| `tenant.members.index` | GET | `/tenant/members` | List members of the current tenant. |
| `tenant.members.store` | POST | `/tenant/members` | Add a user as a member of the current tenant. |
| `tenant.members.update` | PUT/PATCH | `/tenant/members/{membership}` | Change a member's role. |
| `tenant.members.destroy` | DELETE | `/tenant/members/{membership}` | Remove a member. |
| `tenant.settings.edit` | GET | `/tenant/settings` | Show the current tenant's settings form. |
| `tenant.settings.update` | PUT/PATCH | `/tenant/settings` | Update the current tenant's `name`. |

## Inertia pages

### `resources/js/pages/tenants/members/index.tsx`

| Prop | Type | Description |
|------|------|-------------|
| `members` | Paginated `{ id, user: { id, name, email }, role, created_at }` | The active tenant's memberships (in-tenant query, not Inertia optional). |
| `filters.search` | string \| null | Search by name or email. |
| `filters.role` | `'tenant_admin' | 'member' | null` | Role filter. |
| `filters.per_page` | number | Reuses `BaseService::PER_PAGE_OPTIONS`. |
| `lastTenantAdminMembershipId` | uuid \| null | Used by the UI to disable demote/remove on the last tenant admin. |

UI behaviour:

- Reuse `resource-index-layout`, `generic-table`, `app-dialog`, `confirmation-dialog`, `app-select`, and form input primitives.
- Add member: dialog form with `email` and `role` (default `member`). On success, refresh list with a flash toast.
- Change role: inline dialog from a row action; respects `lastTenantAdminMembershipId` (the only admin's row cannot be demoted).
- Remove member: confirmation dialog; respects `lastTenantAdminMembershipId`.

### `resources/js/pages/tenants/settings.tsx`

| Prop | Type | Description |
|------|------|-------------|
| `tenant` | `{ id, name, slug, status }` | Current tenant. |
| `canEditSlug` | `false` | Always false for tenant admins in this slice; the field is rendered read-only. |

UI behaviour: reuses the existing settings page layout (`resources/js/layouts/settings`). Only `name` is editable.

## Form requests

### `Tenant\StoreMemberRequest`

```text
email:        required, email, max:255
role:         optional, in:tenant_admin,member  (default: member)
```

Service-layer rules:

- Resolve `email` to an existing `users.id`; if no such user → 422 ("No user with that email exists.").
- Reject if a `tenant_memberships` row already exists for `(tenant_id, user_id)` → 422 ("This user is already a member.").

### `Tenant\UpdateMemberRequest`

```text
role:         required, in:tenant_admin,member
```

Service-layer rules:

- Membership belongs to the active tenant (route binding scoping enforced).
- Reject when `role = member` and the target is the only `tenant_admin` of the active tenant (FR-021d).

### `Tenant\UpdateTenantSettingsRequest`

```text
name:         required, string, max:255
```

## Service contracts

`App\Services\TenantMembershipService`

| Method | Returns | Notes |
|--------|---------|-------|
| `paginatedMembersForCurrentTenant(string $search, ?TenantMemberRole $role, int $perPage)` | `LengthAwarePaginator<int, MemberRow>` | Repository delegation. |
| `addMember(Tenant $tenant, string $email, TenantMemberRole $role): TenantMembership` | Throws `MemberAlreadyExistsException`. |
| `changeRole(TenantMembership $membership, TenantMemberRole $role): TenantMembership` | Throws `LastTenantAdminProtectedException` when the change would leave 0 admins. |
| `removeMember(TenantMembership $membership): void` | Throws `LastTenantAdminProtectedException`. |
| `lastTenantAdminMembershipId(Tenant $tenant): ?int` | Helper used by controllers to surface UI guards. |

`App\Services\TenantService`

| Method | Returns | Notes |
|--------|---------|-------|
| `updateName(Tenant $tenant, string $name): Tenant` | Audit event `tenant_updated`. |
| `findCurrent(): Tenant` | Wraps `TenantContext::tenant()`. |

All write methods are wrapped in `DB::transaction` and emit the appropriate audit log events.

## Verification

- `tests/Feature/Tenancy/TenantAdminMemberManagementTest.php` exercises every acceptance scenario for User Story 4, including the last-tenant-admin protection and cross-tenant denial.
- `tests/Feature/Tenancy/TenancyAccessDenialTest.php` proves a member (non-admin) cannot reach `tenant.members.*` routes, and a tenant admin of tenant A cannot reach tenant B's URLs (even by editing the path).
