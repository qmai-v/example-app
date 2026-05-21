import { usePage } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';

export function SuperAdminBanner() {
    const { tenant } = usePage().props;

    if (!tenant.actingAsSuperAdmin || !tenant.active) {
        return null;
    }

    return (
        <div className="border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
            <div className="flex items-center gap-3 border-y border-amber-300 px-4 py-2 text-sm dark:border-amber-700">
                <ShieldAlert className="size-4" />
                <span>
                    Acting as super admin in{' '}
                    <strong>{tenant.active.name}</strong>. Writes are attributed
                    to your super-admin account.
                </span>
            </div>
        </div>
    );
}
