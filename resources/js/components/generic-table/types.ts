import type { FormEvent, ReactNode } from 'react';
import type { GenericPagination } from '@/types';

export type GenericTableColumn<TItem> = {
    key: string;
    header: ReactNode;
    cell: (item: TItem) => ReactNode;
    className?: string;
    headerClassName?: string;
};

export type GenericTableSearchToolbar = {
    value: string;
    onChange: (value: string) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    ariaLabel?: string;
    clearLabel?: ReactNode;
    hasValue?: boolean;
    onClear?: () => void;
    placeholder?: string;
    submitLabel?: ReactNode;
};

export type GenericTableToolbar =
    | false
    | {
          enabled?: boolean;
          search?: GenericTableSearchToolbar;
          filters?: ReactNode;
          actions?: ReactNode;
          className?: string;
      };

export type GenericTablePageSize = {
    value: number;
    options: number[];
    onChange: (value: number) => void;
    label?: ReactNode;
    ariaLabel?: string;
};

export type GenericTableProps<TItem> = {
    columns: GenericTableColumn<TItem>[];
    data?: TItem[];
    getRowKey: (item: TItem) => string | number;
    emptyAction?: ReactNode;
    emptyDescription?: ReactNode;
    emptyState?: ReactNode;
    emptyTitle?: ReactNode;
    maxBodyHeight?: string;
    className?: string;
    rowClassName?: string | ((item: TItem) => string | undefined);
    toolbar?: GenericTableToolbar;
    pagination?: GenericPagination<TItem>;
    pageSize?: GenericTablePageSize;
};
