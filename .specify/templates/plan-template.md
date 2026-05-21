# Implementation Plan: [FEATURE]

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: [link]

**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command. See `.specify/templates/plan-template.md` for the execution workflow.

## Summary

[Extract from feature spec: primary requirement + technical approach from research]

## Technical Context

<!--
  ACTION REQUIRED: Replace the content in this section with the technical details
  for the project. The structure here is presented in advisory capacity to guide
  the iteration process.
-->

**Language/Version**: [e.g., Python 3.11, Swift 5.9, Rust 1.75 or NEEDS CLARIFICATION]

**Primary Dependencies**: [e.g., FastAPI, UIKit, LLVM or NEEDS CLARIFICATION]

**Storage**: [if applicable, e.g., PostgreSQL, CoreData, files or N/A]

**Testing**: [e.g., pytest, XCTest, cargo test or NEEDS CLARIFICATION]

**Target Platform**: [e.g., Linux server, iOS 15+, WASM or NEEDS CLARIFICATION]

**Project Type**: [e.g., library/cli/web-service/mobile-app/compiler/desktop-app or NEEDS CLARIFICATION]

**Performance Goals**: [domain-specific, e.g., 1000 req/s, 10k lines/sec, 60 fps or NEEDS CLARIFICATION]

**Constraints**: [domain-specific, e.g., <200ms p95, <100MB memory, offline-capable or NEEDS CLARIFICATION]

**Scale/Scope**: [domain-specific, e.g., 10k users, 1M LOC, 50 screens or NEEDS CLARIFICATION]

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **Laravel-first architecture**: Confirm the plan uses Laravel conventions,
  named routes, Eloquent, Form Request validation, Fortify primitives, policy
  authorization, and Artisan-generated files where applicable.
- **Layered service + repository pattern**: Confirm controllers stay thin and
  delegate to `app/Services/` classes (extending `BaseService`), and that
  persistence flows through `app/Repositories/` classes (extending
  `BaseRepository<TModel>` with a typed contract under
  `app/Repositories/Contracts/`). Cite the sibling service/repository the new
  work mirrors.
- **Inertia React contracts**: Confirm SPA pages live in `resources/js/pages`,
  server routes use `Inertia::render()` responses, Wayfinder route/action
  imports are used where available, and deferred/optional props include
  loading or empty states.
- **Programmatic testing**: List the Pest feature tests, unit tests, frontend
  lint/format/type checks, and the minimum
  `php artisan test --compact --filter=<Target>` command set that will prove
  the behavior.
- **Structure and dependencies**: Confirm no new base directories or
  `composer.json`/`package.json` dependencies are introduced without explicit
  approval, and cite existing sibling patterns to follow.
- **Tooling and observability**: Identify required `vendor/bin/pint --dirty
  --format agent`, ESLint, Prettier, TypeScript, Artisan, database, and
  Laravel Boost MCP (`search-docs`, `database-query`, `database-schema`,
  `get-absolute-url`, `browser-logs`) checks.

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)
<!--
  ACTION REQUIRED: Replace the placeholder tree below with the concrete layout
  for this feature. Delete unused options and expand the chosen structure with
  real paths (e.g., apps/admin, packages/something). The delivered plan must
  not include Option labels.
-->

```text
app/
├── Http/
├── Models/
└── [feature-specific Laravel namespaces]

database/
├── factories/
├── migrations/
└── seeders/

resources/js/
├── actions/
├── components/
├── pages/
└── routes/

routes/
├── web.php
└── [api.php if an API surface already exists or is approved]

tests/
├── Feature/
└── Unit/
```

**Structure Decision**: [Document the selected structure and reference the real
directories captured above]

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
