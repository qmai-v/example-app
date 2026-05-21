import { Head, Link } from '@inertiajs/react';
import { logout } from '@/routes';

interface Props {
    reason?: 'no_membership' | 'all_unavailable' | 'pending_invite';
}

export default function NoTenant({ reason = 'no_membership' }: Props) {
    const messages: Record<NonNullable<Props['reason']>, string> = {
        no_membership:
            'Your account is not a member of any active tenant. Ask an administrator to add you to a tenant before continuing.',
        all_unavailable:
            'Every tenant you belong to is currently unavailable (suspended or deleted). Ask an administrator to reactivate one or assign you to another.',
        pending_invite:
            'Your tenant access is pending. Once an administrator finishes onboarding, you will be able to continue.',
    };

    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
            <Head title="No tenant available" />

            <div className="w-full max-w-md rounded-lg border border-[#19140035] bg-white p-8 shadow-sm dark:border-[#3E3E3A] dark:bg-[#161615]">
                <h1 className="mb-4 text-xl font-semibold">
                    No tenant available
                </h1>
                <p className="mb-6 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    {messages[reason]}
                </p>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Link
                        href={logout()}
                        as="button"
                        method="post"
                        className="inline-flex items-center justify-center rounded-sm border border-[#19140035] px-4 py-2 text-sm leading-normal font-medium hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
                    >
                        Sign out
                    </Link>
                    <button
                        type="button"
                        onClick={() => window.location.reload()}
                        className="inline-flex items-center justify-center rounded-sm border border-transparent px-4 py-2 text-sm leading-normal font-medium text-[#f53003] underline underline-offset-4 dark:text-[#FF4433]"
                    >
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    );
}
