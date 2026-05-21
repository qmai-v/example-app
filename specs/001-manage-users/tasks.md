# Tasks: User Management

**Input**: Design documents from `/specs/001-manage-users/`

**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/user-management.md](./contracts/user-management.md), [quickstart.md](./quickstart.md)

**Tests**: Automated tests are REQUIRED by FR-017 and the project constitution. Pest feature tests must be written before implementation and fail first.

**Organization**: Tasks are grouped by user story so each story can be implemented and tested independently.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel because it touches different files and does not depend on incomplete tasks
- **[Story]**: Maps to user stories from [spec.md](./spec.md)
- Every task includes exact file paths

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Confirm conventions and prepare generated files without changing feature behavior.

- [X] T001 Review Laravel/Inertia user-management requirements in `specs/001-manage-users/spec.md`, `specs/001-manage-users/plan.md`, and `specs/001-manage-users/contracts/user-management.md`
- [X] T002 Use Laravel Boost `search-docs` for routing, validation, pagination, Inertia forms, and Inertia React patterns before editing `routes/web.php`, `app/Http/Controllers/UserController.php`, and `resources/js/pages/users/index.tsx`
- [X] T003 [P] Inspect existing settings form and dialog patterns in `resources/js/pages/settings/profile.tsx` and `resources/js/components/delete-user.tsx`
- [X] T004 [P] Inspect existing authentication route grouping in `routes/web.php` and `routes/settings.php`
- [X] T005 Generate missing Laravel classes with Artisan for `app/Http/Controllers/UserController.php`, `app/Http/Requests/StoreUserRequest.php`, `app/Http/Requests/UpdateUserRequest.php`, and `tests/Feature/UserManagementTest.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Establish shared route, authorization, validation, and type contracts required by all user stories.

**CRITICAL**: No user story work can begin until this phase is complete.

- [X] T006 Add authenticated user-management route definitions for `users.index`, `users.store`, `users.update`, and `users.destroy` in `routes/web.php`
- [X] T007 Implement shared administrator access guard or authorization checks for user-management actions in `app/Http/Controllers/UserController.php`
- [X] T008 Implement shared user list query shape with selected fields and pagination defaults in `app/Http/Controllers/UserController.php`
- [X] T009 [P] Implement create-user validation rules in `app/Http/Requests/StoreUserRequest.php`
- [X] T010 [P] Implement update-user validation rules in `app/Http/Requests/UpdateUserRequest.php`
- [X] T011 Refresh Wayfinder generated route and action files for `resources/js/actions/App/Http/Controllers/UserController.ts` and `resources/js/routes/users/index.ts`
- [X] T012 [P] Define shared TypeScript page prop and user row types in `resources/js/pages/users/index.tsx`

**Checkpoint**: Foundation ready - user story implementation can now begin.

---

## Phase 3: User Story 1 - Browse and Find Users (Priority: P1) MVP

**Goal**: Administrators can open a paginated user list, search by name or email, and see empty search results clearly.

**Independent Test**: Open the user management page, page through seeded users, search by name/email, and confirm matching, non-matching, and empty states.

### Tests for User Story 1

- [X] T013 [P] [US1] Add Pest test for authenticated access and unauthenticated redirect in `tests/Feature/UserManagementTest.php`
- [X] T014 [P] [US1] Add Pest test for paginated users in `tests/Feature/UserManagementTest.php`
- [X] T015 [P] [US1] Add Pest test for name and email search filtering in `tests/Feature/UserManagementTest.php`
- [X] T016 [P] [US1] Add Pest test for empty search results in `tests/Feature/UserManagementTest.php`

### Implementation for User Story 1

- [X] T017 [US1] Implement `index` action with search, pagination, and preserved query strings in `app/Http/Controllers/UserController.php`
- [X] T018 [US1] Create user management Inertia page shell with heading, search form, and table layout in `resources/js/pages/users/index.tsx`
- [X] T019 [US1] Add pagination controls that preserve current search state in `resources/js/pages/users/index.tsx`
- [X] T020 [US1] Add empty-state messaging and clear-search behavior in `resources/js/pages/users/index.tsx`
- [X] T021 [US1] Add navigation entry for the user management page if consistent with sidebar patterns in `resources/js/components/nav-main.tsx`
- [X] T022 [US1] Run US1 tests with `php artisan test --compact --filter=UserManagementTest` for `tests/Feature/UserManagementTest.php`

**Checkpoint**: User Story 1 is independently functional and is the suggested MVP.

---

## Phase 4: User Story 2 - Add a User In Place (Priority: P2)

**Goal**: Administrators can open an add-user dialog, create a valid user, and see validation errors without leaving the page.

**Independent Test**: Open the add dialog, submit valid details, confirm the user appears, then submit invalid or duplicate details and confirm inline dialog errors.

### Tests for User Story 2

- [X] T023 [P] [US2] Add Pest test for creating a valid user in `tests/Feature/UserManagementTest.php`
- [X] T024 [P] [US2] Add Pest test for required-field and duplicate-email validation on create in `tests/Feature/UserManagementTest.php`

### Implementation for User Story 2

- [X] T025 [US2] Implement `store` action and success feedback in `app/Http/Controllers/UserController.php`
- [X] T026 [US2] Wire `StoreUserRequest` into user creation and password hashing in `app/Http/Requests/StoreUserRequest.php` and `app/Http/Controllers/UserController.php`
- [X] T027 [US2] Add add-user dialog form using Wayfinder controller action imports in `resources/js/pages/users/index.tsx`
- [X] T028 [US2] Show create validation errors inside the add-user dialog in `resources/js/pages/users/index.tsx`
- [X] T029 [US2] Run US2 tests with `php artisan test --compact --filter=UserManagementTest` for `tests/Feature/UserManagementTest.php`

**Checkpoint**: User Stories 1 and 2 both work independently.

---

## Phase 5: User Story 3 - Edit a User In Place (Priority: P3)

**Goal**: Administrators can open an edit-user dialog with current details, save valid changes, and see validation errors without leaving the page.

**Independent Test**: Select a listed user, edit name/email, confirm row updates, then submit invalid or duplicate details and confirm inline dialog errors.

### Tests for User Story 3

- [X] T030 [P] [US3] Add Pest test for updating an existing user's name and email in `tests/Feature/UserManagementTest.php`
- [X] T031 [P] [US3] Add Pest test for update validation and duplicate-email protection in `tests/Feature/UserManagementTest.php`

### Implementation for User Story 3

- [X] T032 [US3] Implement `update` action and success feedback in `app/Http/Controllers/UserController.php`
- [X] T033 [US3] Wire `UpdateUserRequest` into user updates with current-user email uniqueness handling in `app/Http/Requests/UpdateUserRequest.php` and `app/Http/Controllers/UserController.php`
- [X] T034 [US3] Add edit-user dialog form with pre-filled selected user details in `resources/js/pages/users/index.tsx`
- [X] T035 [US3] Show update validation errors inside the edit-user dialog in `resources/js/pages/users/index.tsx`
- [X] T036 [US3] Run US3 tests with `php artisan test --compact --filter=UserManagementTest` for `tests/Feature/UserManagementTest.php`

**Checkpoint**: User Stories 1, 2, and 3 all work independently.

---

## Phase 6: User Story 4 - Delete a User In Place (Priority: P4)

**Goal**: Administrators can confirm deletion in a dialog, remove eligible users, cancel safely, and cannot delete their own active account.

**Independent Test**: Select a listed user, cancel deletion and confirm no change, confirm deletion and verify removal, then attempt self-deletion and verify it is blocked.

### Tests for User Story 4

- [X] T037 [P] [US4] Add Pest test for deleting an eligible user in `tests/Feature/UserManagementTest.php`
- [X] T038 [P] [US4] Add Pest test that canceling deletion makes no server-side change in `tests/Feature/UserManagementTest.php`
- [X] T039 [P] [US4] Add Pest test that the current administrator cannot delete their own account in `tests/Feature/UserManagementTest.php`
- [X] T040 [P] [US4] Add Pest test for recovering to an available page after deleting the last user on a page in `tests/Feature/UserManagementTest.php`

### Implementation for User Story 4

- [X] T041 [US4] Implement `destroy` action with self-deletion protection and success/failure feedback in `app/Http/Controllers/UserController.php`
- [X] T042 [US4] Add delete confirmation dialog that identifies the selected user in `resources/js/pages/users/index.tsx`
- [X] T043 [US4] Hide or disable delete affordance for the current administrator in `resources/js/pages/users/index.tsx`
- [X] T044 [US4] Handle post-delete list refresh and empty-page recovery in `app/Http/Controllers/UserController.php` and `resources/js/pages/users/index.tsx`
- [X] T045 [US4] Run US4 tests with `php artisan test --compact --filter=UserManagementTest` for `tests/Feature/UserManagementTest.php`

**Checkpoint**: All user stories are independently functional.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Verify integration, formatting, and frontend quality across all stories.

- [X] T046 [P] Run route inspection for user-management routes with `php artisan route:list --path=users --except-vendor` covering `routes/web.php`
- [X] T047 [P] Run PHP formatter with `vendor/bin/pint --dirty --format agent` covering `app/Http/Controllers/UserController.php`, `app/Http/Requests/StoreUserRequest.php`, `app/Http/Requests/UpdateUserRequest.php`, and `tests/Feature/UserManagementTest.php`
- [X] T048 [P] Run TypeScript validation with `npm run types:check` covering `resources/js/pages/users/index.tsx`
- [X] T049 [P] Run frontend lint check with `npm run lint:check` covering `resources/js/pages/users/index.tsx` and `resources/js/components/nav-main.tsx`
- [X] T050 [P] Run frontend format check with `npm run format:check` covering `resources/js/pages/users/index.tsx` and `resources/js/components/nav-main.tsx`
- [X] T051 Run full affected Pest suite with `php artisan test --compact --filter=UserManagementTest` covering `tests/Feature/UserManagementTest.php`
- [X] T052 Verify quickstart commands and update task status in `specs/001-manage-users/tasks.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 Setup**: No dependencies; start immediately.
- **Phase 2 Foundational**: Depends on Phase 1; blocks all user stories.
- **Phase 3 US1**: Depends on Phase 2; delivers the MVP.
- **Phase 4 US2**: Depends on Phase 2 and benefits from US1 page shell.
- **Phase 5 US3**: Depends on Phase 2 and benefits from US1 page shell.
- **Phase 6 US4**: Depends on Phase 2 and benefits from US1 page shell.
- **Phase 7 Polish**: Depends on all selected user stories being complete.

### User Story Dependencies

- **US1 Browse and Find Users**: Can start after Phase 2; no dependency on other user stories.
- **US2 Add a User In Place**: Can start after Phase 2, but reuses the US1 page shell if US1 is already complete.
- **US3 Edit a User In Place**: Can start after Phase 2, but reuses the US1 page shell if US1 is already complete.
- **US4 Delete a User In Place**: Can start after Phase 2, but reuses the US1 page shell if US1 is already complete.

### Within Each User Story

- Write Pest tests first and confirm they fail before implementation.
- Implement backend route/controller/request behavior before wiring frontend forms.
- Refresh Wayfinder files before relying on generated frontend imports.
- Complete the story-specific verification command before moving to the next story.

## Parallel Opportunities

- T003 and T004 can run in parallel after T001.
- T009 and T010 can run in parallel after T006.
- US1 tests T013-T016 can run in parallel before US1 implementation.
- US2 tests T023-T024 can run in parallel before US2 implementation.
- US3 tests T030-T031 can run in parallel before US3 implementation.
- US4 tests T037-T040 can run in parallel before US4 implementation.
- Polish checks T046-T050 can run in parallel after implementation is complete.

## Parallel Example: User Story 1

```bash
Task: "T013 [P] [US1] Add Pest test for authenticated access and unauthenticated redirect in tests/Feature/UserManagementTest.php"
Task: "T014 [P] [US1] Add Pest test for paginated users in tests/Feature/UserManagementTest.php"
Task: "T015 [P] [US1] Add Pest test for name and email search filtering in tests/Feature/UserManagementTest.php"
Task: "T016 [P] [US1] Add Pest test for empty search results in tests/Feature/UserManagementTest.php"
```

## Parallel Example: User Story 4

```bash
Task: "T037 [P] [US4] Add Pest test for deleting an eligible user in tests/Feature/UserManagementTest.php"
Task: "T038 [P] [US4] Add Pest test that canceling deletion makes no server-side change in tests/Feature/UserManagementTest.php"
Task: "T039 [P] [US4] Add Pest test that the current administrator cannot delete their own account in tests/Feature/UserManagementTest.php"
Task: "T040 [P] [US4] Add Pest test for recovering to an available page after deleting the last user on a page in tests/Feature/UserManagementTest.php"
```

## Implementation Strategy

### MVP First (US1 Only)

1. Complete Phase 1 setup.
2. Complete Phase 2 foundational routes, validation, types, and Wayfinder refresh.
3. Complete Phase 3 US1 browse/search/pagination.
4. Stop and validate with `php artisan test --compact --filter=UserManagementTest`.

### Incremental Delivery

1. Deliver US1 as the browse/search MVP.
2. Add US2 create workflow and validate independently.
3. Add US3 edit workflow and validate independently.
4. Add US4 delete workflow and validate independently.
5. Run Phase 7 checks across the integrated feature.

### Parallel Team Strategy

After Phase 2, one developer can continue US1 page shell work while others write story-specific Pest coverage for US2, US3, and US4. Frontend dialog work should coordinate through `resources/js/pages/users/index.tsx` to avoid conflicting edits.

## Notes

- `[P]` tasks are independent by file or verification command.
- `[US1]`, `[US2]`, `[US3]`, and `[US4]` map directly to prioritized user stories in [spec.md](./spec.md).
- No new runtime dependencies or base directories are planned.
- Commit after completing each story or another coherent task group.
