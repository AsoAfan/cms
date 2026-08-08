import { Head, useForm, router } from '@inertiajs/react';
import { Pencil, Plus, Tags, Trash2, Wallet, X } from 'lucide-react';
import { useState } from 'react';

import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { DateRangePicker } from '@/components/date-range-picker';
import type { DateRange } from '@/components/date-range-picker';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useCurrency } from '@/hooks/use-currency';
import AppLayout from '@/layouts/app-layout';
import expenseCategories from '@/routes/expense-categories';
import { destroy, store, update } from '@/routes/expenses';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';
import type {
    ExpenseCategoryRow,
    ExpenseRow,
    PaymentMethodOption,
} from '@/types/expenses';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Expenses' }];

const ANY = 'any';

type ExpenseForm = {
    expense_category_id: string;
    amount: string;
    spent_on: string;
    payment_method: string;
    reference: string;
    notes: string;
};

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

export default function ExpensesIndex({
    rows,
    table,
    categories,
    paymentMethods,
    filteredTotal,
}: {
    rows: Paginated<ExpenseRow>;
    table: TableState;
    categories: ExpenseCategoryRow[];
    paymentMethods: PaymentMethodOption[];
    filteredTotal: number;
}) {
    const currency = useCurrency();
    const [editing, setEditing] = useState<ExpenseRow | null>(null);
    const [open, setOpen] = useState(false);
    const [managingCategories, setManagingCategories] = useState(false);

    const form = useForm<ExpenseForm>({
        expense_category_id: '',
        amount: '',
        spent_on: today(),
        payment_method: paymentMethods[0]?.value ?? 'cash',
        reference: '',
        notes: '',
    });

    const categoryForm = useForm<{ name: string }>({ name: '' });

    function visit(params: Record<string, string | null>) {
        const url = new URL(window.location.href);

        for (const [key, value] of Object.entries(params)) {
            if (value === null) {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, value);
            }
        }

        url.searchParams.delete('page');

        router.get(
            `${url.pathname}${url.search}`,
            {},
            { preserveState: true, preserveScroll: true },
        );
    }

    function openCreate() {
        form.setData({
            expense_category_id: String(categories[0]?.id ?? ''),
            amount: '',
            spent_on: today(),
            payment_method: paymentMethods[0]?.value ?? 'cash',
            reference: '',
            notes: '',
        });
        form.clearErrors();
        setEditing(null);
        setOpen(true);
    }

    function openEdit(row: ExpenseRow) {
        form.setData({
            expense_category_id: String(row.category_id),
            amount: (row.amount / 100).toFixed(2),
            spent_on: row.spent_on,
            payment_method: row.payment_method,
            reference: row.reference ?? '',
            notes: row.notes ?? '',
        });
        form.clearErrors();
        setEditing(row);
        setOpen(true);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.put(update(editing.id).url, options);
        } else {
            form.post(store().url, options);
        }
    }

    const range: DateRange | undefined = table.filters.from
        ? {
              from: new Date(table.filters.from),
              to: table.filters.to ? new Date(table.filters.to) : undefined,
          }
        : undefined;

    const columns: Column<ExpenseRow>[] = [
        {
            key: 'spent_on',
            header: 'Date',
            sortable: true,
            cell: (row) => row.spent_on,
        },
        {
            key: 'category',
            header: 'Category',
            cell: (row) => <Badge variant="secondary">{row.category}</Badge>,
        },
        {
            key: 'reference',
            header: 'Reference',
            hideOnMobile: true,
            cell: (row) =>
                row.reference ?? (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'payment_method_label',
            header: 'Paid by',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-muted-foreground">
                    {row.payment_method_label}
                </span>
            ),
        },
        {
            key: 'amount',
            header: 'Amount',
            sortable: true,
            align: 'right',
            cell: (row) => <MoneyDisplay amount={row.amount} />,
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={`Edit expense on ${row.spent_on}`}
                        onClick={() => openEdit(row)}
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={`Delete expense on ${row.spent_on}`}
                        onClick={() =>
                            router.delete(destroy(row.id).url, {
                                preserveScroll: true,
                            })
                        }
                    >
                        <Trash2 />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Expenses" />

            <PageHeader
                title="Expenses"
                description="What it costs to run the business."
                actions={
                    <>
                        <div className="mr-2 text-right">
                            <div className="text-xs text-muted-foreground">
                                Shown total
                            </div>
                            <MoneyDisplay
                                amount={filteredTotal}
                                className="text-lg font-semibold"
                            />
                        </div>
                        <Button
                            variant="outline"
                            onClick={() => setManagingCategories(true)}
                        >
                            <Tags data-icon="inline-start" />
                            Categories
                        </Button>
                        <Button onClick={openCreate}>
                            <Plus data-icon="inline-start" />
                            New expense
                        </Button>
                    </>
                }
            />

            <DataTable
                rows={rows}
                columns={columns}
                state={table}
                getRowKey={(row) => row.id}
                searchPlaceholder="Search reference or notes"
                toolbar={
                    <>
                        <DateRangePicker
                            value={range}
                            placeholder="Any dates"
                            onChange={(next) =>
                                visit({
                                    from: next?.from
                                        ? next.from.toISOString().slice(0, 10)
                                        : null,
                                    to: next?.to
                                        ? next.to.toISOString().slice(0, 10)
                                        : null,
                                })
                            }
                        />

                        <Select
                            value={table.filters.expense_category_id ?? ANY}
                            onValueChange={(value) =>
                                visit({
                                    expense_category_id:
                                        String(value) === ANY
                                            ? null
                                            : String(value),
                                })
                            }
                        >
                            <SelectTrigger
                                className="w-44"
                                aria-label="Filter by category"
                            >
                                <SelectValue placeholder="Category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>
                                    All categories
                                </SelectItem>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={String(category.id)}
                                    >
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {(table.filters.from ||
                            table.filters.expense_category_id) && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    visit({
                                        from: null,
                                        to: null,
                                        expense_category_id: null,
                                    })
                                }
                            >
                                <X data-icon="inline-start" />
                                Clear
                            </Button>
                        )}
                    </>
                }
                empty={
                    <EmptyState
                        icon={Wallet}
                        title="No expenses"
                        description="Record rent, wages, fuel and anything else it costs to trade."
                        action={
                            <Button onClick={openCreate}>
                                <Plus data-icon="inline-start" />
                                New expense
                            </Button>
                        }
                    />
                }
            />

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                {editing ? 'Edit expense' : 'New expense'}
                            </DialogTitle>
                            <DialogDescription>
                                Running costs only — buying stock is a purchase,
                                not an expense.
                            </DialogDescription>
                        </DialogHeader>

                        <FieldGroup className="py-4">
                            <div className="grid gap-6 sm:grid-cols-2">
                                <FormField
                                    label={`Amount (${currency.code})`}
                                    error={form.errors.amount}
                                >
                                    {(control) => (
                                        <Input
                                            {...control}
                                            inputMode="decimal"
                                            placeholder="0.00"
                                            className="text-right tabular-nums"
                                            autoFocus
                                            value={form.data.amount}
                                            onChange={(event) =>
                                                form.setData(
                                                    'amount',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>

                                <FormField
                                    label="Date"
                                    error={form.errors.spent_on}
                                >
                                    {(control) => (
                                        <Input
                                            {...control}
                                            type="date"
                                            value={form.data.spent_on}
                                            onChange={(event) =>
                                                form.setData(
                                                    'spent_on',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                            </div>

                            <FormField
                                label="Category"
                                error={form.errors.expense_category_id}
                            >
                                {(control) => (
                                    <Select
                                        value={form.data.expense_category_id}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'expense_category_id',
                                                String(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            {...control}
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Choose a category" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((category) => (
                                                <SelectItem
                                                    key={category.id}
                                                    value={String(category.id)}
                                                >
                                                    {category.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </FormField>

                            <FormField
                                label="Paid by"
                                error={form.errors.payment_method}
                            >
                                {(control) => (
                                    <Select
                                        value={form.data.payment_method}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'payment_method',
                                                String(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            {...control}
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {paymentMethods.map((method) => (
                                                <SelectItem
                                                    key={method.value}
                                                    value={method.value}
                                                >
                                                    {method.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </FormField>

                            <FormField
                                label="Reference"
                                error={form.errors.reference}
                                description="Receipt or invoice number."
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        value={form.data.reference}
                                        onChange={(event) =>
                                            form.setData(
                                                'reference',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>

                            <FormField label="Notes" error={form.errors.notes}>
                                {(control) => (
                                    <Textarea
                                        {...control}
                                        rows={2}
                                        value={form.data.notes}
                                        onChange={(event) =>
                                            form.setData(
                                                'notes',
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
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'Save' : 'Record'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={managingCategories}
                onOpenChange={setManagingCategories}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Categories</DialogTitle>
                        <DialogDescription>
                            How expenses are grouped in reports. A category with
                            expenses against it cannot be deleted.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-2 py-4">
                        {categories.map((category) => (
                            <div
                                key={category.id}
                                className="flex items-center justify-between rounded-md border px-3 py-2"
                            >
                                <span className="text-sm">{category.name}</span>
                                <div className="flex items-center gap-2">
                                    <Badge variant="secondary">
                                        {category.expenses_count ?? 0}
                                    </Badge>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label={`Delete ${category.name}`}
                                        onClick={() =>
                                            router.delete(
                                                expenseCategories.destroy(
                                                    category.id,
                                                ).url,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </div>
                        ))}

                        <form
                            className="flex items-start gap-2 pt-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                categoryForm.post(
                                    expenseCategories.store().url,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => categoryForm.reset(),
                                    },
                                );
                            }}
                        >
                            <div className="flex-1">
                                <Input
                                    placeholder="New category"
                                    aria-label="New category name"
                                    aria-invalid={
                                        categoryForm.errors.name
                                            ? true
                                            : undefined
                                    }
                                    value={categoryForm.data.name}
                                    onChange={(event) =>
                                        categoryForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                                {categoryForm.errors.name && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {categoryForm.errors.name}
                                    </p>
                                )}
                            </div>
                            <Button
                                type="submit"
                                disabled={categoryForm.processing}
                            >
                                Add
                            </Button>
                        </form>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

ExpensesIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
