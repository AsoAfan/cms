import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, X } from 'lucide-react';
import { useState } from 'react';

import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
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
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { destroy, store, update } from '@/routes/attributes';
import type { BreadcrumbItem, Paginated, TableState } from '@/types';
import type { Attribute } from '@/types/catalog';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Options' }];

type AttributeForm = {
    name: string;
    values: string[];
};

export default function AttributesIndex({
    rows,
    table,
}: {
    rows: Paginated<Attribute>;
    table: TableState;
}) {
    const [editing, setEditing] = useState<Attribute | null>(null);
    const [open, setOpen] = useState(false);
    const [draftValue, setDraftValue] = useState('');

    const form = useForm<AttributeForm>({ name: '', values: [] });

    function openCreate() {
        form.setData({ name: '', values: [] });
        form.clearErrors();
        setDraftValue('');
        setEditing(null);
        setOpen(true);
    }

    function openEdit(attribute: Attribute) {
        form.setData({
            name: attribute.name,
            values: attribute.values.map((value) => value.value),
        });
        form.clearErrors();
        setDraftValue('');
        setEditing(attribute);
        setOpen(true);
    }

    function addValue() {
        const value = draftValue.trim();

        if (
            value === '' ||
            form.data.values.some(
                (existing) => existing.toLowerCase() === value.toLowerCase(),
            )
        ) {
            setDraftValue('');

            return;
        }

        form.setData('values', [...form.data.values, value]);
        setDraftValue('');
    }

    function removeValue(index: number) {
        form.setData(
            'values',
            form.data.values.filter((_, position) => position !== index),
        );
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

    const columns: Column<Attribute>[] = [
        {
            key: 'name',
            header: 'Option',
            sortable: true,
            cell: (row) => <span className="font-medium">{row.name}</span>,
        },
        {
            key: 'values',
            header: 'Values',
            cell: (row) => (
                <div className="flex flex-wrap gap-1">
                    {row.values.map((value) => (
                        <Badge key={value.id} variant="secondary">
                            {value.value}
                        </Badge>
                    ))}
                </div>
            ),
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
                        aria-label={`Edit ${row.name}`}
                        onClick={() => openEdit(row)}
                    >
                        <Pencil />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label={`Delete ${row.name}`}
                        onClick={() =>
                            form.delete(destroy(row.id).url, {
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
            <Head title="Options" />

            <PageHeader
                title="Options"
                description="How products vary — width, drop, colour."
                actions={
                    <Button onClick={openCreate}>
                        <Plus data-icon="inline-start" />
                        New option
                    </Button>
                }
            />

            <DataTable
                rows={rows}
                columns={columns}
                state={table}
                getRowKey={(row) => row.id}
                searchPlaceholder="Search options"
                empty={
                    <EmptyState
                        title="No options yet"
                        description="Add one like Width or Drop."
                        action={
                            <Button onClick={openCreate}>
                                <Plus data-icon="inline-start" />
                                New option
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
                                {editing ? 'Edit attribute' : 'New option'}
                            </DialogTitle>
                            <DialogDescription>
                                Values already used by an item can&apos;t be
                                removed.
                            </DialogDescription>
                        </DialogHeader>

                        <FieldGroup className="py-4">
                            <FormField label="Name" error={form.errors.name}>
                                {(control) => (
                                    <Input
                                        {...control}
                                        value={form.data.name}
                                        placeholder="Width"
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>

                            <FormField
                                label="Values"
                                error={form.errors.values}
                                description="Press Enter to add each."
                            >
                                {(control) => (
                                    <div className="flex flex-col gap-2">
                                        <Input
                                            {...control}
                                            value={draftValue}
                                            placeholder="117cm"
                                            onChange={(event) =>
                                                setDraftValue(
                                                    event.target.value,
                                                )
                                            }
                                            onKeyDown={(event) => {
                                                if (event.key === 'Enter') {
                                                    event.preventDefault();
                                                    addValue();
                                                }
                                            }}
                                        />
                                        {form.data.values.length > 0 && (
                                            <div className="flex flex-wrap gap-1">
                                                {form.data.values.map(
                                                    (value, index) => (
                                                        <Badge
                                                            key={value}
                                                            variant="secondary"
                                                            className="gap-1 pr-1"
                                                        >
                                                            {value}
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon-xs"
                                                                aria-label={`Remove ${value}`}
                                                                onClick={() =>
                                                                    removeValue(
                                                                        index,
                                                                    )
                                                                }
                                                            >
                                                                <X />
                                                            </Button>
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </div>
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
                                {form.processing && (
                                    <Spinner data-icon="inline-start" />
                                )}
                                {editing ? 'Save changes' : 'Create option'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

AttributesIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
