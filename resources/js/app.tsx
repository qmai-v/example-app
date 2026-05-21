import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

if (typeof window !== 'undefined') {
    let shouldRefreshRestoredHistory = false;

    window.addEventListener('popstate', () => {
        shouldRefreshRestoredHistory = true;
    });

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            router.visit(window.location.href, {
                fresh: true,
                preserveScroll: true,
                preserveState: false,
                replace: true,
            });
        }
    });

    router.on('navigate', () => {
        if (!shouldRefreshRestoredHistory) {
            return;
        }

        shouldRefreshRestoredHistory = false;

        router.visit(window.location.href, {
            fresh: true,
            preserveScroll: true,
            preserveState: false,
            replace: true,
        });
    });
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
