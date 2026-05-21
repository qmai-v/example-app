import { Search } from 'lucide-react';
import type { ReactNode } from 'react';

type TableEmptyStateProps = {
    action?: ReactNode;
    description?: ReactNode;
    title?: ReactNode;
};

export default function TableEmptyState({
    action,
    description = 'Try adjusting your filters or check back later.',
    title = 'No records found',
}: TableEmptyStateProps) {
    return (
        <div className="border-t px-6 py-14 text-center">
            <div className="mx-auto flex size-12 items-center justify-center rounded-full border bg-muted/40">
                <Search className="size-5 text-muted-foreground" />
            </div>
            <p className="mt-4 font-medium">{title}</p>
            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                {description}
            </p>
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
