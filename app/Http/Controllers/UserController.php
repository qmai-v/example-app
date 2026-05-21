<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /**
     * Show the user management page.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->users->normalizePerPage(
            $request->integer('per_page', UserService::DEFAULT_PER_PAGE),
        );
        $statusQuery = $request->query('status');
        $status = $this->users->normalizeStatus(is_string($statusQuery) ? $statusQuery : null);

        return Inertia::render('users/index', [
            'users' => $this->users->paginatedManagementList($search, $status, $perPage),
            'filters' => [
                'search' => $search === '' ? null : $search,
                'status' => $status,
                'per_page' => $perPage,
                'per_page_options' => UserService::PER_PAGE_OPTIONS,
            ],
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->users->createUser($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return $this->redirectToUsers($request);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->updateUser($user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return $this->redirectToUsers($request);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if (! $this->users->deleteUser($user, $request->user())) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot delete your own account from this page.')]);

            return $this->redirectToUsers($request);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return $this->redirectToUsers($request, recoverEmptyPage: true);
    }

    private function redirectToUsers(Request $request, bool $recoverEmptyPage = false): RedirectResponse
    {
        $search = trim((string) $request->input('search', ''));
        $page = max((int) $request->input('page', 1), 1);
        $perPage = $this->users->normalizePerPage(
            $request->integer('per_page', UserService::DEFAULT_PER_PAGE),
        );
        $statusInput = $request->input('status');
        $status = $this->users->normalizeStatus(is_string($statusInput) ? $statusInput : null);

        if ($recoverEmptyPage) {
            $page = min($page, $this->users->lastManagementPage($search, $status, $perPage));
        }

        return to_route('users.index', array_filter([
            'search' => $search === '' ? null : $search,
            'status' => $status,
            'per_page' => $request->has('per_page') ? $perPage : null,
            'page' => $page > 1 ? $page : null,
        ]));
    }
}
