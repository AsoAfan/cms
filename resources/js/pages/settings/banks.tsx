import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowRightLeft,
    Landmark,
    Pencil,
    Scale,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { MoneyDisplay } from '@/components/money-display';
import { MoneyInput } from '@/components/money-input';
import { OptionSelect } from '@/components/option-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCurrency, useFormatMoney } from '@/hooks/use-currency';
import AppLayout from '@/layouts/app-layout';
import { todayIso } from '@/lib/date';
import banks from '@/routes/settings/banks';
import type { BreadcrumbItem } from '@/types';
import type {
    BankAdjustmentDirectionOption,
    BankAdjustmentForm,
    BankForm,
    BankMovementRow,
    BankRow,
    BankTransferForm,
} from '@/types/banks';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings' },
    { title: 'Banks' },
];

const EMPTY: BankForm = { name: '', account_number: '', notes: '' };

export default function BankSettings({
    banks: list,
    balanceTotal,
    movements,
    directions,
}: {
    banks: BankRow[];
    /** What the accounts hold between them. Minor units. */
    balanceTotal: number;
    /** Everything moved by hand, both kinds in one list. */
    movements: BankMovementRow[];
    directions: BankAdjustmentDirectionOption[];
}) {
    // Editing happens in the same form as adding, keyed by which bank is open.
    // A bank is three fields; a drawer for it would be more chrome than content.
    const [editing, setEditing] = useState<BankRow | null>(null);

    // Which account is having its balance set by hand, if any.
    const [adjusting, setAdjusting] = useState<BankRow | null>(null);

    // Which account money is being moved out of, if any. Moving needs two
    // accounts, so the button only exists once there are two.
    const [moving, setMoving] = useState<BankRow | null>(null);

    return (
        <>
            <Head title="Banks" />

            <PageHeader
                title="Banks"
                description="The accounts your card and transfer payments move through, and what each one holds."
            />

            <div className="grid items-start gap-6 lg:grid-cols-[2fr_1fr]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Your accounts</CardTitle>
                            <CardDescription>
                                Balances are worked out from everything that
                                moved through the account — nothing here is a
                                stored figure.
                            </CardDescription>

                            {list.length > 0 && (
                                <CardAction className="text-right">
                                    <p className="text-xs text-muted-foreground">
                                        Across all accounts
                                    </p>
                                    <MoneyDisplay
                                        amount={balanceTotal}
                                        colored
                                        className="text-lg font-medium"
                                    />
                                </CardAction>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {list.length === 0 ? (
                                <EmptyState
                                    icon={Landmark}
                                    title="No banks yet"
                                    description="Add one and it can be named on card and transfer payments."
                                />
                            ) : (
                                list.map((bank) => (
                                    <BankCard
                                        key={bank.id}
                                        bank={bank}
                                        onEdit={() => setEditing(bank)}
                                        onAdjust={() => setAdjusting(bank)}
                                        onMove={
                                            list.length > 1
                                                ? () => setMoving(bank)
                                                : undefined
                                        }
                                    />
                                ))
                            )}
                        </CardContent>
                    </Card>

                    {movements.length > 0 && (
                        <MovementsCard movements={movements} />
                    )}
                </div>

                <BankFormCard
                    // Keyed so opening a second bank starts from that bank's
                    // values rather than the last one's.
                    key={editing?.id ?? 'new'}
                    editing={editing}
                    onDone={() => setEditing(null)}
                />
            </div>

            <BalanceDialog
                bank={adjusting}
                directions={directions}
                onOpenChange={(open) => !open && setAdjusting(null)}
            />

            <TransferDialog
                from={moving}
                banks={list}
                onOpenChange={(open) => !open && setMoving(null)}
            />
        </>
    );
}

function BankCard({
    bank,
    onEdit,
    onAdjust,
    onMove,
}: {
    bank: BankRow;
    onEdit: () => void;
    onAdjust: () => void;
    /** Absent while there is nowhere to move money to. */
    onMove?: () => void;
}) {
    const used =
        bank.sales_count +
        bank.purchases_count +
        bank.expenses_count +
        bank.payments_count +
        bank.adjustments_count +
        bank.transfers_count;

    return (
        <div className="flex items-start justify-between gap-4 rounded-lg border p-4">
            <div className="grid gap-1">
                <span className="font-medium">{bank.name}</span>

                {bank.account_number && (
                    <span className="font-mono text-xs text-muted-foreground">
                        {bank.account_number}
                    </span>
                )}

                <p className="text-xs text-muted-foreground">
                    {used === 0
                        ? 'Nothing through it yet'
                        : [
                              bank.sales_count && `${bank.sales_count} sales`,
                              bank.purchases_count &&
                                  `${bank.purchases_count} purchases`,
                              bank.expenses_count &&
                                  `${bank.expenses_count} expenses`,
                              bank.payments_count &&
                                  `${bank.payments_count} repayments`,
                              bank.adjustments_count &&
                                  `${bank.adjustments_count} by hand`,
                              bank.transfers_count &&
                                  `${bank.transfers_count} transfers`,
                          ]
                              .filter(Boolean)
                              .join(' · ')}
                </p>

                {bank.notes && (
                    <p className="text-xs text-muted-foreground">
                        {bank.notes}
                    </p>
                )}
            </div>

            <div className="flex items-center gap-1">
                <MoneyDisplay
                    amount={bank.balance}
                    colored
                    className="mr-2 font-medium"
                />

                {onMove && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        aria-label={`Move money out of ${bank.name}`}
                        onClick={onMove}
                    >
                        <ArrowRightLeft />
                    </Button>
                )}

                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label={`Set the balance of ${bank.name}`}
                    onClick={onAdjust}
                >
                    <Scale />
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label={`Edit ${bank.name}`}
                    onClick={onEdit}
                >
                    <Pencil />
                </Button>

                <RemoveButton bank={bank} inUse={used > 0} />
            </div>
        </div>
    );
}

/**
 * Everything moved by hand — an opening balance, cash deposited, a charge, and
 * money shifted between the accounts.
 *
 * One list rather than two, because a balance is checked against a history and
 * a history split in half is two things to read. A transfer shows the route it
 * took and no sign: nothing left the business, so the figure belongs to the
 * pair of accounts rather than to either one.
 */
function MovementsCard({ movements }: { movements: BankMovementRow[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Moved by hand</CardTitle>
                <CardDescription>
                    Money in, out, or between accounts that no sale, purchase or
                    expense accounts for. Everything else in a balance comes
                    from documents.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-2">
                {movements.map((movement) => (
                    <div
                        key={`${movement.kind}-${movement.id}`}
                        className="flex items-center justify-between gap-4 rounded-lg border p-3"
                    >
                        <div className="grid gap-0.5">
                            <span className="text-sm font-medium">
                                {movement.label}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {movement.detail} · {movement.occurred_on}
                            </span>
                        </div>

                        <div className="flex items-center gap-1">
                            <MoneyDisplay
                                amount={movement.amount}
                                colored={movement.kind === 'adjustment'}
                                signed={movement.kind === 'adjustment'}
                            />

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                aria-label={`Remove ${movement.label}`}
                                onClick={() =>
                                    router.delete(
                                        movement.kind === 'transfer'
                                            ? banks.transfers.destroy.url(
                                                  movement.id,
                                              )
                                            : banks.balance.destroy.url(
                                                  movement.id,
                                              ),
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

/**
 * Moving money from one of the business's accounts to another.
 *
 * Opened from the account the money is leaving, so the common case is one
 * click and then a destination. Nothing about it is income or outcome: the
 * total across the accounts is the same afterwards.
 */
function TransferDialog({
    from,
    banks: list,
    onOpenChange,
}: {
    from: BankRow | null;
    banks: BankRow[];
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={from !== null} onOpenChange={onOpenChange}>
            <DialogContent>
                {from !== null && (
                    <TransferForm
                        key={from.id}
                        from={from}
                        banks={list}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function TransferForm({
    from,
    banks: list,
    onDone,
}: {
    from: BankRow;
    banks: BankRow[];
    onDone: () => void;
}) {
    const { base } = useCurrency();
    const format = useFormatMoney();

    const form = useForm<BankTransferForm>({
        from_bank_id: String(from.id),
        to_bank_id: '',
        amount: '',
        amount_currency: base,
        occurred_on: todayIso(),
        reason: '',
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.post(banks.transfers.store.url(), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    const options = list.map((bank) => ({
        value: String(bank.id),
        label: bank.name,
    }));

    /**
     * An account cannot pay itself, so choosing the account money is already
     * going to as the one it leaves clears the destination rather than posting
     * a pair the server will refuse.
     */
    function changeFrom(value: string) {
        form.setData((data) => ({
            ...data,
            from_bank_id: value,
            to_bank_id: data.to_bank_id === value ? '' : data.to_bank_id,
        }));
    }

    const leaving = list.find(
        (bank) => String(bank.id) === form.data.from_bank_id,
    );

    return (
        <form onSubmit={submit} className="grid gap-6">
            <DialogHeader>
                <DialogTitle className="pr-8">Move money</DialogTitle>
                <DialogDescription>
                    Between your own accounts. Nothing is earned or spent — one
                    balance goes down and the other up by the same amount.
                </DialogDescription>
            </DialogHeader>

            <FieldGroup>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Out of"
                        error={form.errors.from_bank_id}
                        description={
                            leaving
                                ? undefined
                                : 'Pick the account the money leaves.'
                        }
                    >
                        {(control) => (
                            <OptionSelect
                                {...control}
                                className="w-full"
                                value={form.data.from_bank_id}
                                options={options}
                                onChange={(value) => changeFrom(String(value))}
                            />
                        )}
                    </FormField>

                    <FormField label="Into" error={form.errors.to_bank_id}>
                        {(control) => (
                            <OptionSelect
                                {...control}
                                className="w-full"
                                value={form.data.to_bank_id}
                                // The account it is leaving is not a place it
                                // can go, so it is not on the list.
                                options={options.filter(
                                    (option) =>
                                        option.value !== form.data.from_bank_id,
                                )}
                                onChange={(value) =>
                                    form.setData('to_bank_id', String(value))
                                }
                                placeholder="Pick an account"
                            />
                        )}
                    </FormField>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Amount"
                        error={form.errors.amount}
                        description={
                            leaving
                                ? `${format(leaving.balance)} in there now.`
                                : undefined
                        }
                    >
                        {(control) => (
                            <MoneyInput
                                {...control}
                                value={form.data.amount}
                                currency={form.data.amount_currency}
                                autoFocus
                                onChange={(value) =>
                                    form.setData('amount', value)
                                }
                                onCurrencyChange={(next) =>
                                    form.setData('amount_currency', next)
                                }
                            />
                        )}
                    </FormField>

                    <FormField
                        label="Date"
                        error={form.errors.occurred_on}
                        description="Converted at that day's rate."
                    >
                        {(control) => (
                            <Input
                                {...control}
                                type="date"
                                value={form.data.occurred_on}
                                onChange={(event) =>
                                    form.setData(
                                        'occurred_on',
                                        event.target.value,
                                    )
                                }
                            />
                        )}
                    </FormField>
                </div>

                <FormField
                    label="Note"
                    error={form.errors.reason}
                    description="Optional — the two accounts and the date already say what this was."
                >
                    {(control) => (
                        <Input
                            {...control}
                            value={form.data.reason}
                            placeholder="Takings banked"
                            autoComplete="off"
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    )}
                </FormField>
            </FieldGroup>

            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Move it
                </Button>
            </DialogFooter>
        </form>
    );
}

/**
 * Putting money into an account, or taking it back out, without a document
 * behind it.
 *
 * The amount is typed as a positive figure and the direction says which way it
 * went — the server applies the sign, so no field on screen can hold a minus
 * nobody meant.
 */
function BalanceDialog({
    bank,
    directions,
    onOpenChange,
}: {
    bank: BankRow | null;
    directions: BankAdjustmentDirectionOption[];
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={bank !== null} onOpenChange={onOpenChange}>
            <DialogContent>
                {bank !== null && (
                    <BalanceForm
                        key={bank.id}
                        bank={bank}
                        directions={directions}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function BalanceForm({
    bank,
    directions,
    onDone,
}: {
    bank: BankRow;
    directions: BankAdjustmentDirectionOption[];
    onDone: () => void;
}) {
    const { base } = useCurrency();

    const form = useForm<BankAdjustmentForm>({
        direction: directions[0]?.value ?? 'in',
        reason: '',
        amount: '',
        amount_currency: base,
        occurred_on: todayIso(),
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form.post(banks.balance.store.url(bank.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    const chosen = directions.find(
        (direction) => direction.value === form.data.direction,
    );

    return (
        <form onSubmit={submit} className="grid gap-6">
            <DialogHeader>
                <DialogTitle className="pr-8">
                    {bank.name}&rsquo;s balance
                </DialogTitle>
                <DialogDescription>
                    Currently <MoneyDisplay amount={bank.balance} colored />.
                    Trade moves this on its own — record here only what no
                    document explains.
                </DialogDescription>
            </DialogHeader>

            <FieldGroup>
                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Direction"
                        error={form.errors.direction}
                        description={chosen?.description}
                    >
                        {(control) => (
                            <OptionSelect
                                {...control}
                                className="w-full"
                                value={form.data.direction}
                                options={directions}
                                onChange={(value) =>
                                    form.setData('direction', String(value))
                                }
                            />
                        )}
                    </FormField>

                    <FormField label="Amount" error={form.errors.amount}>
                        {(control) => (
                            <MoneyInput
                                {...control}
                                value={form.data.amount}
                                currency={form.data.amount_currency}
                                autoFocus
                                onChange={(value) =>
                                    form.setData('amount', value)
                                }
                                onCurrencyChange={(next) =>
                                    form.setData('amount_currency', next)
                                }
                            />
                        )}
                    </FormField>
                </div>

                <FormField
                    label="What this is"
                    error={form.errors.reason}
                    description="Opening balance, cash deposited, bank charge."
                >
                    {(control) => (
                        <Input
                            {...control}
                            value={form.data.reason}
                            placeholder="Opening balance"
                            autoComplete="off"
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                        />
                    )}
                </FormField>

                <FormField
                    label="Date"
                    error={form.errors.occurred_on}
                    description="Converted at that day's rate."
                >
                    {(control) => (
                        <Input
                            {...control}
                            type="date"
                            value={form.data.occurred_on}
                            onChange={(event) =>
                                form.setData('occurred_on', event.target.value)
                            }
                        />
                    )}
                </FormField>
            </FieldGroup>

            <DialogFooter>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Record it
                </Button>
            </DialogFooter>
        </form>
    );
}

function BankFormCard({
    editing,
    onDone,
}: {
    editing: BankRow | null;
    onDone: () => void;
}) {
    const form = useForm<BankForm>(
        editing
            ? {
                  name: editing.name,
                  account_number: editing.account_number ?? '',
                  notes: editing.notes ?? '',
              }
            : EMPTY,
    );

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onDone();
            },
        };

        if (editing) {
            form.put(banks.update.url(editing.id), options);

            return;
        }

        form.post(banks.store.url(), options);
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    {editing ? `Edit ${editing.name}` : 'Add a bank'}
                </CardTitle>
                <CardDescription>
                    The name is what you will pick from when recording a
                    payment.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit}>
                    <FieldGroup>
                        <FormField label="Name" error={form.errors.name}>
                            {(control) => (
                                <Input
                                    {...control}
                                    value={form.data.name}
                                    placeholder="Cihan Bank"
                                    autoComplete="off"
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                            )}
                        </FormField>

                        <FormField
                            label="Account number"
                            error={form.errors.account_number}
                            description="Optional. Useful for telling two accounts at the same bank apart."
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    value={form.data.account_number}
                                    autoComplete="off"
                                    className="font-mono"
                                    onChange={(event) =>
                                        form.setData(
                                            'account_number',
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

                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'Save changes' : 'Add bank'}
                            </Button>

                            {editing && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={onDone}
                                >
                                    <X data-icon="inline-start" />
                                    Cancel
                                </Button>
                            )}
                        </div>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}

/**
 * Disabled with the reason attached beats hidden, which leaves someone hunting
 * for a button that was never there.
 */
function RemoveButton({ bank, inUse }: { bank: BankRow; inUse: boolean }) {
    const button = (
        <Button
            type="button"
            variant="ghost"
            size="icon-sm"
            disabled={inUse}
            aria-label={`Remove ${bank.name}`}
            onClick={() =>
                router.delete(banks.destroy.url(bank.id), {
                    preserveScroll: true,
                })
            }
        >
            <Trash2 />
        </Button>
    );

    if (!inUse) {
        return button;
    }

    return (
        <Tooltip>
            <TooltipTrigger render={<span tabIndex={0} />}>
                {button}
            </TooltipTrigger>
            <TooltipContent>
                {bank.name} has money recorded against it.
            </TooltipContent>
        </Tooltip>
    );
}

BankSettings.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
