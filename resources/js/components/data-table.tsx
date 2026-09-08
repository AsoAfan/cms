import { router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ChevronsUpDown, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { Paginated, TableState } from '@/types';

export type Column<T> = {
    /** Must match a key whitelisted by `TableQuery::sortable()` to be sortable. */
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    sortable?: boolean;
    align?: 'left' | 'right';
    /** Hide below the `sm` breakpoint to keep narrow screens readable. */
    hideOnMobile?: boolean;
    className?: string;
};

export type DataTableProps<T> = {
    rows: Paginated<T>;
    columns: Column<T>[];
    state: TableState;
    getRowKey: (row: T) => string | number;
    /** Omit to hide the search box entirely. */
    searchPlaceholder?: string;
    /** Extra filter controls, shown beside the search box. */
    toolbar?: ReactNode;
    empty?: ReactNode;
    onRowClick?: (row: T) => void;
};

/**
 * Merge parameters into the current URL, dropping any that are cleared.
 *
 * Changing anything other than the page sends the user back to page one —
 * staying on page 7 of a result set that now has two pages is never useful.
 */
function urlWith(
    current: string,
    updates: Record<string, string | number | null>,
): string {
    const url = new URL(current, window.location.origin);

    for (const [key, value] of Object.entries(updates)) {
        if (value === null || value === '') {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, String(value));
        }
    }

    if (!('page' in updates)) {
        url.searchParams.delete('page');
    }

    return `${url.pathname}${url.search}`;
}

/** Page numbers around the current page, with gaps collapsed to `null`. */
function pageWindow(current: number, last: number): (number | null)[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const pages = new Set([1, last, current, current - 1, current + 1]);
    const sorted = [...pages]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);

    return sorted.flatMap((page, index) =>
        index > 0 && page - sorted[index - 1] > 1 ? [null, page] : [page],
    );
}

export function DataTable<T>({
    rows,
    columns,
    state,
    getRowKey,
    searchPlaceholder,
    toolbar,
    empty,
    onRowClick,
}: DataTableProps<T>) {
    const currentUrl = usePage().url;
    const serverSearch = state.search ?? '';
    const [search, setSearch] = useState(serverSearch);
    const [lastServerSearch, setLastServerSearch] = useState(serverSearch);

    // Adjusted during render rather than in an effect: when the server sends a
    // different search back — after the browser's back button, say — the box
    // follows it without a second render pass.
    if (lastServerSearch !== serverSearch) {
        setLastServerSearch(serverSearch);
        setSearch(serverSearch);
    }

    // Debounced so typing a code does not fire a request per keystroke.
    useEffect(() => {
        if (search === serverSearch) {
            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                urlWith(currentUrl, { search: search || null }),
                {},
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, serverSearch, currentUrl]);

    function visit(updates: Record<string, string | number | null>) {
        router.get(
            urlWith(currentUrl, updates),
            {},
            { preserveState: true, preserveScroll: true },
        );
    }

    function toggleSort(key: string) {
        const direction =
            state.sort === key && state.direction === 'asc' ? 'desc' : 'asc';

        visit({ sort: key, direction });
    }

    const hasRows = rows.data.length > 0;

    return (
        <div className="flex flex-col gap-4">
            {(searchPlaceholder || toolbar) && (
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    {searchPlaceholder && (
                        <InputGroup className="sm:max-w-xs">
                            <InputGroupInput
                                type="search"
                                value={search}
                                placeholder={searchPlaceholder}
                                aria-label={searchPlaceholder}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                            <InputGroupAddon>
                                <Search />
                            </InputGroupAddon>
                        </InputGroup>
                    )}
                    {toolbar && (
                        <div className="flex flex-wrap items-center gap-2">
                            {toolbar}
                        </div>
                    )}
                </div>
            )}

            <div className="overflow-x-auto rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column) => (
                                <TableHead
                                    key={column.key}
                                    className={cn(
                                        column.align === 'right' &&
                                            'text-right',
                                        column.hideOnMobile &&
                                            'hidden sm:table-cell',
                                        column.className,
                                    )}
                                >
                                    {column.sortable ? (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className={cn(
                                                '-mx-2 h-8',
                                                column.align === 'right' &&
                                                    'ml-auto',
                                            )}
                                            onClick={() =>
                                                toggleSort(column.key)
                                            }
                                            aria-label={`Sort by ${column.header}`}
                                        >
                                            {column.header}
                                            {state.sort !== column.key ? (
                                                <ChevronsUpDown className="text-muted-foreground" />
                                            ) : state.direction === 'asc' ? (
                                                <ArrowUp />
                                            ) : (
                                                <ArrowDown />
                                            )}
                                        </Button>
                                    ) : (
                                        column.header
                                    )}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {hasRows ? (
                            rows.data.map((row) => (
                                <TableRow
                                    key={getRowKey(row)}
                                    onClick={
                                        onRowClick
                                            ? () => onRowClick(row)
                                            : undefined
                                    }
                                    className={cn(
                                        onRowClick && 'cursor-pointer',
                                    )}
                                >
                                    {columns.map((column) => (
                                        <TableCell
                                            key={column.key}
                                            className={cn(
                                                column.align === 'right' &&
                                                    'text-right',
                                                column.hideOnMobile &&
                                                    'hidden sm:table-cell',
                                                column.className,
                                            )}
                                        >
                                            {column.cell(row)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow className="hover:bg-transparent">
                                <TableCell
                                    colSpan={columns.length}
                                    className="p-0"
                                >
                                    {empty ?? (
                                        <EmptyState
                                            title={
                                                state.search
                                                    ? 'No matches'
                                                    : 'Nothing here yet'
                                            }
                                            description={
                                                state.search
                                                    ? `Nothing matches "${state.search}".`
                                                    : undefined
                                            }
                                        />
                                    )}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {hasRows && (
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted-foreground">
                        Showing {rows.from ?? 0}–{rows.to ?? 0} of {rows.total}
                    </p>

                    {rows.last_page > 1 && (
                        <Pagination className="mx-0 w-auto justify-start sm:justify-end">
                            <PaginationContent>
                                <PaginationItem>
                                    <PaginationPrevious
                                        aria-disabled={rows.current_page === 1}
                                        className={cn(
                                            rows.current_page === 1 &&
                                                'pointer-events-none opacity-50',
                                        )}
                                        onClick={() =>
                                            visit({
                                                page: rows.current_page - 1,
                                            })
                                        }
                                    />
                                </PaginationItem>

                                {pageWindow(
                                    rows.current_page,
                                    rows.last_page,
                                ).map((page, index) =>
                                    page === null ? (
                                        <PaginationItem key={`gap-${index}`}>
                                            <span className="px-2 text-muted-foreground">
                                                …
                                            </span>
                                        </PaginationItem>
                                    ) : (
                                        <PaginationItem key={page}>
                                            <PaginationLink
                                                isActive={
                                                    page === rows.current_page
                                                }
                                                onClick={() => visit({ page })}
                                            >
                                                {page}
                                            </PaginationLink>
                                        </PaginationItem>
                                    ),
                                )}

                                <PaginationItem>
                                    <PaginationNext
                                        aria-disabled={
                                            rows.current_page === rows.last_page
                                        }
                                        className={cn(
                                            rows.current_page ===
                                                rows.last_page &&
                                                'pointer-events-none opacity-50',
                                        )}
                                        onClick={() =>
                                            visit({
                                                page: rows.current_page + 1,
                                            })
                                        }
                                    />
                                </PaginationItem>
                            </PaginationContent>
                        </Pagination>
                    )}
                </div>
            )}
        </div>
    );
}
