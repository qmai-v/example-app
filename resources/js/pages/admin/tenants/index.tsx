import { Link, router } from '@inertiajs/react';
import { Building2, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    destroy as adminTenantsDestroy,
    index as adminTenantsIndex,
    restore as adminTenantsRestore,
    update as adminTenantsUpdate,
} from '@/actions/App/Http/Controllers/Admin/TenantController';
import ConfirmationDialog from '@/components/confirmation-dialog';
import GenericTable from '@/components/generic-table';
import type { GenericTableColumn } from '@/components/generic-table';
import ResourceIndexLayout from '@/components/resource-index-layout';
import { Button } from '@/components/ui/button';
import { index as adminMembersIndex } from '@/routes/admin/tenants/members';
import type { GenericPagination } from '@/types';
import CreateTenantDialog from './create-tenant-dialog';

type TenantStatusFilter = 'active' | 'suspended' | 'deleted' | null;

type TenantRow = {
    id: string;
    name: string;
    slug: string;
    status: 'active' | 'suspended';
    deleted_at: string | null;
    member_count: number;
    created_at: string;
    updated_at: string;
};

type Props = {
    tenants: GenericPagination<TenantRow>;
    filters: {
        search: string | null;
        status: TenantStatusFilter;
        per_page: number;
        per_page_options: number[];
    };
};

export default function AdminTenantsIndex({ tenants, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [createOpen, setCreateOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<TenantRow | null>(null);

    const visitIndex = (
        next: {
            search?: string;
            status?: TenantStatusFilter;
            perPage?: number;
        } = {},
    ) => {
        const nextSearch = next.search ?? search;
        const nextStatus =
            next.status === undefined ? filters.status : next.status;

        router.get(
            adminTenantsIndex.url({
                query: {
                    search: nextSearch.trim() || undefined,
                    status: nextStatus ?? undefined,
                    per_page: next.perPage ?? tenants.per_page,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        visitIndex();
    };

    const toggleStatus = (row: TenantRow) => {
        const nextStatus = row.status === 'active' ? 'suspended' : 'active';

        router.put(
            adminTenantsUpdate.url({ tenant: row.id }),
            { name: row.name, status: nextStatus },
            { preserveScroll: true },
        );
    };

    const restore = (row: TenantRow) => {
        router.post(
            adminTenantsRestore.url({ tenant: row.id }),
            {},
            { preserveScroll: true },
        );
    };

    const onDelete = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!deleteTarget) {
            return;
        }

        router.delete(adminTenantsDestroy.url({ tenant: deleteTarget.id }), {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    const changeStatusFilter = (next: TenantStatusFilter) => {
        visitIndex({ status: next });
    };

    const clearSearch = () => {
        setSearch('');
        visitIndex({ search: '' });
    };

    const columns: GenericTableColumn<TenantRow>[] = [
        {
            key: 'name',
            header: 'Name',
            cell: (row) => row.name,
        },
        {
            key: 'slug',
            header: 'Slug',
            className: 'text-muted-foreground',
            cell: (row) => row.slug,
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) =>
                row.deleted_at !== null ? (
                    <span className="text-rose-600">Deleted</span>
                ) : (
                    <span className="capitalize">{row.status}</span>
                ),
        },
        {
            key: 'members',
            header: 'Members',
            cell: (row) => row.member_count,
        },
        {
            key: 'actions',
            header: 'Actions',
            headerClassName: 'text-right',
            cell: (row) => {
                const isDeleted = row.deleted_at !== null;

                return (
                    <div className="flex justify-end gap-1">
                        <Button size="sm" variant="ghost">
                            <Link
                                href={adminMembersIndex.url({
                                    tenant: row.id,
                                })}
                            >
                                Members
                            </Link>
                        </Button>
                        {!isDeleted && (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => toggleStatus(row)}
                            >
                                {row.status === 'active'
                                    ? 'Suspend'
                                    : 'Reactivate'}
                            </Button>
                        )}
                        {isDeleted ? (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => restore(row)}
                                aria-label={`Restore ${row.name}`}
                            >
                                <RotateCcw className="size-4" />
                            </Button>
                        ) : (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setDeleteTarget(row)}
                                aria-label={`Delete ${row.name}`}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        )}
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <ResourceIndexLayout
                title="Tenant administration"
                description="Create, edit, suspend, delete, and restore tenants across the platform."
            >
                <GenericTable
                    columns={columns}
                    getRowKey={(row) => row.id}
                    emptyTitle="No tenants found."
                    toolbar={{
                        search: {
                            value: search,
                            hasValue: search.trim().length > 0,
                            placeholder: 'Search by name or slug',
                            ariaLabel: 'Search tenants by name or slug',
                            onChange: setSearch,
                            onSubmit: submitSearch,
                            onClear: clearSearch,
                        },
                        filters: (
                            <>
                                {(
                                    [
                                        ['All', null],
                                        ['Active', 'active'],
                                        ['Suspended', 'suspended'],
                                        ['Deleted', 'deleted'],
                                    ] as const
                                ).map(([label, value]) => (
                                    <Button
                                        key={label}
                                        variant={
                                            filters.status === value
                                                ? 'default'
                                                : 'ghost'
                                        }
                                        size="sm"
                                        onClick={() =>
                                            changeStatusFilter(value)
                                        }
                                    >
                                        {label}
                                    </Button>
                                ))}
                            </>
                        ),
                        actions: (
                            <Button onClick={() => setCreateOpen(true)}>
                                <Building2 className="size-4" /> Create tenant
                            </Button>
                        ),
                    }}
                    pagination={tenants}
                    pageSize={{
                        value: tenants.per_page,
                        options: filters.per_page_options,
                        onChange: (value) => visitIndex({ perPage: value }),
                    }}
                />
            </ResourceIndexLayout>

            <CreateTenantDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
            />

            <ConfirmationDialog
                open={deleteTarget !== null}
                title="Delete tenant"
                description={
                    deleteTarget
                        ? `Soft-delete ${deleteTarget.name}? You can restore it later.`
                        : ''
                }
                confirmLabel="Delete"
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                onConfirm={onDelete}
            />
        </>
    );
}

AdminTenantsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Tenant administration',
            href: adminTenantsIndex(),
        },
    ],
};
