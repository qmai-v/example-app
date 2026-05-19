<!--
Sync Impact Report
Version change: template -> 1.0.0
Modified principles:
- PRINCIPLE_1_NAME placeholder -> I. Laravel-First Architecture
- PRINCIPLE_2_NAME placeholder -> II. Inertia React Contracts
- PRINCIPLE_3_NAME placeholder -> III. Programmatic Testing Is Required
- PRINCIPLE_4_NAME placeholder -> IV. Existing Structure and Dependencies Are Stable
- PRINCIPLE_5_NAME placeholder -> V. Tooling, Formatting, and Observability
Added sections:
- Application Constraints
- Development Workflow
Removed sections:
- None
Templates requiring updates:
- ✅ .specify/templates/plan-template.md
- ✅ .specify/templates/spec-template.md
- ✅ .specify/templates/tasks-template.md
- ⚠ .specify/templates/commands/*.md (directory not present)
Follow-up TODOs:
- None
-->
# Example App Constitution

## Core Principles

### I. Laravel-First Architecture
All backend work MUST follow Laravel conventions before introducing custom
abstractions. Controllers, models, migrations, factories, seeders, middleware,
requests, policies, resources, and commands MUST be created with Artisan when
Laravel provides a generator. Features MUST use named routes, the `route()`
helper, Eloquent, validation, authorization, and framework configuration in the
style already present in the application. Rationale: Laravel conventions keep
the application predictable, testable, and compatible with framework tooling.

### II. Inertia React Contracts
User-facing pages MUST be delivered through Inertia React from
`resources/js/pages` unless an existing route pattern requires otherwise.
Server routes MUST render pages with Inertia responses rather than adding Blade
views for SPA flows. Frontend navigation and form actions MUST prefer Wayfinder
imports from `@/actions/` or `@/routes/` when route helpers are generated.
Inertia deferred data MUST include a visible empty or loading state. Rationale:
the application is a Laravel/Inertia SPA, so route contracts must remain typed,
discoverable, and consistent across PHP and TypeScript.

### III. Programmatic Testing Is Required
Every behavior change MUST include a new or updated automated test, and the
affected tests MUST be run before the change is considered complete. Pest
feature tests are the default for user-visible Laravel behavior; unit tests are
reserved for isolated logic. Frontend-only changes MUST run the narrowest
applicable static checks or build checks. Tests MUST use factories and existing
test helpers instead of ad hoc verification scripts when test coverage can prove
the behavior. Rationale: changes without executable proof are not review-ready.

### IV. Existing Structure and Dependencies Are Stable
Work MUST stay within the existing application layout unless a plan documents a
specific need and receives approval. New base directories, new runtime
dependencies, and new package-level architecture are prohibited without explicit
approval. Before creating a component, class, route, or helper, the implementer
MUST inspect sibling files and reuse existing patterns where practical.
Rationale: this project is an application, not a framework playground; stability
and local consistency outrank novelty.

### V. Tooling, Formatting, and Observability
PHP changes MUST be formatted with Pint before completion. TypeScript and React
changes MUST satisfy the existing ESLint, Prettier, and TypeScript checks
appropriate to the touched files. Debugging MUST prefer application-aware tools:
Laravel Boost tools when available, Artisan commands for route/config discovery,
read-only database inspection for schema and data checks, and recent browser
logs for frontend failures. Rationale: first-party tooling catches integration
problems earlier than manual inspection.

## Application Constraints

The project targets Laravel 13, PHP 8.4, Fortify, Inertia Laravel 3, Inertia
React 3, React 19, Tailwind CSS 4, Wayfinder, Pest 4, Pint, ESLint 9, and
Prettier 3. Implementation plans MUST treat those versions as binding unless a
dependency change is explicitly approved.

Database changes MUST use migrations and Eloquent models. API surfaces, when
added, MUST use Eloquent API Resources and versioned API routes unless existing
routes establish a different convention. Security-sensitive behavior MUST use
Laravel authorization, validation, middleware, and Fortify conventions rather
than bespoke alternatives.

Documentation files MUST NOT be added unless specifically requested. Runtime
URLs shared with users MUST be resolved through project-aware tooling when that
tooling is available.

## Development Workflow

Each feature specification MUST define independently testable user stories,
measurable success criteria, relevant edge cases, and assumptions. Each
implementation plan MUST include a Constitution Check that explains how the work
satisfies all five core principles before design work proceeds and again after
design artifacts are complete.

Task lists MUST group work by independently deliverable user story and include
explicit test, implementation, formatting, and verification tasks. For PHP work,
the final verification MUST include the narrowest affected `php artisan test
--compact` command and `vendor/bin/pint --dirty --format agent`. For frontend
work, the final verification MUST include the narrowest applicable npm checks.

Any complexity, dependency, structure, or testing exception MUST be documented in
the plan before implementation begins, including the simpler alternative that was
rejected and the reason it was insufficient.

## Governance

This constitution supersedes conflicting project habits, generated templates,
and implementation plans. Amendments MUST update this file, include a Sync
Impact Report, and propagate any changed rule to affected Spec Kit templates or
runtime guidance files in the same change.

Versioning follows semantic versioning. MAJOR versions remove or redefine core
principles in a backward-incompatible way. MINOR versions add principles,
sections, or materially expanded governance requirements. PATCH versions clarify
wording without changing obligations.

Compliance review is required during specification, planning, task generation,
implementation, and code review. A feature cannot be considered complete while a
known constitution violation lacks a documented and approved exception.

**Version**: 1.0.0 | **Ratified**: 2026-05-19 | **Last Amended**: 2026-05-19
