import {
    destroy as adminMembersDestroy,
    index as adminMembersIndex,
    store as adminMembersStore,
    update as adminMembersUpdate,
} from '@/actions/App/Http/Controllers/Admin/TenantMemberController';
import MemberManagementPage from '@/components/tenant-members/member-management-page';
import type {
    TenantMemberFilters,
    TenantMemberPagination,
    TenantMemberRow,
} from '@/components/tenant-members/types';
import type { TenantStatus } from '@/types/auth';

type Props = {
    targetTenant: {
        id: string;
        name: string;
        slug: string;
        status: TenantStatus;
        deleted_at: string | null;
    };
    members: TenantMemberPagination;
    filters: TenantMemberFilters;
    lastTenantAdminMembershipId: number | null;
};

export default function AdminTenantMembers({
    targetTenant,
    members,
    filters,
    lastTenantAdminMembershipId,
}: Props) {
    return (
        <MemberManagementPage
            title={`Members of ${targetTenant.name}`}
            description="Manage every member and their in-tenant role for this tenant."
            addDescription={`Add a user as a member of ${targetTenant.name}.`}
            removeDescription={(member: TenantMemberRow) =>
                `Remove ${member.user.name} from ${targetTenant.name}?`
            }
            members={members}
            filters={filters}
            lastTenantAdminMembershipId={lastTenantAdminMembershipId}
            routes={{
                indexUrl: (query) =>
                    adminMembersIndex.url(
                        { tenant: targetTenant.id },
                        { query },
                    ),
                storeUrl: () =>
                    adminMembersStore.url({ tenant: targetTenant.id }),
                updateUrl: (member) =>
                    adminMembersUpdate.url({
                        tenant: targetTenant.id,
                        membership: member.id,
                    }),
                destroyUrl: (member) =>
                    adminMembersDestroy.url({
                        tenant: targetTenant.id,
                        membership: member.id,
                    }),
            }}
        />
    );
}
