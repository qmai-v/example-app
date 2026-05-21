import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { cn } from '@/lib/utils';

type ResourceIndexLayoutProps = {
    children: ReactNode;
    title: string;
    actions?: ReactNode;
    className?: string;
    contentClassName?: string;
    description?: string;
    headTitle?: string;
};

export default function ResourceIndexLayout({
    children,
    title,
    actions,
    className,
    contentClassName,
    description,
    headTitle,
}: ResourceIndexLayoutProps) {
    return (
        <>
            <Head title={headTitle ?? title} />

            <div
                className={cn(
                    'flex min-h-0 flex-1 basis-0 flex-col gap-1 overflow-hidden p-4',
                    className,
                )}
            >
                <div className="flex shrink-0 flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                    <Heading title={title} description={description} />

                    {actions && (
                        <div className="flex flex-wrap gap-2 sm:justify-end">
                            {actions}
                        </div>
                    )}
                </div>

                <div
                    className={cn(
                        'flex min-h-0 flex-1 basis-0 flex-col overflow-hidden rounded-xl border bg-background shadow-xs',
                        contentClassName,
                    )}
                >
                    {children}
                </div>
            </div>
        </>
    );
}
