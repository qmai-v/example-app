<!--
Sync Impact Report
Version change: 1.0.0 -> 1.1.0
Modified principles:
- I. Laravel-First Architecture (clarified Artisan generator and named-route obligations)
- III. Inertia React Contracts (renumbered from II; clarified Inertia v3 patterns and deferred-prop UX)
- IV. Programmatic Testing Is Required (renumbered from III; ties to Pest 4 + composer ci:check)
- V. Existing Structure and Dependencies Are Stable (renumbered from IV; cites concrete sibling directories)
- VI. Tooling, Formatting, and Observability (renumbered from V; adds Laravel Boost MCP tool guidance)
Added sections:
- II. Layered Service + Repository Pattern (new principle reflecting BaseService/BaseRepository abstractions)
Removed sections:
- None
Templates requiring updates:
- ✅ .specify/templates/plan-template.md (Constitution Check expanded to six principles)
- ✅ .specify/templates/spec-template.md (no obligations changed; verified)
- ✅ .specify/templates/tasks-template.md (verification commands aligned with composer ci:check + pint flags)
- ⚠ .specify/templates/commands/*.md (directory not present; no action)
Follow-up TODOs:
- None
-->
# Example App Constitution

## Core Principles

### I. Laravel-First Architecture

All backend work MUST follow Laravel 13 conventions before introducing custom
abstractions. Controllers, models, migrations, factories, seeders, middleware,
form requests, policies, resources, jobs, and commands MUST be created with the
matching `php artisan make:*` command using `--no-interaction`. Server routes
MUST be declared by name and resolved with the `route()` helper or Wayfinder
imports; URLs MUST NOT be hand-built. Authorization MUST use Laravel policies
and gate primitives, validation MUST live in Form Requests, and authentication
MUST flow through Fortify primitives. Rationale: Laravel conventions keep the
application predictable, testable, and compatible with framework tooling and
Laravel Boost MCP introspection.

### II. Layered Service + Repository Pattern

User-visible features MUST flow through the existing
Controller → Service → Repository → Eloquent layering. Controllers MUST stay
thin: parse the request, delegate to a service method, and return a redirect
or Inertia response. Domain orchestration, normalization (e.g., pagination
size, status filters), hashing, and policy decisions MUST live in
`app/Services/` classes that extend `BaseService` and reuse its constants
(`DEFAULT_PER_PAGE`, `PER_PAGE_OPTIONS`). Persistence MUST go through
`app/Repositories/` classes that extend `BaseRepository<TModel>` and implement
a typed contract under `app/Repositories/Contracts/`. New Eloquent query
methods MUST be added to the relevant repository (typed
`Builder<TModel>`/`LengthAwarePaginator<int, TModel>` shapes preserved), not
inline in controllers or services. Rationale: the layered split is the project
contract that makes services unit-testable, controllers reviewable, and
queries reusable across HTTP, jobs, and tinker.

### III. Inertia React Contracts

User-facing pages MUST be delivered through Inertia React from
`resources/js/pages` and rendered server-side with `Inertia::render()`. Blade
views MUST NOT be added for SPA flows. Frontend navigation, form submission,
and link generation MUST prefer Wayfinder imports from `@/actions/` or
`@/routes/` when generated bindings exist. Inertia deferred or optional props
MUST ship with a visible loading or empty state (e.g., pulsing skeleton).
Removed Inertia v3 APIs (`Inertia::lazy`, `router.cancel`, `axios`) MUST NOT
be reintroduced; use `Inertia::optional()`, `router.cancelAll()`, and the
built-in XHR client instead. Rationale: the application is a Laravel/Inertia
SPA, so route contracts must remain typed, discoverable, and consistent
across PHP and TypeScript.

### IV. Programmatic Testing Is Required

Every behavior change MUST include a new or updated automated test, and the
narrowest affected test command MUST be run before the change is considered
complete. Pest 4 feature tests under `tests/Feature/` are the default for
user-visible Laravel behavior; `tests/Unit/` is reserved for isolated logic.
Tests MUST use model factories and existing test helpers rather than ad hoc
verification scripts or `tinker` when test coverage can prove the behavior.
Tests MUST NOT be deleted without explicit approval. The minimum verification
command for PHP changes is
`php artisan test --compact --filter=<TestNameOrPath>`; full CI parity is
`composer ci:check`. Rationale: changes without executable proof are not
review-ready, and `--compact` keeps feedback loops fast without sacrificing
coverage.

### V. Existing Structure and Dependencies Are Stable

Work MUST stay within the existing application layout
(`app/{Actions,Concerns,Console,Http,Models,Providers,Repositories,Services}`,
`resources/js/{actions,components,hooks,layouts,lib,pages,routes,types,wayfinder}`,
`routes/{web.php,settings.php,console.php}`, `tests/{Feature,Unit}`) unless an
approved implementation plan documents a specific need. New base directories,
new runtime dependencies in `composer.json` or `package.json`, and new
package-level architecture are prohibited without explicit approval. Before
creating a controller, service, repository, request, page, or helper, the
implementer MUST inspect sibling files (e.g., `UserController`, `UserService`,
`UserRepository`, `resources/js/pages/users/`) and reuse the established
pattern. Documentation files MUST NOT be added unless explicitly requested.
Rationale: this project is an application, not a framework playground;
stability and local consistency outrank novelty.

### VI. Tooling, Formatting, and Observability

PHP changes MUST be formatted with `vendor/bin/pint --dirty --format agent`
before completion; `pint --test` MUST NOT be used as the fix step.
TypeScript and React changes MUST satisfy `npm run lint:check`,
`npm run format:check`, and `npm run types:check` for touched files. Debugging
MUST prefer application-aware tooling in this order: Laravel Boost MCP tools
(`search-docs`, `database-query`, `database-schema`, `get-absolute-url`,
`browser-logs`) when available; Artisan commands (`php artisan route:list`,
`php artisan config:show`) for route and configuration discovery; read-only
database inspection for schema and data checks; and recent browser logs for
frontend failures. Shared URLs MUST be resolved through `get-absolute-url`
when the tool is available. Rationale: first-party and project-aware tooling
catches integration problems earlier than manual inspection.

## Application Constraints

The project targets Laravel 13, PHP 8.4, Fortify 1, Inertia Laravel 3, Inertia
React 3, React 19, Tailwind CSS 4, Wayfinder 0.x, Laravel Boost 2, Pest 4,
PHPUnit 12, Pint 1, ESLint 9, and Prettier 3. Implementation plans MUST treat
those versions as binding unless a dependency change is explicitly approved.

The default development database is PostgreSQL 16 provisioned via
`docker-compose.yml` (service `postgres`, database `example_app`). Local
testing MAY continue to use SQLite where existing tests already rely on it,
but production-shaped behavior MUST be verified against PostgreSQL semantics
(case-sensitive `LIKE`, JSON columns, sequence-backed IDs) before merge.

Database changes MUST use Artisan-generated migrations and Eloquent models
with matching factories and seeders. API surfaces, when added, MUST use
Eloquent API Resources and versioned API routes unless existing routes
establish a different convention. Security-sensitive behavior MUST use
Laravel authorization (policies, gates), Form Request validation, middleware,
and Fortify primitives rather than bespoke alternatives. Passwords MUST be
hashed via `Hash::make()` inside the service layer, never in controllers or
factories outside test fixtures.

## Development Workflow

Each feature specification MUST define independently testable user stories,
measurable success criteria, relevant edge cases, and assumptions. Each
implementation plan MUST include a Constitution Check that explains how the
work satisfies all six core principles before design work proceeds and again
after design artifacts are complete.

Task lists MUST group work by independently deliverable user story and include
explicit test, implementation, formatting, and verification tasks. For PHP
work, the final verification MUST include the narrowest applicable
`php artisan test --compact --filter=<Target>` command and
`vendor/bin/pint --dirty --format agent`. For frontend work, the final
verification MUST include `npm run lint:check`, `npm run format:check`, and
`npm run types:check` for the affected paths. Full CI parity is
`composer ci:check` and MUST be run before requesting review on any change
that touches both layers.

Any complexity, dependency, structure, layering, or testing exception MUST be
documented in the plan before implementation begins, including the simpler
alternative that was rejected and the reason it was insufficient.

## Governance

This constitution supersedes conflicting project habits, generated templates,
and implementation plans. Amendments MUST update this file, include a Sync
Impact Report, and propagate any changed rule to affected Spec Kit templates
or runtime guidance files (`.specify/templates/*.md`, `AGENTS.md`,
`CLAUDE.md`) in the same change.

Versioning follows semantic versioning. MAJOR versions remove or redefine
core principles in a backward-incompatible way. MINOR versions add principles,
sections, or materially expanded governance requirements. PATCH versions
clarify wording without changing obligations.

Compliance review is required during specification, planning, task generation,
implementation, and code review. A feature cannot be considered complete
while a known constitution violation lacks a documented and approved
exception captured in the plan's Complexity Tracking table.

**Version**: 1.1.0 | **Ratified**: 2026-05-19 | **Last Amended**: 2026-05-21
