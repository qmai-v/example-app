import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { GenericTableColumn } from './types';

type TableContentProps<TItem> = {
    columns: GenericTableColumn<TItem>[];
    data: TItem[];
    getRowKey: (item: TItem) => string | number;
    maxBodyHeight?: string;
    className?: string;
    rowClassName?: string | ((item: TItem) => string | undefined);
};

export default function TableContent<TItem>({
    columns,
    data,
    getRowKey,
    maxBodyHeight,
    className,
    rowClassName,
}: TableContentProps<TItem>) {
    const resolveRowClassName = (item: TItem) =>
        typeof rowClassName === 'function' ? rowClassName(item) : rowClassName;

    return (
        <Table
            className={className}
            containerClassName="min-h-0 flex-1 basis-0 overflow-auto"
            containerStyle={
                maxBodyHeight ? { maxHeight: maxBodyHeight } : undefined
            }
        >
            <TableHeader className="sticky top-0 z-10 bg-background text-xs tracking-wide text-muted-foreground uppercase shadow-[0_1px_0_0_var(--border)]">
                <TableRow className="hover:bg-transparent">
                    {columns.map((column) => (
                        <TableHead
                            key={column.key}
                            className={cn('px-5 py-3', column.headerClassName)}
                        >
                            {column.header}
                        </TableHead>
                    ))}
                </TableRow>
            </TableHeader>

            <TableBody>
                {data.map((item) => (
                    <TableRow
                        key={getRowKey(item)}
                        className={cn(
                            'hover:bg-muted/30',
                            resolveRowClassName(item),
                        )}
                    >
                        {columns.map((column) => (
                            <TableCell
                                key={column.key}
                                className={cn('px-5 py-4', column.className)}
                            >
                                {column.cell(item)}
                            </TableCell>
                        ))}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
