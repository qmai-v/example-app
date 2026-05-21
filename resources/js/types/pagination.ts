import type { Auth } from './auth';

export type GenericPaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type GenericPagination<TItem> = {
    data: TItem[];
    current_page: number;
    from: number | null;
    last_page: number;
    links: GenericPaginationLink[];
    per_page: number;
    to: number | null;
    total: number;
};

export type GenericIndexProps<
    TItem,
    TFilters extends Record<string, unknown> = Record<string, unknown>,
> = {
    auth: Auth;
    filters: TFilters;
    records: GenericPagination<TItem>;
};
