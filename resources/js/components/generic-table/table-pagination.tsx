import { Link } from '@inertiajs/react';
import AppSelect from '@/components/app-select';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
} from '@/components/ui/pagination';
import type { GenericPagination } from '@/types';
import type { GenericTablePageSize } from './types';

type TablePaginationProps<TItem> = {
    pagination?: GenericPagination<TItem>;
    pageSize?: GenericTablePageSize;
};

type PaginationLinkItem = {
    key: string;
    href: string | null;
    label: string;
    active: boolean;
    disabled: boolean;
    ellipsis?: boolean;
};

const cleanPaginationLabel = (label: string) =>
    label.replace('&laquo;', '').replace('&raquo;', '').trim();

const isNumberLabel = (label: string) => /^\d+$/.test(label);

const buildPaginationLinks = <TItem,>(
    pagination: GenericPagination<TItem>,
): PaginationLinkItem[] => {
    if (pagination.last_page <= 1) {
        return [];
    }

    const links = pagination.links.map((link, index): PaginationLinkItem => {
        const label = cleanPaginationLabel(link.label);

        return {
            key: `${link.label}-${index}`,
            href: link.url,
            label,
            active: link.active,
            disabled: link.url === null,
            ellipsis: label === '...',
        };
    });

    const edgeLinks = links.filter(
        (link) => !link.ellipsis && !isNumberLabel(link.label),
    );
    const pageLinks = links.filter((link) => isNumberLabel(link.label));

    if (pageLinks.length <= 5) {
        return [...edgeLinks.slice(0, 1), ...pageLinks, ...edgeLinks.slice(1)];
    }

    return [
        ...edgeLinks.slice(0, 1),
        ...pageLinks.slice(0, 3),
        {
            key: 'page-gap',
            href: null,
            label: '...',
            active: false,
            disabled: true,
            ellipsis: true,
        },
        ...pageLinks.slice(-2),
        ...edgeLinks.slice(1),
    ];
};

export default function TablePagination<TItem>({
    pagination,
    pageSize,
}: TablePaginationProps<TItem>) {
    if (!pagination) {
        return null;
    }

    const summary =
        pagination.total > 0
            ? `Showing ${pagination.from} to ${pagination.to} of ${pagination.total}`
            : 'No records';

    const links = buildPaginationLinks(pagination);

    return (
        <div className="flex shrink-0 flex-col gap-3 border-t bg-muted/20 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
                {pageSize && (
                    <AppSelect
                        value={String(pageSize.value)}
                        options={pageSize.options.map((option) => ({
                            value: String(option),
                            label: option,
                        }))}
                        label={pageSize.label ?? 'Rows per page'}
                        ariaLabel={pageSize.ariaLabel ?? 'Rows per page'}
                        triggerClassName="w-20"
                        onValueChange={(value) =>
                            pageSize.onChange(Number(value))
                        }
                    />
                )}

                <p className="text-sm text-muted-foreground">{summary}</p>
            </div>

            {links.length > 0 && (
                <Pagination className="mx-0 w-auto justify-start sm:justify-end">
                    <PaginationContent className="flex-wrap justify-start sm:justify-end">
                        {links.map((link, index) => (
                            <PaginationItem key={link.key ?? `page-${index}`}>
                                {link.ellipsis ? (
                                    <PaginationEllipsis />
                                ) : link.href && !link.disabled ? (
                                    <PaginationLink
                                        asChild
                                        isActive={link.active}
                                        size="sm"
                                        className="min-w-9"
                                    >
                                        <Link href={link.href} preserveScroll>
                                            {link.label}
                                        </Link>
                                    </PaginationLink>
                                ) : (
                                    <PaginationLink
                                        aria-disabled
                                        href="#"
                                        size="sm"
                                        className="pointer-events-none min-w-9 opacity-50"
                                    >
                                        {link.label}
                                    </PaginationLink>
                                )}
                            </PaginationItem>
                        ))}
                    </PaginationContent>
                </Pagination>
            )}
        </div>
    );
}
