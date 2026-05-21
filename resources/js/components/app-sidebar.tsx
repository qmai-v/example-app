import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    FolderGit2,
    LayoutGrid,
    ShieldCheck,
    Users,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TenantSwitcher } from '@/components/tenant-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as adminTenantsIndex } from '@/routes/admin/tenants';
import { index as tenantMembersIndex } from '@/routes/tenant/members';
import { edit as tenantSettingsEdit } from '@/routes/tenant/settings';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth, tenant } = usePage().props;

    const platformItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    const tenantItems: NavItem[] = [];

    if (tenant.role === 'tenant_admin' || tenant.actingAsSuperAdmin) {
        tenantItems.push(
            {
                title: 'Members',
                href: tenantMembersIndex(),
                icon: UsersRound,
            },
            {
                title: 'Settings',
                href: tenantSettingsEdit(),
                icon: Building2,
            },
        );
    }

    if (auth.isSuperAdmin) {
        platformItems.push(
            {
                title: 'All users',
                href: usersIndex(),
                icon: Users,
            },
            {
                title: 'Tenant administration',
                href: adminTenantsIndex(),
                icon: ShieldCheck,
            },
        );
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <TenantSwitcher />
                <NavMain items={platformItems} />
                <NavMain items={tenantItems} label="Tenant" />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
