import { Search, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { GenericTableToolbar } from './types';

type TableToolbarProps = {
    toolbar?: GenericTableToolbar;
};

export default function TableToolbar({ toolbar }: TableToolbarProps) {
    const toolbarOptions =
        toolbar && toolbar.enabled !== false ? toolbar : undefined;
    const searchToolbar = toolbarOptions?.search;
    const filters = toolbarOptions?.filters;
    const actions = toolbarOptions?.actions;

    if (!searchToolbar && !filters && !actions) {
        return null;
    }

    return (
        <div
            className={cn(
                'shrink-0 border-b bg-muted/20 p-4',
                toolbarOptions.className,
            )}
        >
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    {searchToolbar && (
                        <form
                            onSubmit={searchToolbar.onSubmit}
                            className="flex w-full flex-col gap-2 sm:max-w-xl sm:flex-row sm:items-center"
                        >
                            <div className="relative flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={searchToolbar.value}
                                    onChange={(event) =>
                                        searchToolbar.onChange(
                                            event.target.value,
                                        )
                                    }
                                    className="h-10 px-10"
                                    placeholder={searchToolbar.placeholder}
                                    aria-label={searchToolbar.ariaLabel}
                                />

                                {searchToolbar.hasValue &&
                                    searchToolbar.onClear && (
                                        <button
                                            type="button"
                                            onClick={searchToolbar.onClear}
                                            className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                                            aria-label={
                                                typeof searchToolbar.clearLabel ===
                                                'string'
                                                    ? searchToolbar.clearLabel
                                                    : 'Clear search'
                                            }
                                        >
                                            <X className="size-4" />
                                        </button>
                                    )}
                            </div>

                            <Button type="submit">
                                {searchToolbar.submitLabel ?? 'Search'}
                            </Button>
                        </form>
                    )}

                    {filters && (
                        <div className="flex shrink-0 flex-wrap gap-2">
                            {filters}
                        </div>
                    )}
                </div>

                {actions && (
                    <div className="flex shrink-0 flex-wrap gap-2 lg:ml-auto lg:justify-end">
                        {actions}
                    </div>
                )}
            </div>
        </div>
    );
}
