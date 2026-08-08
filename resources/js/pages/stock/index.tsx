import { Head, useForm } from '@inertiajs/react';
import { Boxes } from 'lucide-react';
import { useState } from 'react';

import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { MoneyDisplay } from '@/components/money-display';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FieldGroup } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useCurrency } from '@/hooks/use-currency';
import AppLayout from '@/layouts/app-layout';
import { store } from '@/routes/stock';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';
import type { StockRow } from '@/types/stock';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Stock' }];

type StockForm = {
    product_id: number | null;
    counted_quantity: string;
    reason: string;
    unit_cost: string;
};

export default function StockIndex({
    rows,
    table,
    totalValue,
}: {
    rows: Paginated<StockRow>;
    table: TableState;
    totalValue: number;
}) {
    const currency = useCurrency();
    const [counting, setCounting] = useState<StockRow | null>(null);

    const form = useForm<StockForm>({
        product_id: null,
        counted_quantity: '',
        reason: '',
        unit_cost: '',
    });

    function openCount(row: StockRow) {
        form.setData({
            product_id: row.id,
            counted_quantity: String(row.on_hand),
            reason: '',
            unit_cost: row.default_cost_price ?? '',
        });
        form.clearErrors();
        setCounting(row);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => setCounting(null),
        });
    }

    const counted = Number(form.data.counted_quantity);
    const delta =
        counting && Number.isFinite(counted) ? counted - counting.on_hand : 0;

    const columns: Column<StockRow>[] = [
        {
            key: 'name',
            header: 'Product',
            sortable: true,
            cell: (row) => (
                <div className="flex flex-col">
                    <span className="font-medium">{row.name}</span>
                    <span className="font-mono text-xs text-muted-foreground">
                        {row.code}
                    </span>
                </div>
            ),
        },
        {
            key: 'on_hand',
            header: 'On hand',
            align: 'right',
            cell: (row) =>
                row.on_hand === 0 ? (
                    <span className="text-muted-foreground tabular-nums">
                        0
                    </span>
                ) : (
                    <span className="font-medium tabular-nums">
                        {row.on_hand}
                    </span>
                ),
        },
        {
            key: 'value',
            header: 'Value',
            align: 'right',
            hideOnMobile: true,
            cell: (row) => <MoneyDisplay amount={row.value} />,
        },
        {
            key: 'is_active',
            header: '',
            align: 'right',
            hideOnMobile: true,
            cell: (row) =>
                row.is_active ? null : (
                    <Badge variant="outline">Archived</Badge>
                ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            cell: (row) => (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => openCount(row)}
                >
                    Count
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Stock" />

            <PageHeader
                title="Stock"
                description="On hand and what it cost, derived from every movement."
                actions={
                    <div className="text-right">
                        <div className="text-xs text-muted-foreground">
                            Total value
                        </div>
                        <MoneyDisplay
                            amount={totalValue}
                            className="text-lg font-semibold"
                        />
                    </div>
                }
            />

            <DataTable
                rows={rows}
                columns={columns}
                state={table}
                getRowKey={(row) => row.id}
                searchPlaceholder="Search name or code"
                empty={
                    <EmptyState
                        icon={Boxes}
                        title={table.search ? 'No matches' : 'Nothing stocked'}
                        description={
                            table.search
                                ? `Nothing matches "${table.search}".`
                                : 'Add a product, then record a count.'
                        }
                    />
                }
            />

            <Dialog
                open={counting !== null}
                onOpenChange={(open) => !open && setCounting(null)}
            >
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>Count {counting?.name}</DialogTitle>
                            <DialogDescription>
                                The books say {counting?.on_hand}. Enter what
                                you actually counted.
                            </DialogDescription>
                        </DialogHeader>

                        <FieldGroup className="py-4">
                            <FormField
                                label="Counted quantity"
                                error={form.errors.counted_quantity}
                                description={
                                    delta === 0
                                        ? undefined
                                        : `${delta > 0 ? 'Adding' : 'Removing'} ${Math.abs(delta)}.`
                                }
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        inputMode="numeric"
                                        className="text-right tabular-nums"
                                        value={form.data.counted_quantity}
                                        autoFocus
                                        onChange={(event) =>
                                            form.setData(
                                                'counted_quantity',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>

                            {delta > 0 && (
                                <FormField
                                    label={`Unit cost (${currency.code})`}
                                    error={form.errors.unit_cost}
                                    description="What the extra stock is worth."
                                >
                                    {(control) => (
                                        <Input
                                            {...control}
                                            inputMode="decimal"
                                            placeholder="0.00"
                                            className="text-right tabular-nums"
                                            value={form.data.unit_cost}
                                            onChange={(event) =>
                                                form.setData(
                                                    'unit_cost',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                            )}

                            <FormField
                                label="Reason"
                                error={form.errors.reason}
                                description="Why the books were wrong."
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        placeholder="Stock count, damage, miscount"
                                        value={form.data.reason}
                                        onChange={(event) =>
                                            form.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        </FieldGroup>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCounting(null)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Record count
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

StockIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
