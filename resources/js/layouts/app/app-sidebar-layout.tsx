import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { SuperAdminBanner } from '@/components/super-admin-banner';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="h-svh max-h-svh min-h-0 overflow-hidden"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <SuperAdminBanner />
                {children}
            </AppContent>
        </AppShell>
    );
}
