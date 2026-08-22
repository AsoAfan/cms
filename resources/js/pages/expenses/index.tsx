import { Head, useForm, router } from '@inertiajs/react';
import { Pencil, Plus, Tags, Trash2, Wallet, X } from 'lucide-react';
import { useState } from 'react';

import { BankField, bankAfterMethodChange } from '@/components/bank-field';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { DateRangePicker } from '@/components/date-range-picker';
import type { DateRange } from '@/components/date-range-picker';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { MoneyDisplay } from '@/components/money-display';
import { MoneyInput } from '@/components/money-input';
import { OptionSelect } from '@/components/option-select';
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
import { Textarea } from '@/components/ui/textarea';
import { useCurrency } from '@/hooks/use-currency';
import AppLayout from '@/layouts/app-layout';
import { toDecimalString } from '@/lib/money';
import expenseCategories from '@/routes/expense-categories';
import { destroy, store, update } from '@/routes/expenses';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';
import type { BankOption } from '@/types/banks';
import { NO_BANK } from '@/types/banks';
import type {
    ExpenseCategoryRow,
    ExpenseRow,
    PaymentMethodOption,
} from '@/types/expenses';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Expenses' }];

const ANY = 'any';

type ExpenseForm = {
    expense_category_id: string;
    title: string;
    amount: string;
    /** What the amount is being typed in; the server converts to the base. */
    amount_currency: string;
    /** What the money actually changed hands in, recorded on the expense. */
    currency: string;
    spent_on: string;
    payment_method: string;
    /** Which account it went out of. Empty on cash — a select cannot hold null. */
    bank_id: string;
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
    banks,
    filteredTotal,
}: {
    rows: Paginated<ExpenseRow>;
    table: TableState;
    categories: ExpenseCategoryRow[];
    paymentMethods: PaymentMethodOption[];
    banks: BankOption[];
    filteredTotal: number;
}) {
    const { base } = useCurrency();
    const [editing, setEditing] = useState<ExpenseRow | null>(null);
    const [open, setOpen] = useState(false);
    const [managingCategories, setManagingCategories] = useState(false);

    const form = useForm<ExpenseForm>({
        expense_category_id: '',
        title: '',
        amount: '',
        amount_currency: base,
        currency: base,
        spent_on: today(),
        payment_method: paymentMethods[0]?.value ?? 'cash',
        bank_id: NO_BANK,
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
            expense_category_id: String(categories[0]?.name ?? ''),
            title: '',
            amount: '',
            amount_currency: base,
            currency: base,
            spent_on: today(),
            payment_method: paymentMethods[0]?.value ?? 'cash',
            bank_id: NO_BANK,
            notes: '',
        });
        form.clearErrors();
        setEditing(null);
        setOpen(true);
    }

    function openEdit(row: ExpenseRow) {
        form.setData({
            expense_category_id: String(row.category_id),
            title: row.title,
            // Stored in the base currency, so the field reopens in it whatever
            // it was originally typed in.
            amount: toDecimalString(row.amount),
            amount_currency: base,
            currency: base,
            spent_on: row.spent_on,
            payment_method: row.payment_method,
            bank_id: row.bank_id === null ? NO_BANK : String(row.bank_id),
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
            key: 'title',
            header: 'Title',
            cell: (row) => <span className="font-medium">{row.title}</span>,
        },
        {
            key: 'category',
            header: 'Category',
            cell: (row) => <Badge variant="secondary">{row.category}</Badge>,
        },
        {
            key: 'payment_method_label',
            header: 'Paid by',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-muted-foreground">
                    {row.payment_method_label}
                    {row.bank && ` · ${row.bank}`}
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
                        aria-label={`Edit ${row.title}`}
                        onClick={() => openEdit(row)}
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={`Delete ${row.title}`}
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
                searchPlaceholder="Search title or notes"
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

                        <OptionSelect
                            className="w-44"
                            aria-label="Filter by category"
                            value={table.filters.expense_category_id ?? ANY}
                            options={[
                                { value: ANY, label: 'All categories' },
                                ...categories.map((category) => ({
                                    value: String(category.id),
                                    label: category.name,
                                })),
                            ]}
                            onChange={(value) =>
                                visit({
                                    expense_category_id:
                                        value === ANY ? null : value,
                                })
                            }
                            placeholder="Category"
                        />

                        {banks.length > 0 && (
                            <OptionSelect
                                className="w-44"
                                aria-label="Filter by bank"
                                value={table.filters.bank_id ?? ANY}
                                options={[
                                    { value: ANY, label: 'All banks' },
                                    ...banks.map((bank) => ({
                                        value: String(bank.id),
                                        label: bank.name,
                                    })),
                                ]}
                                onChange={(value) =>
                                    visit({
                                        bank_id: value === ANY ? null : value,
                                    })
                                }
                                placeholder="Bank"
                            />
                        )}

                        {(table.filters.from ||
                            table.filters.expense_category_id ||
                            table.filters.bank_id) && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    visit({
                                        from: null,
                                        to: null,
                                        expense_category_id: null,
                                        bank_id: null,
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
                            <FormField
                                label="Title"
                                error={form.errors.title}
                                description="What the money went on — “February rent”, “Van diesel”."
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        autoFocus
                                        value={form.data.title}
                                        onChange={(event) =>
                                            form.setData(
                                                'title',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>

                            <div className="grid gap-6 sm:grid-cols-2">
                                <FormField
                                    label="Amount"
                                    error={form.errors.amount}
                                >
                                    {(control) => (
                                        <MoneyInput
                                            {...control}
                                            value={form.data.amount}
                                            currency={form.data.amount_currency}
                                            onChange={(value) =>
                                                form.setData('amount', value)
                                            }
                                            onCurrencyChange={(next) =>
                                                // The amount is the only figure
                                                // here, so what it is typed in
                                                // IS what was paid in.
                                                form.setData((data) => ({
                                                    ...data,
                                                    amount_currency: next,
                                                    currency: next,
                                                }))
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
                                    <OptionSelect
                                        {...control}
                                        className="w-full"
                                        value={form.data.expense_category_id}
                                        options={categories.map((category) => ({
                                            value: String(category.id),
                                            label: category.name,
                                        }))}
                                        onChange={(value) =>
                                            form.setData(
                                                'expense_category_id',
                                                value,
                                            )
                                        }
                                        placeholder="Choose a category"
                                    />
                                )}
                            </FormField>

                            <FormField
                                label="Paid by"
                                error={form.errors.payment_method}
                            >
                                {(control) => (
                                    <OptionSelect
                                        {...control}
                                        className="w-full"
                                        value={form.data.payment_method}
                                        options={paymentMethods}
                                        onChange={(value) => {
                                            form.setData((data) => ({
                                                ...data,
                                                payment_method: value,
                                                bank_id: bankAfterMethodChange(
                                                    paymentMethods,
                                                    value,
                                                    data.bank_id,
                                                ),
                                            }));
                                        }}
                                    />
                                )}
                            </FormField>

                            <BankField
                                banks={banks}
                                methods={paymentMethods}
                                method={form.data.payment_method}
                                value={form.data.bank_id}
                                error={form.errors.bank_id}
                                onChange={(value) =>
                                    form.setData('bank_id', value)
                                }
                            />

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
