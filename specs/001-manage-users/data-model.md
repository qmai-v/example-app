# Data Model: User Management

## User

Represents an application account that can be listed and managed by an administrator.

### Fields

| Field | Source | Rules |
|-------|--------|-------|
| `id` | Existing account identity | Stable unique identifier; not editable |
| `name` | Existing user profile | Required for create and update; displayed in the list |
| `email` | Existing user profile | Required, valid email format, unique across users, displayed in the list |
| `email_verified_at` | Existing account state | Display-only indicator for whether the account is verified |
| `password` | Existing credential field | Required on create; optional or unchanged on edit unless password management is explicitly added |
| `created_at` | Existing timestamps | Display-only or available for sorting/context if needed |
| `updated_at` | Existing timestamps | Display-only or available for stale update checks if implemented |

### Relationships

- A user can be the current administrator performing management actions.
- A user can be the selected subject of edit or delete actions.

### Validation

- Name is required and must be a normal text value suitable for display.
- Email is required, must be a valid email address, and must remain unique.
- Password is required when creating a user and must satisfy existing password policy conventions.
- Update validation must ignore the selected user's current email when enforcing uniqueness.
- Delete validation must reject attempts to delete the current administrator.

### State Transitions

```text
Not listed -> Created -> Listed
Listed -> Updated -> Listed with changed details
Listed -> Deleted -> Removed from normal management results
```

## Administrator

Represents the authenticated user authorized to access the management screen.

### Rules

- Must be authenticated.
- Must satisfy the application's authorization requirement for user management.
- Cannot delete their own active account from the management screen.

## User Management List

Represents the current paginated and filtered set of users shown on screen.

### Fields

| Field | Rules |
|-------|-------|
| `users` | Paginated user records matching the current search |
| `search` | Optional name/email search term, trimmed and case-insensitive |
| `pagination` | Current page, page links, and total result context |

### Rules

- Search by name and email.
- Preserve search when moving between pages.
- After deletion, recover gracefully if the current page becomes empty.

## User Form Submission

Represents details submitted from add or edit dialogs.

### Fields

| Field | Create | Update |
|-------|--------|--------|
| `name` | Required | Required |
| `email` | Required, unique | Required, unique except current user |
| `password` | Required | Out of scope unless explicitly added during implementation |

### Error Handling

- Validation errors remain visible inside the dialog.
- Successful submissions close the dialog and refresh the visible list.

## Deletion Confirmation

Represents explicit administrator approval to delete a selected user.

### Fields

| Field | Rules |
|-------|-------|
| `user_id` | Required selected user identity |
| `confirmation` | Provided by the administrator's explicit dialog action |

### Rules

- Confirmation must identify the affected user.
- Canceling the dialog must not change data.
- Confirming deletion must remove the user from normal management results unless blocked by a safeguard.
