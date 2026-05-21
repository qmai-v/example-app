import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Skeleton } from '@/components/ui/skeleton';
import tenants from '@/routes/tenants';

export function TenantSwitcher() {
    const { tenant } = usePage().props;
    const active = tenant.active;
    const available = tenant.available;

    if (!active && available.length === 0) {
        return null;
    }

    const handleSwitch = (tenantId: string) => {
        if (tenantId === active?.id) {
            return;
        }

        router.flushAll();
        router.post(
            tenants.switch.url(),
            { tenant_id: tenantId },
            {
                fresh: true,
                onSuccess: () => router.flushAll(),
                preserveScroll: false,
                preserveState: false,
                replace: true,
            },
        );
    };

    const showSwitcher = !active || available.length > 1;
    const triggerLabel = active?.name ?? 'Select tenant';
    const roleLabel = (role: (typeof available)[number]['role']) => {
        if (role === 'tenant_admin') {
            return 'Tenant admin';
        }

        if (role === 'super_admin') {
            return 'Super admin';
        }

        return 'Member';
    };

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Switch tenant</SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem>
                    {showSwitcher ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <SidebarMenuButton
                                    size="lg"
                                    className="text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                                    data-test="tenant-switcher-trigger"
                                >
                                    <Building2 className="size-4" />
                                    <span className="flex-1 truncate text-left text-sm font-medium">
                                        {triggerLabel}
                                    </span>
                                    <ChevronsUpDown className="ml-auto size-4 opacity-60" />
                                </SidebarMenuButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                                align="end"
                                side="bottom"
                            >
                                <DropdownMenuLabel className="text-xs text-muted-foreground">
                                    Available tenants
                                </DropdownMenuLabel>
                                {available === undefined ? (
                                    <div className="space-y-2 px-2 py-1.5">
                                        <Skeleton className="h-5 w-full" />
                                        <Skeleton className="h-5 w-4/5" />
                                    </div>
                                ) : (
                                    available.map((option) => (
                                        <DropdownMenuItem
                                            key={option.id}
                                            onSelect={() =>
                                                handleSwitch(option.id)
                                            }
                                            data-test={`tenant-switcher-option-${option.slug}`}
                                        >
                                            <Building2 className="size-4 opacity-60" />
                                            <div className="flex flex-col">
                                                <span className="text-sm font-medium">
                                                    {option.name}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {roleLabel(option.role)}
                                                </span>
                                            </div>
                                            {option.id === active?.id && (
                                                <Check className="ml-auto size-4" />
                                            )}
                                        </DropdownMenuItem>
                                    ))
                                )}
                                {tenant.actingAsSuperAdmin && (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuLabel className="text-xs">
                                            Acting as super admin
                                        </DropdownMenuLabel>
                                    </>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : (
                        <SidebarMenuButton
                            size="lg"
                            className="cursor-default text-sidebar-accent-foreground"
                            data-test="tenant-switcher-static"
                        >
                            <Building2 className="size-4" />
                            <span className="flex-1 truncate text-left text-sm font-medium">
                                {triggerLabel}
                            </span>
                        </SidebarMenuButton>
                    )}
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    );
}
