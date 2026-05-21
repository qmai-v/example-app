import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import ResourceIndexLayout from '@/components/resource-index-layout';
import { index } from '@/routes/users';
import type { UserRow } from '@/types';
import DeleteUserDialog from './components/delete-user-dialog';
import UserFormDialog from './components/user-form-dialog';
import UsersTable from './components/users-table';
import type { UserStatusFilter, UsersIndexProps } from './types';

export default function UsersIndex({ auth, users, filters }: UsersIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState<UserStatusFilter>(
        filters.status ?? 'all',
    );
    const [createOpen, setCreateOpen] = useState(false);
    const [selectedUser, setSelectedUser] = useState<UserRow | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.get(
            index.url({
                query: {
                    search: search.trim() || undefined,
                    status: status === 'all' ? undefined : status,
                    per_page: users.per_page,
                },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const clearSearch = () => {
        setSearch('');
        router.get(
            index.url({
                query: {
                    status: status === 'all' ? undefined : status,
                    per_page: users.per_page,
                },
            }),
            {},
            { preserveState: true, replace: true },
        );
    };

    const changePerPage = (perPage: number) => {
        router.get(
            index.url({
                query: {
                    search: search.trim() || undefined,
                    status: status === 'all' ? undefined : status,
                    per_page: perPage,
                },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const changeStatus = (nextStatus: UserStatusFilter) => {
        setStatus(nextStatus);

        router.get(
            index.url({
                query: {
                    search: search.trim() || undefined,
                    status: nextStatus === 'all' ? undefined : nextStatus,
                    per_page: users.per_page,
                },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('all');

        router.get(
            index.url({
                query: {
                    per_page: users.per_page,
                },
            }),
            {},
            { preserveState: true, replace: true },
        );
    };

    const openCreateDialog = () => {
        setSelectedUser(null);
        setDeleteOpen(false);
        setCreateOpen(true);
    };

    const openEditDialog = (user: UserRow) => {
        setCreateOpen(false);
        setDeleteOpen(false);
        setSelectedUser(user);
    };

    const openDeleteDialog = (user: UserRow) => {
        setCreateOpen(false);
        setSelectedUser(user);
        setDeleteOpen(true);
    };

    const hasSearch = Boolean(filters.search);
    const hasFilters = hasSearch || status !== 'all';
    const activeSearch = filters.search ?? '';
    const activeStatus = status;
    const userFormOpen = createOpen || (selectedUser !== null && !deleteOpen);
    const deletingUser = deleteOpen ? selectedUser : null;

    const closeUserFormDialog = (open: boolean) => {
        if (open) {
            return;
        }

        setCreateOpen(false);
        setSelectedUser(null);
        setDeleteOpen(false);
    };

    const closeDeleteDialog = (open: boolean) => {
        if (open) {
            return;
        }

        setSelectedUser(null);
        setDeleteOpen(false);
    };

    return (
        <>
            <ResourceIndexLayout
                title="User management"
                description="Find accounts quickly and manage user details from one place"
            >
                <UsersTable
                    users={users}
                    currentUserId={auth.user.id}
                    hasSearch={hasSearch}
                    hasFilters={hasFilters}
                    perPageOptions={filters.per_page_options}
                    search={search}
                    status={status}
                    onEdit={openEditDialog}
                    onDelete={openDeleteDialog}
                    onCreate={openCreateDialog}
                    onPerPageChange={changePerPage}
                    onStatusChange={changeStatus}
                    onSearchChange={setSearch}
                    onSearchSubmit={submitSearch}
                    onClearSearch={clearSearch}
                    onClearFilters={clearFilters}
                />
            </ResourceIndexLayout>

            <UserFormDialog
                open={userFormOpen}
                user={createOpen ? null : selectedUser}
                currentPage={users.current_page}
                perPage={users.per_page}
                search={activeSearch}
                status={activeStatus}
                onOpenChange={closeUserFormDialog}
            />

            {deletingUser && (
                <DeleteUserDialog
                    open={deletingUser !== null}
                    user={deletingUser}
                    currentPage={users.current_page}
                    perPage={users.per_page}
                    search={activeSearch}
                    status={activeStatus}
                    onOpenChange={closeDeleteDialog}
                />
            )}
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'User management',
            href: index(),
        },
    ],
};
