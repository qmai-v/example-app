import { usePage } from '@inertiajs/react';
import {
    destroy as membersDestroy,
    index as membersIndex,
    store as membersStore,
    update as membersUpdate,
} from '@/actions/App/Http/Controllers/Tenant/MemberController';
import MemberManagementPage from '@/components/tenant-members/member-management-page';
import type {
    TenantMemberFilters,
    TenantMemberPagination,
    TenantMemberRow,
} from '@/components/tenant-members/types';

type Props = {
    members: TenantMemberPagination;
    filters: TenantMemberFilters;
    lastTenantAdminMembershipId: number | null;
};

export default function MembersIndex({
    members,
    filters,
    lastTenantAdminMembershipId,
}: Props) {
    const { tenant } = usePage().props;
    const tenantName = tenant.active?.name ?? 'this tenant';

    return (
        <MemberManagementPage
            title={`Members of ${tenantName}`}
            description="Add, remove, and adjust roles for everyone with access to this tenant."
            addDescription="Invite an existing user to this tenant by their email address."
            removeDescription={(member: TenantMemberRow) =>
                `Remove ${member.user.name} from this tenant?`
            }
            members={members}
            filters={filters}
            lastTenantAdminMembershipId={lastTenantAdminMembershipId}
            routes={{
                indexUrl: (query) => membersIndex.url({ query }),
                storeUrl: () => membersStore.url(),
                updateUrl: (member) =>
                    membersUpdate.url({ membership: member.id }),
                destroyUrl: (member) =>
                    membersDestroy.url({ membership: member.id }),
            }}
        />
    );
}
