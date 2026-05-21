# Quickstart: User Management

## Prerequisites

- Use the feature branch `001-manage-users`.
- Review [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), and [contracts/user-management.md](./contracts/user-management.md).
- Before implementation, use Laravel Boost `search-docs` for Laravel routing, validation, authorization, pagination, Inertia forms, and Inertia React patterns if the tool is available in the session.

## Implementation Outline

1. Create server-side files with Artisan generators:
   - `php artisan make:controller UserController --no-interaction`
   - `php artisan make:request StoreUserRequest --no-interaction`
   - `php artisan make:request UpdateUserRequest --no-interaction`
   - `php artisan make:test --pest UserManagementTest --no-interaction`
2. Add authenticated administrator routes in `routes/web.php` using named routes from the contract.
3. Implement the controller with paginated search, create, update, and delete actions.
4. Implement request validation for create and update.
5. Generate or refresh Wayfinder route/action files after route changes.
6. Build `resources/js/pages/users/index.tsx` with existing UI components, search, pagination controls, and add/edit/delete dialogs.
7. Add or update navigation only if the existing app pattern supports exposing admin pages from the sidebar.
8. Add Pest coverage for all functional requirements and edge cases.

## Verification

Run the narrowest checks that prove the feature:

```bash
php artisan route:list --path=users --except-vendor
php artisan test --compact --filter=UserManagementTest
vendor/bin/pint --dirty --format agent
npm run types:check
npm run lint:check
npm run format:check
```

If frontend behavior is not reflected locally, run the existing development stack or build command:

```bash
composer run dev
```

or:

```bash
npm run build
```
