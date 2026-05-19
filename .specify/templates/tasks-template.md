---

description: "Task list template for feature implementation"
---

# Tasks: [FEATURE NAME]

**Input**: Design documents from `/specs/[###-feature-name]/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Automated tests are REQUIRED for every behavior change. Include the
narrowest Pest, frontend, build, or static-check tasks needed to prove the
feature. If a change is documentation-only or otherwise cannot be tested,
document the reason in the generated tasks.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Laravel app**: `app/`, `database/`, `routes/`, `resources/js/`, `tests/`
- **Inertia pages**: `resources/js/pages`
- **React components**: `resources/js/components`
- **Wayfinder imports**: `resources/js/actions` and `resources/js/routes`
- **Tests**: `tests/Feature` for user-visible behavior, `tests/Unit` for
  isolated logic

<!--
  ============================================================================
  IMPORTANT: The tasks below are SAMPLE TASKS for illustration purposes only.

  The /speckit-tasks command MUST replace these with actual tasks based on:
  - User stories from spec.md (with their priorities P1, P2, P3...)
  - Feature requirements from plan.md
  - Entities from data-model.md
  - Endpoints from contracts/

  Tasks MUST be organized by user story so each story can be:
  - Implemented independently
  - Tested independently
  - Delivered as an MVP increment

  DO NOT keep these sample tasks in the generated tasks.md file.
  ============================================================================
-->

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [ ] T001 Create project structure per implementation plan
- [ ] T002 Confirm existing Laravel/Inertia dependencies and tooling
- [ ] T003 [P] Configure linting and formatting tools
- [ ] T004 Confirm existing Laravel/Inertia sibling patterns to reuse

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

Examples of foundational tasks (adjust based on your project):

- [ ] T005 Setup database schema and migrations with Artisan
- [ ] T006 [P] Implement Laravel authentication/authorization policy changes
- [ ] T007 [P] Setup named routes, middleware, and Inertia page contracts
- [ ] T008 Create base Eloquent models, factories, and seeders
- [ ] T009 Configure validation, error handling, and logging infrastructure
- [ ] T010 Setup environment configuration management

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - [Title] (Priority: P1) 🎯 MVP

**Goal**: [Brief description of what this story delivers]

**Independent Test**: [How to verify this story works on its own]

### Tests for User Story 1 (REQUIRED) ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T011 [P] [US1] Pest feature test for [user journey] in tests/Feature/[Name]Test.php
- [ ] T012 [P] [US1] Frontend/static check task for [page/component] if React behavior changes

### Implementation for User Story 1

- [ ] T013 [P] [US1] Create [Entity1] model/migration/factory in app/Models and database/
- [ ] T014 [P] [US1] Create [Inertia page/component] in resources/js/pages or resources/js/components
- [ ] T015 [US1] Implement Laravel action/controller/service in app/ (depends on T013)
- [ ] T016 [US1] Implement named route and Wayfinder-backed navigation/action
- [ ] T017 [US1] Add validation, authorization, and error handling
- [ ] T018 [US1] Add logging for user story 1 operations

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - [Title] (Priority: P2)

**Goal**: [Brief description of what this story delivers]

**Independent Test**: [How to verify this story works on its own]

### Tests for User Story 2 (REQUIRED) ⚠️

- [ ] T019 [P] [US2] Pest feature test for [user journey] in tests/Feature/[Name]Test.php
- [ ] T020 [P] [US2] Frontend/static check task for [page/component] if React behavior changes

### Implementation for User Story 2

- [ ] T021 [P] [US2] Create [Entity] model/migration/factory in app/Models and database/
- [ ] T022 [US2] Implement [Service] in app/
- [ ] T023 [US2] Implement [route/page/feature] in routes/ and resources/js/
- [ ] T024 [US2] Integrate with User Story 1 components (if needed)

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: User Story 3 - [Title] (Priority: P3)

**Goal**: [Brief description of what this story delivers]

**Independent Test**: [How to verify this story works on its own]

### Tests for User Story 3 (REQUIRED) ⚠️

- [ ] T025 [P] [US3] Pest feature test for [user journey] in tests/Feature/[Name]Test.php
- [ ] T026 [P] [US3] Frontend/static check task for [page/component] if React behavior changes

### Implementation for User Story 3

- [ ] T027 [P] [US3] Create [Entity] model/migration/factory in app/Models and database/
- [ ] T028 [US3] Implement [Service] in app/
- [ ] T029 [US3] Implement [route/page/feature] in routes/ and resources/js/

**Checkpoint**: All user stories should now be independently functional

---

[Add more user story phases as needed, following the same pattern]

---

## Phase N: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [ ] TXXX [P] Documentation updates in docs/ only if explicitly requested
- [ ] TXXX Code cleanup and refactoring
- [ ] TXXX Performance optimization across all stories
- [ ] TXXX [P] Additional unit tests for isolated logic in tests/Unit/
- [ ] TXXX Security hardening
- [ ] TXXX Run quickstart.md validation
- [ ] TXXX Run `vendor/bin/pint --dirty --format agent` for PHP changes
- [ ] TXXX Run affected `php artisan test --compact` command
- [ ] TXXX Run affected npm checks for React/TypeScript changes

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - User stories can then proceed in parallel (if staffed)
  - Or sequentially in priority order (P1 → P2 → P3)
- **Polish (Final Phase)**: Depends on all desired user stories being complete

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P2)**: Can start after Foundational (Phase 2) - May integrate with US1 but should be independently testable
- **User Story 3 (P3)**: Can start after Foundational (Phase 2) - May integrate with US1/US2 but should be independently testable

### Within Each User Story

- Tests MUST be written and FAIL before implementation
- Models before services
- Services before routes, endpoints, and pages
- Core implementation before integration
- Story complete before moving to next priority

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel
- All Foundational tasks marked [P] can run in parallel (within Phase 2)
- Once Foundational phase completes, all user stories can start in parallel (if team capacity allows)
- All tests for a user story marked [P] can run in parallel
- Models within a story marked [P] can run in parallel
- Different user stories can be worked on in parallel by different team members

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together:
Task: "Pest feature test for [user journey] in tests/Feature/[Name]Test.php"
Task: "Frontend/static check task for [page/component] if React behavior changes"

# Launch all models for User Story 1 together:
Task: "Create [Entity1] model/migration/factory in app/Models and database/"
Task: "Create [Entity2] model/migration/factory in app/Models and database/"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: Test User Story 1 independently
5. Deploy/demo if ready

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → Deploy/Demo (MVP!)
3. Add User Story 2 → Test independently → Deploy/Demo
4. Add User Story 3 → Test independently → Deploy/Demo
5. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1
   - Developer B: User Story 2
   - Developer C: User Story 3
3. Stories complete and integrate independently

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Verify tests fail before implementing
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence
