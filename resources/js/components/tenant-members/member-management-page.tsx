import { router, useForm } from '@inertiajs/react';
import { Trash2, UserPlus, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppDialog from '@/components/app-dialog';
import AppSelect from '@/components/app-select';
import ConfirmationDialog from '@/components/confirmation-dialog';
import GenericTable from '@/components/generic-table';
import type { GenericTableColumn } from '@/components/generic-table';
import InputError from '@/components/input-error';
import ResourceIndexLayout from '@/components/resource-index-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { TenantRole } from '@/types/auth';
import ChangeMemberRoleDialog from './change-member-role-dialog';
import type {
    TenantMemberFilters,
    TenantMemberPagination,
    TenantMemberRoutes,
    TenantMemberRow,
} from './types';

type RoleFilter = TenantRole | 'all';

type MemberManagementPageProps = {
    title: string;
    description: string;
    addDescription: string;
    removeDescription: (member: TenantMemberRow) => string;
    members: TenantMemberPagination;
    filters: TenantMemberFilters;
    routes: TenantMemberRoutes;
    lastTenantAdminMembershipId: number | null;
};

const roleOptions = [
    { value: 'member', label: 'Member' },
    { value: 'tenant_admin', label: 'Tenant admin' },
];

export default function MemberManagementPage({
    title,
    description,
    addDescription,
    removeDescription,
    members,
    filters,
    routes,
    lastTenantAdminMembershipId,
}: MemberManagementPageProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [role, setRole] = useState<RoleFilter>(filters.role ?? 'all');
    const [createOpen, setCreateOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<TenantMemberRow | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<TenantMemberRow | null>(
        null,
    );

    const createForm = useForm<{ email: string; role: TenantRole }>({
        email: '',
        role: 'member',
    });

    const visitIndex = (
        next: {
            search?: string;
            role?: RoleFilter;
            perPage?: number;
        } = {},
    ) => {
        const nextSearch = next.search ?? search;
        const nextRole = next.role ?? role;

        router.get(
            routes.indexUrl({
                search: nextSearch.trim() || undefined,
                role: nextRole === 'all' ? undefined : nextRole,
                per_page: next.perPage ?? members.per_page,
            }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        visitIndex();
    };

    const clearSearch = () => {
        setSearch('');
        visitIndex({ search: '' });
    };

    const changeRoleFilter = (nextRole: RoleFilter) => {
        setRole(nextRole);
        visitIndex({ role: nextRole });
    };

    const clearFilters = () => {
        setSearch('');
        setRole('all');
        visitIndex({ search: '', role: 'all' });
    };

    const onCreate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        createForm.post(routes.storeUrl(), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setCreateOpen(false);
            },
        });
    };

    const onDelete = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!deleteTarget) {
            return;
        }

        router.delete(routes.destroyUrl(deleteTarget), {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    const columns: GenericTableColumn<TenantMemberRow>[] = [
        {
            key: 'name',
            header: 'Name',
            cell: (member) => member.user.name,
        },
        {
            key: 'email',
            header: 'Email',
            cell: (member) => member.user.email,
        },
        {
            key: 'role',
            header: 'Role',
            cell: (member) =>
                member.role === 'tenant_admin' ? 'Tenant admin' : 'Member',
        },
        {
            key: 'actions',
            header: 'Actions',
            headerClassName: 'text-right',
            cell: (member) => {
                const isLastAdmin = member.id === lastTenantAdminMembershipId;

                return (
                    <div className="flex justify-end gap-1">
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setEditTarget(member)}
                            disabled={isLastAdmin}
                        >
                            Role
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setDeleteTarget(member)}
                            disabled={isLastAdmin}
                            aria-label={`Remove ${member.user.name}`}
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                );
            },
        },
    ];

    const hasSearch = Boolean(filters.search);
    const hasFilters = hasSearch || filters.role !== null;

    return (
        <>
            <ResourceIndexLayout title={title} description={description}>
                <GenericTable
                    columns={columns}
                    getRowKey={(member) => member.id}
                    emptyTitle="No members found."
                    toolbar={{
                        search: {
                            value: search,
                            hasValue: search.trim().length > 0,
                            placeholder: 'Search by name or email',
                            ariaLabel: 'Search members by name or email',
                            onChange: setSearch,
                            onSubmit: submitSearch,
                            onClear: clearSearch,
                        },
                        filters: (
                            <AppSelect
                                value={role}
                                options={[
                                    { value: 'all', label: 'All roles' },
                                    ...roleOptions,
                                ]}
                                label="Role"
                                ariaLabel="Filter members by role"
                                triggerClassName="w-36"
                                onValueChange={(value) =>
                                    changeRoleFilter(value as RoleFilter)
                                }
                            />
                        ),
                        actions: (
                            <Button onClick={() => setCreateOpen(true)}>
                                <UserPlus className="size-4" /> Add member
                            </Button>
                        ),
                    }}
                    emptyAction={
                        hasFilters && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={clearFilters}
                            >
                                <X className="size-4" />
                                Clear filters
                            </Button>
                        )
                    }
                    pagination={members}
                    pageSize={{
                        value: members.per_page,
                        options: filters.per_page_options,
                        onChange: (value) => visitIndex({ perPage: value }),
                    }}
                />
            </ResourceIndexLayout>

            <AppDialog
                open={createOpen}
                title="Add member"
                description={addDescription}
                submitLabel="Add member"
                onOpenChange={(open) => {
                    if (!open) {
                        createForm.reset();
                        setCreateOpen(false);
                    }
                }}
                onSubmit={onCreate}
                processing={createForm.processing}
            >
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="member-email">Email</Label>
                        <Input
                            id="member-email"
                            type="email"
                            value={createForm.data.email}
                            onChange={(event) =>
                                createForm.setData('email', event.target.value)
                            }
                        />
                        <InputError message={createForm.errors.email} />
                    </div>
                    <div className="space-y-1.5">
                        <AppSelect
                            value={createForm.data.role}
                            options={roleOptions}
                            label="Role"
                            ariaLabel="Select member role"
                            triggerClassName="w-40"
                            onValueChange={(value) =>
                                createForm.setData('role', value as TenantRole)
                            }
                        />
                        <InputError message={createForm.errors.role} />
                    </div>
                </div>
            </AppDialog>

            {editTarget && (
                <ChangeMemberRoleDialog
                    member={editTarget}
                    updateUrl={routes.updateUrl(editTarget)}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditTarget(null);
                        }
                    }}
                />
            )}

            <ConfirmationDialog
                open={deleteTarget !== null}
                title="Remove member"
                description={
                    deleteTarget ? removeDescription(deleteTarget) : ''
                }
                confirmLabel="Remove"
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
