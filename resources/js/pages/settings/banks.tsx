import { Head, router, useForm } from '@inertiajs/react';
import { Landmark, Pencil, Trash2, X } from 'lucide-react';
import { useState } from 'react';

import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
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
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import banks from '@/routes/settings/banks';
import type { BreadcrumbItem } from '@/types';
import type { BankRow, BankForm } from '@/types/banks';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings' },
    { title: 'Banks' },
];

const EMPTY: BankForm = { name: '', account_number: '', notes: '' };

export default function BankSettings({ banks: list }: { banks: BankRow[] }) {
    // Editing happens in the same form as adding, keyed by which bank is open.
    // A bank is three fields; a drawer for it would be more chrome than content.
    const [editing, setEditing] = useState<BankRow | null>(null);

    return (
        <>
            <Head title="Banks" />

            <PageHeader
                title="Banks"
                description="The accounts your card and transfer payments move through. Cash never names one."
            />

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Your accounts</CardTitle>
                        <CardDescription>
                            An account with payments against it cannot be
                            removed — its history would have nowhere to point.
                        </CardDescription>
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
                                />
                            ))
                        )}
                    </CardContent>
                </Card>

                <BankFormCard
                    // Keyed so opening a second bank starts from that bank's
                    // values rather than the last one's.
                    key={editing?.id ?? 'new'}
                    editing={editing}
                    onDone={() => setEditing(null)}
                />
            </div>
        </>
    );
}

function BankCard({ bank, onEdit }: { bank: BankRow; onEdit: () => void }) {
    const used = bank.sales_count + bank.expenses_count + bank.payments_count;

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
                        ? 'Nothing paid through it yet'
                        : [
                              bank.sales_count && `${bank.sales_count} sales`,
                              bank.expenses_count &&
                                  `${bank.expenses_count} expenses`,
                              bank.payments_count &&
                                  `${bank.payments_count} repayments`,
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

            <div className="flex gap-1">
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
                {bank.name} has payments against it.
            </TooltipContent>
        </Tooltip>
    );
}

BankSettings.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
