# Contract: Tenant Switching and Sign-in Restore

Covers User Story 2 (switching) and User Story 3 (sign-in restore) plus the no-tenant state from User Story 3 / FR-013.

## Web routes

| Name | Method | Path | Middleware | Purpose |
|------|--------|------|------------|---------|
| `tenants.switch` | POST | `/tenants/switch` | `auth`, `verified`, `ResolveActiveTenant` | Switch the active tenant for the current session. |
| `tenants.no-tenant` | GET | `/tenants/none` | `auth` (no `ResolveActiveTenant`) | Renders the no-tenant landing page when the user has no available tenant. |

All other tenant-scoped routes are covered in [tenant-admin-area.md](./tenant-admin-area.md) and [super-admin-tenants.md](./super-admin-tenants.md).

## Sign-in flow

Implemented by `App\Http\Responses\TenantAwareLoginResponse` (bound to `Laravel\Fortify\Contracts\LoginResponse` in `FortifyServiceProvider::register()`).

```text
authenticated user U
└─ resolve active tenant T:
   ├─ if U.last_tenant_id exists, T = users.last_tenant_id
   │   └─ if T is available (exists, not deleted, not suspended)
   │      and (U is a member of T or U is super admin)
   │      → use T
   ├─ else pick U's earliest-joined available membership
   │   (ORDER BY tenant_memberships.created_at ASC, id ASC LIMIT 1)
   ├─ else if U is super admin → pick earliest active, non-deleted tenant in the system
   └─ else → redirect to tenants.no-tenant
on success:
  session('active_tenant_id') := T.id
  users.last_tenant_id := T.id
  audit: login_tenant_restored | login_tenant_fallback
  redirect to session('url.intended') ?? dashboard
on no-tenant:
  session.forget('active_tenant_id')
  audit: login_no_tenant
  redirect to tenants.no-tenant
```

## Tenant switcher contract

### Switcher UI

- Rendered in the app sidebar/header by `resources/js/components/tenant-switcher.tsx`.
- Reads `tenant.active` (always) and `tenant.available` (Inertia optional, with skeleton).
- Single-tenant users: shows the active-tenant label only, no chooser controls.
- Multi-tenant users: shows the active-tenant label + a dropdown of the other available tenants.
- Super admin acting inside a non-member tenant: the active-tenant label is decorated by `super-admin-banner.tsx` ("Acting as super admin in {name}").

### Switching request

```
POST /tenants/switch
Body: { tenant_id: uuid }
Wayfinder: @/actions/App/Http/Controllers/TenantSwitcherController.store
Form Request: Tenant/SwitchTenantRequest
```

### Validation

- `tenant_id`: required, valid UUID, must reference a tenant the current user can use:
  - Regular user: must be a member; the tenant must be `active` and not soft-deleted.
  - Super admin: must be any tenant that is not soft-deleted (suspended tenants are NOT switchable — switching to suspended is blocked even for super admins; super admins manage suspended tenants from the admin area, not by activating them).

### Success

- `session('active_tenant_id') := tenant_id`
- `users.last_tenant_id := tenant_id`
- Audit event `tenant_switched` with previous tenant id, new tenant id, and `acted_as`.
- Inertia redirect to the dashboard, OR to the page the user was on if the page is still legal in the new tenant (default: dashboard for simplicity in this slice).

### Failure

- Invalid `tenant_id`, suspended tenant, soft-deleted tenant, or non-member non-super-admin → 422 with a generic error message ("This tenant is not available"). The response MUST NOT reveal whether the tenant exists in another scope.
- Audit event `cross_tenant_access_denied` is emitted on the membership/super-admin check failure.

## No-tenant page contract

`resources/js/pages/tenants/no-tenant.tsx`

| Prop | Type | Description |
|------|------|-------------|
| `reason` | `'no_membership' | 'all_unavailable' | 'pending_invite'` | Drives the explanation block. |
| `auth` | Existing shared shape | Always present so the page can render a sign-out action. |

- Renders an explanation aligned to `reason`.
- Offers only the actions the user can take: sign out, contact administrator (mailto where configured), refresh.
- No sidebar, no tenant switcher — outside the `ResolveActiveTenant` middleware group.

## Edge cases proven by tests

| Spec edge case | Test | Expected behaviour |
|----------------|------|--------------------|
| Last tenant became unavailable | `LoginRestoresLastTenantTest::it_falls_back_when_last_tenant_is_suspended` | Falls back to earliest membership; updates `last_tenant_id`; no error page. |
| User removed from every tenant | `TenantSwitcherTest::it_redirects_to_no_tenant_when_no_membership_remains` | Redirects to `tenants.no-tenant` with `reason = no_membership`. |
| Membership change during session | `TenantSwitcherTest::it_evicts_user_from_revoked_active_tenant_on_next_request` | Next request through `ResolveActiveTenant` triggers fallback or no-tenant page. |
| Super admin switching to suspended tenant | `TenantSwitcherTest::super_admin_cannot_activate_suspended_tenant` | 422; super admin must reactivate from admin area instead. |
| First-ever sign-in | `LoginRestoresLastTenantTest::it_picks_deterministic_default_on_first_sign_in` | Picks earliest-joined membership; records `last_tenant_id`. |
| Concurrent sessions on two devices | `TenantSwitcherTest::switching_does_not_affect_other_sessions` | Per-session session keys are isolated. |
