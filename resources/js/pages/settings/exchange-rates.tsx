import { Head, router, useForm } from '@inertiajs/react';
import { Coins, Plus, Star, Trash2 } from 'lucide-react';

import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { FieldGroup } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { todayIso } from '@/lib/date';
import currencies from '@/routes/settings/currencies';
import exchangeRates from '@/routes/settings/exchange-rates';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings' },
    { title: 'Currencies' },
];

/** A currency this business deals in, with what it is worth today. */
type CurrencyRow = {
    id: number;
    code: string;
    name: string;
    symbol: string;
    fraction_digits: number;
    is_base: boolean;
    /** Named on a purchase, sale or expense — so it cannot be removed. */
    in_use: boolean;
    rate: string | null;
    effective_on: string | null;
};

type RateRow = {
    id: number;
    currency: string;
    rate: string;
    effective_on: string;
};

type RateForm = {
    currency: string;
    rate: string;
    effective_on: string;
};

type CurrencyForm = {
    code: string;
    name: string;
    symbol: string;
    fraction_digits: string;
};

export default function CurrencySettings({
    rows,
    table,
    base,
    currencies: list,
    canChangeBase,
}: {
    rows: Paginated<RateRow>;
    table: TableState;
    base: string;
    currencies: CurrencyRow[];
    canChangeBase: boolean;
}) {
    const foreign = list.filter((currency) => !currency.is_base);

    const rateForm = useForm<RateForm>({
        currency: foreign[0]?.code ?? '',
        rate: '',
        effective_on: todayIso(),
    });

    const currencyForm = useForm<CurrencyForm>({
        code: '',
        name: '',
        symbol: '',
        fraction_digits: '2',
    });

    function recordRate(event: React.FormEvent) {
        event.preventDefault();

        rateForm.post(exchangeRates.store.url(), {
            preserveScroll: true,
            onSuccess: () => rateForm.setData('rate', ''),
        });
    }

    function addCurrency(event: React.FormEvent) {
        event.preventDefault();

        currencyForm.post(currencies.store.url(), {
            preserveScroll: true,
            onSuccess: () => currencyForm.reset(),
        });
    }

    const columns: Column<RateRow>[] = [
        {
            key: 'effective_on',
            header: 'In force from',
            sortable: true,
            cell: (row) => row.effective_on,
        },
        {
            key: 'currency',
            header: 'Currency',
            sortable: true,
            cell: (row) => <span className="font-mono">{row.currency}</span>,
        },
        {
            key: 'rate',
            header: `${base} per unit`,
            sortable: true,
            align: 'right',
            cell: (row) => (
                <span className="font-mono tabular-nums">{row.rate}</span>
            ),
        },
        {
            key: 'actions',
            header: '',
            align: 'right',
            cell: (row) => (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label={`Remove the ${row.currency} rate from ${row.effective_on}`}
                    onClick={() =>
                        router.delete(exchangeRates.destroy.url(row.id), {
                            preserveScroll: true,
                        })
                    }
                >
                    <Trash2 />
                </Button>
            ),
        },
    ];

    return (
        <>
            <Head title="Currencies" />

            <PageHeader
                title="Currencies"
                description={`Your books are kept in ${base}. Every amount is stored in it, and the rates below are what convert one on the way in.`}
            />

            <Card>
                <CardHeader>
                    <CardTitle>Currencies you deal in</CardTitle>
                    <CardDescription>
                        {canChangeBase
                            ? 'The default is the currency your books are kept in. It can still be changed because nothing has been recorded yet.'
                            : 'The default is the currency your books are kept in. It is fixed now that there is money recorded against it.'}
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 lg:grid-cols-3">
                    {list.map((currency) => (
                        <div
                            key={currency.code}
                            className="flex flex-col gap-2 rounded-lg border p-4"
                        >
                            <div className="flex items-baseline justify-between gap-2">
                                <span className="font-mono font-medium">
                                    {currency.code}
                                </span>
                                {currency.is_base ? (
                                    <Badge>Default</Badge>
                                ) : (
                                    <span className="font-mono text-sm text-muted-foreground tabular-nums">
                                        {currency.rate ?? '—'}
                                    </span>
                                )}
                            </div>

                            <p className="text-xs text-muted-foreground">
                                {currency.is_base
                                    ? `${currency.name} · everything is stored in this`
                                    : currency.rate === null
                                      ? `${currency.name} · no rate yet, so it cannot be typed into a price`
                                      : `${currency.name} · ${currency.rate} ${base} from ${currency.effective_on}`}
                            </p>

                            {!currency.is_base && (
                                <div className="flex gap-1">
                                    <BaseButton
                                        currency={currency}
                                        canChangeBase={canChangeBase}
                                    />
                                    <RemoveButton currency={currency} />
                                </div>
                            )}
                        </div>
                    ))}
                </CardContent>
            </Card>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Add a currency</CardTitle>
                        <CardDescription>
                            Anything you buy or sell in. Record what it is worth
                            before it can be typed into a price.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={addCurrency}>
                            <FieldGroup>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="Code"
                                        error={currencyForm.errors.code}
                                        description="Three letters, like EUR."
                                    >
                                        {(control) => (
                                            <Input
                                                {...control}
                                                value={currencyForm.data.code}
                                                placeholder="EUR"
                                                autoComplete="off"
                                                maxLength={3}
                                                className="font-mono uppercase"
                                                onChange={(event) =>
                                                    currencyForm.setData(
                                                        'code',
                                                        event.target.value.toUpperCase(),
                                                    )
                                                }
                                            />
                                        )}
                                    </FormField>

                                    <FormField
                                        label="Symbol"
                                        error={currencyForm.errors.symbol}
                                        description="What it is written with."
                                    >
                                        {(control) => (
                                            <Input
                                                {...control}
                                                value={currencyForm.data.symbol}
                                                placeholder="€"
                                                autoComplete="off"
                                                onChange={(event) =>
                                                    currencyForm.setData(
                                                        'symbol',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        )}
                                    </FormField>
                                </div>

                                <FormField
                                    label="Name"
                                    error={currencyForm.errors.name}
                                >
                                    {(control) => (
                                        <Input
                                            {...control}
                                            value={currencyForm.data.name}
                                            placeholder="Euro"
                                            autoComplete="off"
                                            onChange={(event) =>
                                                currencyForm.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>

                                <FormField
                                    label="Decimal places"
                                    error={currencyForm.errors.fraction_digits}
                                    description="How it is shown. Dinars use none, dollars two."
                                >
                                    {(control) => (
                                        <OptionSelect
                                            {...control}
                                            className="w-full"
                                            value={
                                                currencyForm.data
                                                    .fraction_digits
                                            }
                                            options={['0', '2', '3'].map(
                                                (digits) => ({
                                                    value: digits,
                                                    label: digits,
                                                }),
                                            )}
                                            onChange={(value) =>
                                                currencyForm.setData(
                                                    'fraction_digits',
                                                    value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>

                                <Button
                                    type="submit"
                                    disabled={currencyForm.processing}
                                >
                                    <Plus data-icon="inline-start" />
                                    Add currency
                                </Button>
                            </FieldGroup>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Record a rate</CardTitle>
                        <CardDescription>
                            What you actually trade at, dated from when it
                            applies. A document uses whichever rate applied on
                            its own day.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {foreign.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Add a second currency and there will be
                                something to price against {base}.
                            </p>
                        ) : (
                            <form onSubmit={recordRate}>
                                <FieldGroup>
                                    <FormField
                                        label="Currency"
                                        error={rateForm.errors.currency}
                                    >
                                        {(control) => (
                                            <OptionSelect
                                                {...control}
                                                className="w-full"
                                                value={rateForm.data.currency}
                                                options={foreign.map(
                                                    (currency) => ({
                                                        value: currency.code,
                                                        label: `${currency.code} — ${currency.name}`,
                                                    }),
                                                )}
                                                onChange={(value) =>
                                                    rateForm.setData(
                                                        'currency',
                                                        value,
                                                    )
                                                }
                                            />
                                        )}
                                    </FormField>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <FormField
                                            label={`${base} per unit`}
                                            error={rateForm.errors.rate}
                                            description="How many you get for one."
                                        >
                                            {(control) => (
                                                <Input
                                                    {...control}
                                                    inputMode="decimal"
                                                    placeholder="1320"
                                                    className="text-right tabular-nums"
                                                    value={rateForm.data.rate}
                                                    onChange={(event) =>
                                                        rateForm.setData(
                                                            'rate',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                        </FormField>

                                        <FormField
                                            label="In force from"
                                            error={rateForm.errors.effective_on}
                                            description="Documents dated on or after this."
                                        >
                                            {(control) => (
                                                <Input
                                                    {...control}
                                                    type="date"
                                                    value={
                                                        rateForm.data
                                                            .effective_on
                                                    }
                                                    onChange={(event) =>
                                                        rateForm.setData(
                                                            'effective_on',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                        </FormField>
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={rateForm.processing}
                                    >
                                        Record rate
                                    </Button>
                                </FieldGroup>
                            </form>
                        )}
                    </CardContent>
                </Card>
            </div>

            <DataTable
                rows={rows}
                columns={columns}
                state={table}
                getRowKey={(row) => row.id}
                empty={
                    <EmptyState
                        icon={Coins}
                        title="No rates recorded"
                        description={`Record what a currency is worth in ${base}, and prices can be typed in it.`}
                    />
                }
            />
        </>
    );
}

/**
 * Moving the base re-denominates the books, so it is only on offer while they
 * are empty. Disabled with the reason attached beats hidden, which just leaves
 * someone hunting for it.
 */
function BaseButton({
    currency,
    canChangeBase,
}: {
    currency: CurrencyRow;
    canChangeBase: boolean;
}) {
    const button = (
        <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={!canChangeBase}
            onClick={() =>
                router.post(
                    currencies.default.url(currency.id),
                    {},
                    { preserveScroll: true },
                )
            }
        >
            <Star data-icon="inline-start" />
            Make default
        </Button>
    );

    if (canChangeBase) {
        return button;
    }

    return (
        <Tooltip>
            <TooltipTrigger render={<span tabIndex={0} />}>
                {button}
            </TooltipTrigger>
            <TooltipContent>
                There is money recorded already. Changing which currency the
                books are kept in would restate every past figure at a rate that
                was never used.
            </TooltipContent>
        </Tooltip>
    );
}

function RemoveButton({ currency }: { currency: CurrencyRow }) {
    const button = (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            disabled={currency.in_use}
            aria-label={`Remove ${currency.code}`}
            onClick={() =>
                router.delete(currencies.destroy.url(currency.id), {
                    preserveScroll: true,
                })
            }
        >
            <Trash2 data-icon="inline-start" />
            Remove
        </Button>
    );

    if (!currency.in_use) {
        return button;
    }

    return (
        <Tooltip>
            <TooltipTrigger render={<span tabIndex={0} />}>
                {button}
            </TooltipTrigger>
            <TooltipContent>
                {currency.code} is recorded on a purchase, sale or expense.
            </TooltipContent>
        </Tooltip>
    );
}

CurrencySettings.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
