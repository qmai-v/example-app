# Feature Specification: Multi-Tenant Architecture

**Feature Branch**: `002-multi-tenancy`

**Created**: 2026-05-21

**Status**: Draft

**Input**: User description: "I want to develop the system using a multi-tenant architecture with a shared database and shared tables. Use a Global Scope for tenant models. A user can belong to multiple tenants, and users can switch between tenants from the UI. When logging in, the system should automatically open the last tenant the user accessed. A system-wide super admin should be able to manage all tenants."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Work Inside a Tenant With Isolated Data (Priority: P1)

A signed-in user with a single tenant membership lands directly inside that tenant after authentication and sees only data that belongs to that tenant. Lists, searches, detail pages, and any data they create or change are confined to the tenant they are currently operating in.

**Why this priority**: Tenant data isolation is the foundation of the entire feature — every other capability (switching, super-admin oversight, last-tenant restore) only makes sense if the active-tenant boundary is reliably enforced. This is also the smallest viable slice: a single-tenant user gets a coherent product.

**Independent Test**: Can be fully tested by seeding two tenants with overlapping data, signing in as a user who belongs to only one of them, and confirming that every tenant-scoped page, search result, create/update/delete action, and direct URL only exposes that one tenant's records.

**Acceptance Scenarios**:

1. **Given** two tenants exist with their own users, **When** a user who belongs to only tenant A signs in, **Then** every tenant-scoped list and search shows only tenant A's records and no tenant B records are visible anywhere in the application.
2. **Given** a user is operating inside tenant A, **When** they create, edit, or delete a tenant-scoped record, **Then** the record is associated with tenant A and remains invisible to anyone whose active tenant is tenant B.
3. **Given** a user is operating inside tenant A, **When** they follow a direct link or URL pointing at a record that belongs to tenant B, **Then** the system treats the record as not found rather than revealing its existence or contents.

---

### User Story 2 - Switch Between Tenants From the UI (Priority: P2)

A user who belongs to more than one tenant can see which tenant they are currently operating in and switch to any other tenant they belong to from the UI, without signing out. After switching, the entire application reflects the newly chosen tenant.

**Why this priority**: Tenant switching is the primary differentiator for users with multi-tenant memberships and is the most visible day-to-day interaction. It builds on the isolation guarantee from P1.

**Independent Test**: Can be fully tested by assigning a user to two tenants, signing in, using the tenant switcher to move from tenant A to tenant B, and confirming the visible data, navigation context, and any in-app tenant indicator all update to reflect tenant B.

**Acceptance Scenarios**:

1. **Given** a user belongs to two or more tenants, **When** they view any authenticated page, **Then** the UI clearly shows which tenant is currently active and offers a way to switch to any other tenant they belong to.
2. **Given** a user is operating inside tenant A, **When** they switch to tenant B using the UI, **Then** the application reloads tenant-scoped views to show tenant B's data and the active-tenant indicator updates to tenant B.
3. **Given** a user belongs to exactly one tenant, **When** they view the tenant indicator, **Then** the active tenant is displayed but no switcher options are offered.
4. **Given** a user is operating inside tenant A, **When** their membership in tenant A is removed (by another administrator) and they attempt to continue working, **Then** the system stops treating tenant A as their active tenant and prompts them to select another tenant they still belong to.

---

### User Story 3 - Resume Last Tenant on Sign-In (Priority: P3)

When a user signs in, the application automatically opens the tenant they were most recently working in, so they continue where they left off without having to choose again.

**Why this priority**: This is a continuity and convenience improvement that compounds the value of P1 and P2. The product still works without it (users would simply choose a tenant on each sign-in), so it is third priority.

**Independent Test**: Can be fully tested by signing in as a multi-tenant user, switching to a specific tenant, signing out, and signing back in to confirm the application opens directly in that tenant.

**Acceptance Scenarios**:

1. **Given** a returning user has previously used tenant B and still belongs to it, **When** they sign in, **Then** the application opens with tenant B as the active tenant without asking them to choose.
2. **Given** a returning user's most recently used tenant is no longer accessible to them (membership removed, tenant suspended, or tenant deleted), **When** they sign in, **Then** the application falls back to another tenant they still belong to or prompts them to choose one if more than one remains.
3. **Given** a brand-new user who has just been added to one or more tenants and has never signed in, **When** they sign in for the first time, **Then** the application opens one of their tenants as the active tenant using a deterministic default and records that choice as their most recent tenant.
4. **Given** a user has been removed from every tenant they previously belonged to, **When** they sign in, **Then** the application takes them to a clearly-labelled "no tenant available" state that prevents access to tenant-scoped features and explains the next step.

---

### User Story 4 - Tenant Admin Manages Their Own Tenant (Priority: P4)

A tenant admin can manage the membership and basic settings of the specific tenant they administer — adding and removing members, changing a member's in-tenant role, and updating the tenant's display name — without needing super-admin involvement.

**Why this priority**: Day-to-day membership changes happen at the tenant level. Letting tenant admins handle their own membership is the smallest delegation that prevents super admins from becoming a bottleneck. It depends on P1 (isolation) and the role concept introduced in this feature.

**Independent Test**: Can be fully tested by signing in as a tenant admin of tenant A, adding a new user to tenant A, changing a member's role, and confirming the changes apply only to tenant A — and that the same tenant admin cannot manage any other tenant.

**Acceptance Scenarios**:

1. **Given** a user is a tenant admin of tenant A, **When** they open tenant-management screens while operating inside tenant A, **Then** they can add a user as a member, remove a member, and change a member's in-tenant role between tenant admin and member.
2. **Given** a tenant admin of tenant A, **When** they attempt to manage members, roles, or settings of tenant B, **Then** the system denies access and does not reveal tenant B's membership.
3. **Given** a tenant admin is the last remaining tenant admin in their tenant, **When** they attempt to remove themselves or demote themselves to member, **Then** the action is blocked with a clear explanation that the tenant must always have at least one tenant admin.
4. **Given** a tenant admin attempts to suspend, delete, or restore the tenant, **When** the action is submitted, **Then** the system denies it and clarifies that those actions are super-admin-only.

---

### User Story 5 - Super Admin Manages All Tenants (Priority: P5)

A system-wide super admin can see every tenant in the system, create new tenants, update tenant details, suspend or reactivate tenants, soft-delete and restore tenants, and manage which users belong to which tenants (including in-tenant role) — independent of any individual tenant's membership. A super admin can also set any tenant as their active tenant to read and write its data with tenant-admin-equivalent capabilities.

**Why this priority**: Super-admin tenant management is operational tooling that is needed before the product can be run at scale, but it does not deliver direct value to end users and depends on the rest of the tenancy model being in place.

**Independent Test**: Can be fully tested by signing in as a super admin, listing all tenants, creating a new tenant with an initial tenant admin, updating a tenant, suspending and reactivating a tenant, soft-deleting and restoring a tenant, adding/removing users with roles on any tenant, and entering a tenant they are not a member of to read and write its data.

**Acceptance Scenarios**:

1. **Given** a super admin is signed in, **When** they open the tenant administration area, **Then** they see every tenant in the system — including suspended and soft-deleted tenants — regardless of their own membership.
2. **Given** a super admin is in the tenant administration area, **When** they create a new tenant and assign at least one initial tenant admin, **Then** the tenant is added to the system and the initial tenant admin can immediately use it as their active tenant.
3. **Given** a super admin attempts to create a tenant without assigning at least one tenant admin, **When** they submit, **Then** the action is blocked with a clear message that an initial tenant admin is required.
4. **Given** a super admin selects an existing tenant, **When** they add or remove a user, or change a user's in-tenant role between tenant admin and member, **Then** the change takes effect for that user's next request and is visible in the user's tenant switcher and capabilities.
5. **Given** a super admin suspends a tenant, **When** any member of that tenant attempts to use it as their active tenant, **Then** the tenant is treated as unavailable and the user is redirected to choose another available tenant (or shown the "no tenant available" state).
6. **Given** a super admin soft-deletes a tenant, **When** they later choose to restore it, **Then** the tenant returns with its previous data, memberships, and in-tenant roles intact.
7. **Given** a super admin who is not a member of tenant C, **When** they choose tenant C as their active tenant, **Then** they can read and write tenant C's data with tenant-admin-equivalent capabilities, and the active-tenant UI clearly indicates they are acting as super admin.
8. **Given** a non-super-admin user, **When** they attempt to reach any super-admin tenant administration screen or action by URL or otherwise, **Then** the system denies access and does not reveal information about tenants they do not belong to.

---

### Edge Cases

- **No accessible tenant**: A user who is not a member of any tenant (new account, removed from all, every tenant suspended) is shown a clearly-labelled "no tenant available" state and cannot reach any tenant-scoped feature.
- **Last tenant became unavailable**: When the most recently used tenant has been suspended or the user's membership was revoked, the system silently falls back to another available tenant (or to the no-tenant state) rather than erroring on sign-in.
- **Cross-tenant data attempts**: Any request — direct URL, form submission, API call — that targets a record outside the active tenant is treated as not found, with no information disclosure about whether the record exists elsewhere.
- **Tenant switch mid-edit**: If a user switches tenants while an unsaved form is open, the user is warned about losing the in-progress work before the switch completes.
- **Concurrent sessions in different tenants**: A single user signed in on two devices may have a different active tenant on each device; switching on one device does not change the active tenant on the other.
- **Membership change during active session**: If a user is removed from their active tenant by another administrator, the next request fails the active-tenant check and the user is moved to another available tenant or to the no-tenant state.
- **Super admin acting inside a tenant**: A super admin may set any tenant — including tenants they do not belong to — as their active tenant and act with tenant-admin-equivalent capabilities; the active-tenant UI must clearly indicate they are acting as super admin so it is never confused with regular membership.
- **Tenant deletion safety**: Suspended and soft-deleted tenants do not appear in regular users' tenant switchers but remain visible to super admins; soft-deleted tenants can be restored by a super admin and recover their previous data, memberships, and in-tenant roles.
- **Last tenant admin protection**: A tenant admin who is the last remaining tenant admin cannot be removed or demoted to member by any tenant admin (including themselves); only a super admin can resolve this — e.g., by promoting another member first.

## Requirements *(mandatory)*

### Functional Requirements

#### Tenancy and isolation

- **FR-001**: The system MUST associate every tenant-scoped record with exactly one owning tenant for the lifetime of the record.
- **FR-002**: The system MUST ensure that, by default, every read of a tenant-scoped record returns only records belonging to the active tenant of the current user, with no opt-out available to regular user-facing code paths.
- **FR-003**: The system MUST ensure that every create, update, and delete on a tenant-scoped record either applies to the active tenant or is rejected, so that cross-tenant writes are impossible from regular user-facing code paths.
- **FR-004**: The system MUST treat any request that references a tenant-scoped record outside the active tenant as not found, without revealing whether the record exists in another tenant.

#### Membership and active tenant

- **FR-005**: The system MUST support a user being a member of zero, one, or many tenants simultaneously.
- **FR-006**: The system MUST record, per user, which tenant is currently active and which tenant they used most recently.
- **FR-007**: The system MUST allow a user with two or more tenant memberships to switch their active tenant to any tenant they currently belong to, from any authenticated screen.
- **FR-008**: The system MUST update the user's "most recently used tenant" whenever they successfully switch to or are auto-assigned to a tenant.
- **FR-009**: The system MUST display the active tenant in the UI on every authenticated tenant-scoped screen so the user can tell which tenant they are operating in.

#### Sign-in behaviour

- **FR-010**: The system MUST, on successful sign-in, set the active tenant to the user's most recently used tenant when that tenant still exists and the user still belongs to it.
- **FR-011**: The system MUST, on sign-in when the most recently used tenant is unavailable, fall back to another tenant the user belongs to using a deterministic rule, or to the "no tenant available" state when none exist.
- **FR-012**: The system MUST, on first-ever sign-in for a user with at least one tenant membership, deterministically pick one of their tenants as the initial active tenant and record it as most-recently-used.
- **FR-013**: The system MUST display a clearly-labelled "no tenant available" state to users who have no available tenant, preventing access to tenant-scoped features and explaining the situation in plain language.

#### Super admin

- **FR-014**: The system MUST support a system-wide super-admin role that is independent of tenant membership.
- **FR-015**: A super admin MUST be able to list, view, create, update, suspend, reactivate, and delete tenants from a dedicated tenant administration area.
- **FR-016**: A super admin MUST be able to add and remove members on any tenant from the tenant administration area and MUST be able to assign or change each membership's in-tenant role (tenant admin or member).
- **FR-017**: The system MUST restrict every super-admin screen and action to users carrying the super-admin role, and MUST deny access without leaking information to other users.
- **FR-018**: Suspended or deleted tenants MUST NOT appear in regular users' tenant switchers, but MUST remain visible to super admins for administration. Deletion MUST be a soft delete: the tenant and its tenant-scoped data are hidden from every regular flow but retained, and a super admin MUST be able to restore a deleted tenant — restoring it returns its data, memberships, and membership roles to the state they were in at the moment of deletion.

#### Tenant lifecycle effects

- **FR-019**: When a tenant is suspended, the system MUST prevent members from using it as their active tenant and MUST move any affected current sessions to another available tenant or to the no-tenant state on the next request.
- **FR-020**: When a user's membership in their active tenant is removed, the system MUST detect this on the user's next request and move them to another available tenant or to the no-tenant state.

#### Tenant member roles

- **FR-021**: The system MUST support exactly two in-tenant roles per membership: **tenant admin** and **member**. A user's role is recorded on the membership and applies only within that tenant; the same user may hold different roles in different tenants.
- **FR-021a**: A **member** MUST be able to use the tenant as their active tenant and read and write tenant-scoped records as permitted by feature-level rules, but MUST NOT manage tenant settings or tenant membership.
- **FR-021b**: A **tenant admin** MUST have every capability of a member plus the ability to manage that tenant's membership (add and remove users, change a member's in-tenant role between tenant admin and member) and to update that tenant's display name and other tenant-level settings, scoped strictly to their own tenant.
- **FR-021c**: When a super admin creates a new tenant, the system MUST require at least one initial tenant admin to be assigned for that tenant; an active tenant MUST always have at least one tenant admin.
- **FR-021d**: A tenant admin MUST NOT be able to remove the last remaining tenant admin from their tenant, demote themselves if they are the last remaining tenant admin, or suspend/delete the tenant itself — those actions remain super-admin-only.

#### Super-admin scope inside tenants

- **FR-025**: A super admin MUST be able to set any tenant in the system as their active tenant and, while it is active, read and write that tenant's data with the same capabilities as a tenant admin of that tenant, even when they hold no membership in it.
- **FR-025a**: A super admin acting inside a tenant they are not a member of MUST be visibly indicated as a super admin in the active-tenant UI, so it is obvious they are not a regular member.
- **FR-025b**: The system MUST attribute every tenancy-relevant action a super admin takes inside a tenant to that super admin (not to an impersonated user) in the audit log defined by FR-022, recording the tenant context and that the actor was acting as super admin.

#### Cross-cutting

- **FR-022**: The system MUST log security-relevant tenancy events — sign-in tenant restored, tenant switch, super-admin tenant create/update/suspend/delete, super-admin membership change, denied cross-tenant access — with enough detail to attribute the action to a user and tenant.
- **FR-023**: The system MUST reuse existing Laravel, Inertia, React, and Wayfinder conventions unless the specification documents an approved exception.
- **FR-024**: The system MUST define automated verification for every behaviour change in this feature, including the user story or requirement each test proves — at minimum: tenant-scoped read/write isolation, denied cross-tenant reads return not-found, tenant switching from the UI, sign-in restores last tenant, fallback when last tenant is unavailable, no-tenant state, tenant-admin member/role management limited to their own tenant, last-tenant-admin protection, super-admin CRUD on tenants (including create-with-initial-tenant-admin), super-admin soft-delete and restore, super-admin acting inside a non-member tenant with audit attribution, and access denial for non-super-admins and non-tenant-admins.

### Key Entities

- **Tenant**: An isolated workspace that owns a set of tenant-scoped records. Key attributes include a display name, a status (active or suspended), and timestamps relevant to administration.
- **User**: An authenticated person who may belong to zero, one, or many tenants and who has at most one active tenant at a time. Holds an indicator of which tenant they most recently used.
- **Tenant Membership**: The link between a user and a tenant, capturing that the user has access to that tenant's data and may switch to it. Carries the in-tenant role (tenant admin or member) which applies only within that tenant.
- **Active Tenant Context**: The transient, per-session indicator of which tenant the user is currently operating in. Used to scope every tenant-scoped read and write.
- **Super Admin Role**: A system-wide marker on a user that grants access to the tenant administration area independent of tenant membership.
- **Tenant Administration Action**: A super-admin operation against tenants or memberships (create tenant with initial tenant admin, update tenant, suspend/reactivate, soft-delete/restore, add/remove member, assign/change in-tenant role).
- **Tenant Member Role**: One of two values — *tenant admin* or *member* — carried on a tenant membership and determining what the user can do inside that single tenant.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of tenant-scoped reads and writes in the running application stay inside the active tenant — verified by automated tests and confirmed in production through zero substantiated cross-tenant data-exposure incidents over any rolling 30-day window.
- **SC-002**: A multi-tenant user can switch their active tenant from the UI and see the new tenant's data fully reflected within 2 seconds under normal operating conditions.
- **SC-003**: At least 95% of returning multi-tenant users sign in and reach their previously-used tenant without taking any extra action (no tenant chooser, no error page).
- **SC-004**: When a user's most recently used tenant becomes unavailable, the system places them into an available tenant or the no-tenant state on sign-in in 100% of tested unavailability scenarios, never showing an error page.
- **SC-005**: A super admin can create a new tenant, assign its initial tenant admin, and have that tenant admin sign in and operate inside it in under 2 minutes.
- **SC-006**: 0 unauthorized accesses to the super-admin tenant administration area or to another tenant's member-management actions succeed across automated authorization tests covering regular members, tenant admins of other tenants, and unauthenticated requests.
- **SC-008**: 100% of super-admin actions taken inside a tenant they are not a member of are attributable in the audit log to the super admin (not to any tenant member) with tenant context, verified by automated tests.
- **SC-007**: At least 95% of users with multiple tenant memberships correctly identify their current active tenant within 5 seconds of landing on any authenticated screen during usability validation.

## Assumptions

- The product is delivered as a single web application with a shared database and shared tables; tenants are not separated by database, schema, or subdomain.
- The active tenant is tracked per authenticated session, not per browser tab; switching applies to the whole session on that device.
- Tenant identity is a server-side concern carried in session state; tenant identifiers are not relied upon as a security boundary when present in URLs or client-side state.
- Tenant-scoped resources are introduced incrementally; this feature establishes the tenancy boundary and switching behaviour, and individual existing features (e.g., user management) will adopt tenant scoping as separate slices unless explicitly listed here.
- Users join tenants by being added either by a super admin or by a tenant admin of that tenant; richer invitation flows (e.g., email invites, self-service signup, accept/decline) are out of scope for this slice and may follow later.
- The existing authentication, session, and authorization mechanisms are reused for sign-in, sign-out, and access control; this feature adds the tenancy layer on top.
- "Most recently used tenant" is updated on every successful tenant switch and on every successful sign-in that restores or assigns an active tenant.
- Super admin is a small, trusted set of system operators; the role is granted out-of-band (e.g., via seed data or an internal tool) and is not self-service.
- Deletion of a tenant is a soft delete: the tenant and its tenant-scoped data are hidden from every regular flow but retained so that a super admin can restore the tenant with its previous data, memberships, and in-tenant roles (see FR-018). No hard-delete or scheduled purge is in scope for this slice.
