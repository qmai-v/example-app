# Contract: User Management

## Web Routes

| Name | Method | Path | Purpose |
|------|--------|------|---------|
| `users.index` | GET | `/users` | Show paginated user management page |
| `users.store` | POST | `/users` | Create a user from the add dialog |
| `users.update` | PUT/PATCH | `/users/{user}` | Update a selected user from the edit dialog |
| `users.destroy` | DELETE | `/users/{user}` | Delete a selected user after confirmation |

All routes require an authenticated administrator. The index route accepts an optional `search` query parameter and a pagination page parameter.

## Inertia Page

`resources/js/pages/users/index.tsx`

### Props

| Prop | Type | Description |
|------|------|-------------|
| `users` | Paginated user list | Current page of user records, pagination metadata, and navigation links |
| `filters.search` | string or null | Current search term |
| `flash` | existing shared flash shape | Success or failure messages after write actions |
| `auth` | existing shared auth shape | Current administrator for self-delete safeguards |

### User Record Shape

| Field | Type | Description |
|-------|------|-------------|
| `id` | number | User identity |
| `name` | string | Display name |
| `email` | string | Email address |
| `email_verified_at` | string or null | Verification status indicator |
| `created_at` | string | Created timestamp for context if shown |

## Search Contract

- Search input sends `search` as a query parameter to `users.index`.
- Search matches name and email.
- Search is case-insensitive and trims surrounding whitespace.
- Pagination links preserve the current search term.
- Empty results show a clear empty state and keep the search control available.

## Add Dialog Contract

### Fields

| Field | Required | Notes |
|-------|----------|-------|
| `name` | Yes | User display name |
| `email` | Yes | Must be unique |
| `password` | Yes | Must follow existing password rules |

### Success

- Dialog closes.
- Success feedback appears.
- List refreshes on the same page.

### Failure

- Dialog remains open.
- Field errors appear next to the relevant controls.
- Existing input is preserved where appropriate.

## Edit Dialog Contract

### Fields

| Field | Required | Notes |
|-------|----------|-------|
| `name` | Yes | Pre-filled from selected user |
| `email` | Yes | Pre-filled from selected user and unique except for that user |

### Success

- Dialog closes.
- Success feedback appears.
- Visible row reflects updated details.

### Failure

- Dialog remains open.
- Field errors appear next to the relevant controls.
- Existing input is preserved where appropriate.

## Delete Dialog Contract

### Behavior

- Dialog title and description identify the selected user by name and email.
- Cancel closes the dialog without data changes.
- Confirm submits deletion for the selected user.
- Current administrator cannot delete their own active account.

### Success

- Dialog closes.
- Success feedback appears.
- Deleted user disappears from normal management results.
- If the current page becomes empty, the page moves to the nearest available page.

### Failure

- Dialog remains open or returns focus to the row with clear failure feedback.
- Self-deletion attempts show a clear explanation.

## Verification Contract

- Feature tests prove access control, search, pagination, create, update, delete, validation failures, and self-deletion prevention.
- Frontend static checks prove TypeScript route/action usage and page prop types compile.
