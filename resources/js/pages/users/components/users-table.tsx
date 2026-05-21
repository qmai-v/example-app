import { Pencil, Trash2, UserPlus, X } from 'lucide-react';
import type { FormEvent } from 'react';
import AppSelect from '@/components/app-select';
import GenericTable from '@/components/generic-table';
import type { GenericTableColumn } from '@/components/generic-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, getInitials } from '@/lib/formatters';
import type { PaginatedUsers, UserRow } from '@/types';
import type { UserStatusFilter } from '../types';

type UsersTableProps = {
    users: PaginatedUsers;
    currentUserId: number;
    hasSearch: boolean;
    hasFilters: boolean;
    perPageOptions: number[];
    search: string;
    status: UserStatusFilter;
    onEdit: (user: UserRow) => void;
    onDelete: (user: UserRow) => void;
    onCreate: () => void;
    onPerPageChange: (value: number) => void;
    onStatusChange: (value: UserStatusFilter) => void;
    onSearchChange: (value: string) => void;
    onSearchSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onClearSearch: () => void;
    onClearFilters: () => void;
};

export default function UsersTable({
    users,
    currentUserId,
    hasSearch,
    hasFilters,
    perPageOptions,
    search,
    status,
    onEdit,
    onDelete,
    onCreate,
    onPerPageChange,
    onStatusChange,
    onSearchChange,
    onSearchSubmit,
    onClearSearch,
    onClearFilters,
}: UsersTableProps) {
    const columns: GenericTableColumn<UserRow>[] = [
        {
            key: 'user',
            header: 'User',
            cell: (user) => (
                <div className="flex min-w-64 items-center gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full border bg-muted text-sm font-semibold text-muted-foreground">
                        {getInitials(user.name)}
                    </div>
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="truncate font-medium">{user.name}</p>
                            {currentUserId === user.id && (
                                <Badge variant="secondary">You</Badge>
                            )}
                        </div>
                        <p className="truncate text-muted-foreground">
                            {user.email}
                        </p>
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (user) =>
                user.email_verified_at ? (
                    <Badge variant="outline">Verified</Badge>
                ) : (
                    <Badge variant="secondary">Unverified</Badge>
                ),
        },
        {
            key: 'created',
            header: 'Created',
            className: 'text-muted-foreground',
            cell: (user) => formatDate(user.created_at),
        },
        {
            key: 'actions',
            header: 'Actions',
            headerClassName: 'text-right',
            cell: (user) => (
                <div className="flex justify-end gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => onEdit(user)}
                        aria-label={`Edit ${user.name}`}
                    >
                        <Pencil className="size-4" />
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={currentUserId === user.id}
                        onClick={() => onDelete(user)}
                        aria-label={`Delete ${user.name}`}
                        className="text-muted-foreground hover:text-destructive"
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <GenericTable
            columns={columns}
            getRowKey={(user) => user.id}
            toolbar={{
                search: {
                    value: search,
                    hasValue: hasSearch,
                    placeholder: 'Filter user',
                    ariaLabel: 'Filter by user name or email',
                    onChange: onSearchChange,
                    onSubmit: onSearchSubmit,
                    onClear: onClearSearch,
                },
                filters: (
                    <AppSelect
                        value={status}
                        options={[
                            { value: 'all', label: 'All statuses' },
                            { value: 'verified', label: 'Verified' },
                            { value: 'unverified', label: 'Unverified' },
                        ]}
                        label="Status"
                        ariaLabel="Filter by status"
                        triggerClassName="w-36"
                        onValueChange={(value) =>
                            onStatusChange(value as UserStatusFilter)
                        }
                    />
                ),
                actions: (
                    <Button onClick={onCreate} className="w-full sm:w-fit">
                        <UserPlus className="size-4" />
                        Add user
                    </Button>
                ),
            }}
            emptyAction={
                hasFilters && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClearFilters}
                    >
                        <X className="size-4" />
                        Clear filters
                    </Button>
                )
            }
            pagination={users}
            pageSize={{
                value: users.per_page,
                options: perPageOptions,
                onChange: onPerPageChange,
            }}
        />
    );
}
