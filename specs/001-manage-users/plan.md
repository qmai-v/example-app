# Implementation Plan: User Management

**Branch**: `001-manage-users` | **Date**: 2026-05-20 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-manage-users/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command. See `.specify/templates/plan-template.md` for the execution workflow.

## Summary

Create an authenticated administrator user management screen where admins can browse paginated users, search by name or email, and add, edit, or delete users through same-screen dialogs. The implementation will follow the existing Laravel/Inertia React starter structure: Laravel web routes and controller actions return Inertia responses, request objects validate create/update submissions, the existing `User` model and factory back the data, Wayfinder-generated actions/routes power frontend forms, and Pest feature tests prove the user-visible workflows.

## Technical Context

**Language/Version**: PHP 8.4, TypeScript 5.7, React 19

**Primary Dependencies**: Laravel 13, Fortify, Inertia Laravel 3, Inertia React 3, Wayfinder, Tailwind CSS 4, existing Radix-based UI components, Pest 4, Pint, ESLint, Prettier

**Storage**: Existing application database through Eloquent `User`; local development currently includes SQLite

**Testing**: Pest feature tests via `php artisan test --compact`, frontend static checks via `npm run types:check`, and formatting checks/fixes via Pint, ESLint, and Prettier

**Target Platform**: Laravel web application served as an Inertia React SPA

**Project Type**: Web application feature

**Performance Goals**: Search results visible within 2 seconds for normal admin-sized user lists; add/edit/delete feedback visible without leaving the management screen

**Constraints**: No new runtime dependencies; no new base directories; use named routes and Wayfinder imports; keep modals accessible; preserve search state across pagination and write actions; prevent self-deletion

**Scale/Scope**: One administrator page, one Laravel controller, create/update request validation, existing `users` data, focused feature coverage for browse, search, pagination, create, update, delete, validation, and self-deletion protection

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **Laravel-first architecture**: Confirm the plan uses Laravel conventions,
  named routes, Eloquent, validation, authorization, and Artisan-generated files
  where applicable.
  - PASS: Use a Laravel controller, form requests, named web routes, Eloquent `User`, existing authentication middleware, and Artisan generators during implementation.
- **Inertia React contracts**: Confirm SPA pages live in `resources/js/pages`,
  server routes use Inertia responses, Wayfinder route/action imports are used
  where available, and deferred props include loading or empty states.
  - PASS: Add the page under `resources/js/pages`, render it through Inertia, and use generated imports from `@/actions` or `@/routes` after routes are generated.
- **Programmatic testing**: List the Pest, frontend, build, or static checks
  that will prove the behavior, including the minimum command set to run.
  - PASS: Add a Pest feature test for route access, search, pagination, creation, update, deletion, validation failures, and self-deletion protection. Run `php artisan test --compact --filter=UserManagementTest`, `vendor/bin/pint --dirty --format agent`, `npm run types:check`, and targeted frontend lint/format checks for touched resources.
- **Structure and dependencies**: Confirm no new base directories or
  dependencies are introduced without explicit approval, and cite existing
  sibling patterns to follow.
  - PASS: Reuse `app/Http/Controllers`, `app/Http/Requests`, `resources/js/pages`, `resources/js/components/ui`, `routes/web.php`, and `tests/Feature`. No dependency changes are planned.
- **Tooling and observability**: Identify required Pint, ESLint, Prettier,
  TypeScript, Artisan, database, browser-log, or Laravel Boost checks.
  - PASS: Use Artisan for route inspection/generation, Pint for PHP, TypeScript/ESLint/Prettier for frontend, Laravel Boost docs/database/browser tools when available, and browser logs if UI behavior fails during verification.

## Project Structure

### Documentation (this feature)

```text
specs/001-manage-users/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── UserController.php
│   └── Requests/
│       ├── StoreUserRequest.php
│       └── UpdateUserRequest.php
├── Models/
│   └── User.php

database/
└── factories/
    └── UserFactory.php

resources/js/
├── actions/
│   └── App/Http/Controllers/UserController.ts
├── components/
│   └── ui/
├── pages/
│   └── users/
│       └── index.tsx
└── routes/
    └── users/

routes/
└── web.php

tests/
└── Feature/
    └── UserManagementTest.php
```

**Structure Decision**: Implement within the existing Laravel/Inertia application layout. The page belongs under `resources/js/pages/users/index.tsx`, reusable controls come from `resources/js/components/ui`, server actions live in `app/Http/Controllers` and `app/Http/Requests`, and behavior tests live in `tests/Feature`.

## Phase 0: Research

Completed in [research.md](./research.md). Key decisions:

- Use the existing `users` table and `User` model; no migration is needed for the first version.
- Use query-string search and paginated Inertia props so URLs remain shareable and pagination preserves filters.
- Use same-screen dialogs with Inertia forms for add, edit, and delete actions.
- Use request validation for create/update and explicit controller protection for self-deletion.

## Phase 1: Design

Generated artifacts:

- [data-model.md](./data-model.md)
- [contracts/user-management.md](./contracts/user-management.md)
- [quickstart.md](./quickstart.md)

## Post-Design Constitution Check

- **Laravel-first architecture**: PASS. Design uses named routes, controller actions, form requests, Eloquent, existing factories, and Pest feature tests.
- **Inertia React contracts**: PASS. Design defines a `resources/js/pages/users/index.tsx` page, Inertia props, same-screen dialogs, and Wayfinder imports.
- **Programmatic testing**: PASS. Quickstart includes focused Pest, Pint, TypeScript, lint, and format commands.
- **Structure and dependencies**: PASS. No new base directories or dependencies are planned; existing UI components and project folders are reused.
- **Tooling and observability**: PASS. Plan calls for Artisan route inspection, generated Wayfinder assets, Laravel Boost tools when available, browser logs for frontend failures, and standard format/static checks.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No constitution violations identified.
