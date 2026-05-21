# Research: User Management

## Decision: Reuse the existing user account data model

**Rationale**: The current `users` table already contains the core fields needed by the feature: name, email, email verification timestamp, password, remember token, and timestamps. The feature scope is browse, search, create, update, and delete for core accounts. Adding status, roles, or audit fields would expand scope beyond the approved specification.

**Alternatives considered**:

- Add a dedicated account status field: rejected for this version because the spec only requires administrators to distinguish users and manage core details.
- Add separate administrator/user management tables: rejected because it duplicates the existing account model and violates the project preference for Laravel-first simplicity.

## Decision: Use paginated query-string search

**Rationale**: A `search` query parameter makes filtered results shareable, keeps browser navigation predictable, and works naturally with Laravel pagination. Pagination links should preserve the current search term so administrators do not lose context when moving between pages.

**Alternatives considered**:

- Client-only filtering: rejected because it only covers loaded rows and fails once the list is paginated.
- Dedicated advanced filtering: rejected because the spec only requires name/email search.

## Decision: Use same-screen dialogs backed by normal Inertia form submissions

**Rationale**: The feature explicitly requires add, edit, and delete actions directly on the same screen via pop-ups. Existing UI components include dialogs, buttons, inputs, labels, and error display components, and current settings pages already use Inertia forms for validation feedback.

**Alternatives considered**:

- Separate create/edit pages: rejected because it conflicts with the same-screen workflow.
- Custom asynchronous client state without Inertia forms: rejected because it bypasses existing form conventions and typed route/action helpers.

## Decision: Validate user creation and updates with form request classes

**Rationale**: Create and update have different uniqueness rules and required fields. Form requests keep validation separate from controller flow and match Laravel conventions already used for settings requests.

**Alternatives considered**:

- Inline controller validation: acceptable for small cases but rejected because the feature has multiple write actions and benefits from named request objects.
- Frontend-only validation: rejected because server-side validation is required for data integrity.

## Decision: Prevent self-deletion in the delete action

**Rationale**: The specification requires administrators not to delete their own active account. Enforcing this in the server action prevents accidental lockout even if the frontend state is stale or manipulated.

**Alternatives considered**:

- Hide only the delete button for the current user: rejected as insufficient because it is only a presentation safeguard.
- Require password confirmation for all admin deletion: rejected for this feature because the spec requires explicit confirmation, not re-authentication.

## Decision: Treat Laravel Boost documentation tools as preferred but unavailable in this planning session

**Rationale**: Project instructions require using Laravel Boost `search-docs` before code changes. The current tool surface does not expose Laravel Boost MCP tools, so implementation tasks must call those tools if they become available before editing Laravel or Inertia code.

**Alternatives considered**:

- Browse public documentation: rejected for planning because the project specifically prefers version-aware Laravel Boost results and no implementation code is being changed in this phase.
