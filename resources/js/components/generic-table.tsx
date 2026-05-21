import TableContent from './generic-table/table-content';
import TableEmptyState from './generic-table/table-empty-state';
import TablePagination from './generic-table/table-pagination';
import TableToolbar from './generic-table/table-toolbar';
import type { GenericTableProps } from './generic-table/types';

export type {
    GenericTableColumn,
    GenericTablePageSize,
    GenericTableProps,
    GenericTableSearchToolbar,
    GenericTableToolbar,
} from './generic-table/types';

export default function GenericTable<TItem>({
    columns,
    data,
    getRowKey,
    emptyAction,
    emptyDescription,
    emptyState,
    emptyTitle,
    maxBodyHeight,
    className,
    rowClassName,
    toolbar,
    pagination,
    pageSize,
}: GenericTableProps<TItem>) {
    const tableData = data ?? pagination?.data ?? [];

    return (
        <div className="flex min-h-0 flex-1 basis-0 flex-col overflow-hidden">
            <TableToolbar toolbar={toolbar} />

            <TableContent
                columns={columns}
                data={tableData}
                getRowKey={getRowKey}
                maxBodyHeight={maxBodyHeight}
                className={className}
                rowClassName={rowClassName}
            />

            {tableData.length === 0 &&
                (emptyState ?? (
                    <TableEmptyState
                        action={emptyAction}
                        description={emptyDescription}
                        title={emptyTitle}
                    />
                ))}

            <TablePagination pagination={pagination} pageSize={pageSize} />
        </div>
    );
}
