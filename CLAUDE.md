# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Companion documents (read these too)

- [AGENTS.md](AGENTS.md) — Laravel Boost guidelines (versions, PHP/Pint/Pest/Inertia/Wayfinder rules). Treat as authoritative for conventions.
- [.specify/memory/constitution.md](.specify/memory/constitution.md) — binding architectural principles (layered Controller → Service → Repository → Eloquent, Inertia React contracts, testing/tooling rules). Constitution overrides ad-hoc patterns.
- When a `specs/NNN-*/plan.md` exists for the current feature branch, read it for in-flight technology and structure decisions.

## Commands

### Backend (PHP / Laravel 13, PHP 8.4)
- Full dev stack (server + queue listener + Pail logs + Vite, concurrently): `composer dev`
- Run tests (compact output): `php artisan test --compact`
- Run a single test: `php artisan test --compact --filter=UserManagementTest` (or pass a path)
- Format PHP (required before finalizing PHP changes): `vendor/bin/pint --dirty --format agent` — do NOT use `--test` as the fix step.
- Full CI parity (lint + format + types + tests): `composer ci:check`
- Create files via Artisan with `--no-interaction` (e.g. `php artisan make:controller`, `make:test --pest`, `make:model -mfs`). Don't hand-roll boilerplate.

### Frontend (Inertia React 3, React 19, Tailwind 4, Vite)
- Dev server: `npm run dev` (or use `composer dev` to run everything together)
- Production build: `npm run build` (`build:ssr` for SSR build)
- Lint: `npm run lint:check` (fix: `npm run lint`)
- Format: `npm run format:check` (fix: `npm run format`)
- Types: `npm run types:check`

### Database
- Default local DB is **PostgreSQL 16** via `docker-compose.yml` (service `postgres`, db `example_app`). Start it with `docker compose up -d postgres`.
- Some existing tests still use SQLite (`database/database.sqlite`). When verifying production-shaped behavior, prefer Postgres (case-sensitive `LIKE`, JSON columns, sequences).
- Migrations: `php artisan migrate` / `migrate:fresh --seed`.

## Architecture

### Layered backend (enforced by the constitution)
Every user-visible feature flows: **Controller → Service → Repository → Eloquent**.

- **Controllers** ([app/Http/Controllers/](app/Http/Controllers/)) stay thin — parse the request (via Form Requests in [app/Http/Requests/](app/Http/Requests/)), delegate to a Service, return `Inertia::render(...)` or a `RedirectResponse`. See [UserController.php](app/Http/Controllers/UserController.php) as the canonical example.
- **Services** ([app/Services/](app/Services/)) own domain orchestration, normalization (pagination, status filters), hashing, and policy decisions. Extend [BaseService](app/Services/BaseService.php) and reuse `DEFAULT_PER_PAGE` / `PER_PAGE_OPTIONS`.
- **Repositories** ([app/Repositories/](app/Repositories/)) own all Eloquent queries. Extend [BaseRepository&lt;TModel&gt;](app/Repositories/BaseRepository.php) and implement a typed contract in [app/Repositories/Contracts/](app/Repositories/Contracts/). Contract → implementation bindings live in [AppServiceProvider::register()](app/Providers/AppServiceProvider.php) — add new bindings there.
- New query methods belong on the repository (typed `Builder<TModel>` / `LengthAwarePaginator<int, TModel>`), never inline in controllers or services.

### Auth
[Fortify](app/Providers/FortifyServiceProvider.php) provides registration, login, password reset, email verification, and two-factor. Fortify customization actions live in [app/Actions/Fortify/](app/Actions/Fortify/). Production password rules are enforced in `AppServiceProvider::configureDefaults()` (min 12, mixed case, letters, numbers, symbols, uncompromised); local has no extra rules.

### Routing
- Web routes in [routes/web.php](routes/web.php), settings routes in [routes/settings.php](routes/settings.php) (included from web.php), console in [routes/console.php](routes/console.php).
- Server-side: always declare routes by name and resolve with `route()` / `to_route()` — never hand-build URLs.
- Client-side: use **Wayfinder**-generated TS bindings — import controllers from `@/actions/` and named routes from `@/routes/`. The Vite plugin regenerates these on save.

### Frontend (Inertia + React)
- Pages live in [resources/js/pages/](resources/js/pages/) and are rendered with `Inertia::render('users/index', ...)`. No Blade views for SPA flows.
- Layouts in [resources/js/layouts/](resources/js/layouts/), shared components in [resources/js/components/](resources/js/components/) (UI primitives under `components/ui/`). Check for existing components before adding new ones — see `generic-table`, `resource-index-layout`, `confirmation-dialog`, etc.
- Inertia v3: use `Inertia::optional()` (not removed `Inertia::lazy()`), `router.cancelAll()` (not `router.cancel()`), built-in XHR client (no Axios). Deferred/optional props must ship with a visible loading state.
- React 19 with the React Compiler enabled (`babel-plugin-react-compiler` in [vite.config.ts](vite.config.ts)).

### Testing
- Pest 4. Feature tests in [tests/Feature/](tests/Feature/) are the default for user-visible behavior; [tests/Unit/](tests/Unit/) for isolated logic.
- Use model factories ([database/factories/](database/factories/)) and existing helpers — don't write ad-hoc verification scripts when a test can prove the behavior. Don't delete tests without approval.

### Spec Kit workflow
This repo uses Spec Kit ([.specify/](.specify/)). Features live in `specs/NNN-slug/` with `spec.md`, `plan.md`, `tasks.md`, `research.md`, `data-model.md`, `contracts/`, `checklists/`. Use the `/speckit-*` skills to drive the workflow (`specify`, `clarify`, `plan`, `tasks`, `implement`, `analyze`, `checklist`). Plans must include a Constitution Check against the six principles in [.specify/memory/constitution.md](.specify/memory/constitution.md).

<!-- SPECKIT START -->
For additional context about technologies to be used, project structure,
shell commands, and other important information, read the current plan
<!-- SPECKIT END -->
