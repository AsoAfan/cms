import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useCurrency } from '@/hooks/use-currency';
import { cn } from '@/lib/utils';
import { rebuildItemRows } from '@/lib/variants';
import type { Attribute, ItemFormRow, ProductFormData } from '@/types/catalog';

export type ProductFormProps = {
    attributes: Attribute[];
    product?: ProductFormData;
    action: { url: string; method: 'post' | 'put' };
    title: string;
    /** Extra header actions, e.g. delete on the edit screen. */
    headerActions?: React.ReactNode;
    submitLabel: string;
};

function initialSelectedValues(
    product: ProductFormData | undefined,
    attributes: Attribute[],
): Record<number, number[]> {
    if (!product) {
        return {};
    }

    const chosen = new Set(
        product.variants.flatMap((variant) => variant.attribute_value_ids),
    );
    const selected: Record<number, number[]> = {};

    for (const attribute of attributes) {
        const values = attribute.values
            .filter((value) => chosen.has(value.id))
            .map((value) => value.id);

        if (values.length > 0) {
            selected[attribute.id] = values;
        }
    }

    return selected;
}

/** Validation note under a table input, reserved so rows don't jump. */
function FieldNote({ message }: { message?: string }) {
    return (
        <p
            className={cn(
                'mt-1 text-xs text-destructive',
                !message && 'sr-only',
            )}
        >
            {message}
        </p>
    );
}

export function ProductForm({
    attributes,
    product,
    action,
    title,
    headerActions,
    submitLabel,
}: ProductFormProps) {
    const currency = useCurrency();
    const isEditing = product !== undefined;

    const [selectedAttributeIds, setSelectedAttributeIds] = useState<number[]>(
        () => product?.attribute_ids ?? [],
    );
    const [selectedValues, setSelectedValues] = useState<
        Record<number, number[]>
    >(() => initialSelectedValues(product, attributes));

    const form = useForm<ProductFormData>({
        name: product?.name ?? '',
        description: product?.description ?? '',
        is_active: product?.is_active ?? true,
        attribute_ids: product?.attribute_ids ?? [],
        variants: product?.variants ?? [
            {
                code: '',
                default_cost_price: null,
                default_selling_price: null,
                is_active: true,
                attribute_value_ids: [],
                label: '',
            },
        ],
    });

    const chosenAttributes = attributes.filter((attribute) =>
        selectedAttributeIds.includes(attribute.id),
    );

    function regenerate(
        attributeIds: number[],
        values: Record<number, number[]>,
    ) {
        const chosen = attributes.filter((attribute) =>
            attributeIds.includes(attribute.id),
        );

        form.setData(
            'variants',
            rebuildItemRows(form.data.name, chosen, values, form.data.variants),
        );
    }

    function toggleAttribute(attribute: Attribute, checked: boolean) {
        const attributeIds = checked
            ? [...selectedAttributeIds, attribute.id]
            : selectedAttributeIds.filter((id) => id !== attribute.id);

        const values = { ...selectedValues };

        if (checked) {
            values[attribute.id] = attribute.values.map((value) => value.id);
        } else {
            delete values[attribute.id];
        }

        setSelectedAttributeIds(attributeIds);
        setSelectedValues(values);
        regenerate(attributeIds, values);
    }

    function toggleValue(
        attribute: Attribute,
        valueId: number,
        checked: boolean,
    ) {
        const current = selectedValues[attribute.id] ?? [];
        const next = checked
            ? [...current, valueId]
            : current.filter((id) => id !== valueId);
        const values = { ...selectedValues, [attribute.id]: next };

        setSelectedValues(values);
        regenerate(selectedAttributeIds, values);
    }

    function updateItem(index: number, patch: Partial<ItemFormRow>) {
        form.setData(
            'variants',
            form.data.variants.map((row, position) =>
                position === index ? { ...row, ...patch } : row,
            ),
        );
    }

    function removeItem(index: number) {
        form.setData(
            'variants',
            form.data.variants.filter((_, position) => position !== index),
        );
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (action.method === 'put') {
            form.put(action.url, { preserveScroll: true });
        } else {
            form.post(action.url);
        }
    }

    function itemError(index: number, field: string): string | undefined {
        return form.errors[
            `variants.${index}.${field}` as keyof typeof form.errors
        ] as string | undefined;
    }

    const showOptionColumn = chosenAttributes.length > 0;

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <PageHeader
                title={title}
                actions={
                    <>
                        {headerActions}
                        <Button type="submit" disabled={form.processing}>
                            {submitLabel}
                        </Button>
                    </>
                }
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <FieldGroup>
                            <FormField label="Name" error={form.errors.name}>
                                {(control) => (
                                    <Input
                                        {...control}
                                        value={form.data.name}
                                        placeholder="Blackout Eyelet Curtain"
                                        autoFocus
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
                                label="Description"
                                error={form.errors.description}
                            >
                                {(control) => (
                                    <Textarea
                                        {...control}
                                        rows={3}
                                        value={form.data.description ?? ''}
                                        onChange={(event) =>
                                            form.setData(
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>

                            <Field orientation="horizontal">
                                <Checkbox
                                    id="is_active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_active',
                                            checked === true,
                                        )
                                    }
                                />
                                <FieldLabel
                                    htmlFor="is_active"
                                    className="font-normal"
                                >
                                    Active
                                </FieldLabel>
                            </Field>
                        </FieldGroup>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Options</CardTitle>
                        <CardDescription>
                            Tick what varies. One item per combination.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {attributes.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No options yet. Add one under Options.
                            </p>
                        ) : (
                            attributes.map((attribute, index) => {
                                const isChosen = selectedAttributeIds.includes(
                                    attribute.id,
                                );

                                return (
                                    <div
                                        key={attribute.id}
                                        className="flex flex-col gap-2"
                                    >
                                        {index > 0 && (
                                            <Separator className="mb-1" />
                                        )}

                                        <Field orientation="horizontal">
                                            <Checkbox
                                                id={`option-${attribute.id}`}
                                                checked={isChosen}
                                                onCheckedChange={(checked) =>
                                                    toggleAttribute(
                                                        attribute,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <FieldLabel
                                                htmlFor={`option-${attribute.id}`}
                                            >
                                                {attribute.name}
                                            </FieldLabel>
                                        </Field>

                                        {isChosen && (
                                            <div className="flex flex-wrap gap-x-4 gap-y-2 pl-6">
                                                {attribute.values.map(
                                                    (value) => (
                                                        <Field
                                                            key={value.id}
                                                            orientation="horizontal"
                                                            className="w-auto items-center"
                                                        >
                                                            <Checkbox
                                                                id={`value-${value.id}`}
                                                                checked={(
                                                                    selectedValues[
                                                                        attribute
                                                                            .id
                                                                    ] ?? []
                                                                ).includes(
                                                                    value.id,
                                                                )}
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    toggleValue(
                                                                        attribute,
                                                                        value.id,
                                                                        checked ===
                                                                            true,
                                                                    )
                                                                }
                                                            />
                                                            <FieldLabel
                                                                htmlFor={`value-${value.id}`}
                                                                className="font-normal"
                                                            >
                                                                {value.value}
                                                            </FieldLabel>
                                                        </Field>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>
                        Items{' '}
                        <span className="font-normal text-muted-foreground">
                            ({form.data.variants.length})
                        </span>
                    </CardTitle>
                    <CardDescription>
                        Prices are defaults. Each purchase and sale keeps its
                        own.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {typeof form.errors.variants === 'string' && (
                        <Alert variant="destructive">
                            <AlertDescription>
                                {form.errors.variants}
                            </AlertDescription>
                        </Alert>
                    )}

                    {isEditing && showOptionColumn && (
                        <p className="text-sm text-muted-foreground">
                            Saved items keep their options. Change the ticks
                            above to add or remove items.
                        </p>
                    )}

                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {showOptionColumn && (
                                        <TableHead className="w-[28%]">
                                            Item
                                        </TableHead>
                                    )}
                                    <TableHead>Code</TableHead>
                                    <TableHead className="w-36 text-right">
                                        Cost ({currency.code})
                                    </TableHead>
                                    <TableHead className="w-36 text-right">
                                        Price ({currency.code})
                                    </TableHead>
                                    <TableHead className="w-12" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {form.data.variants.map((item, index) => (
                                    <TableRow
                                        key={
                                            item.id ??
                                            `new-${item.attribute_value_ids.join('-')}-${index}`
                                        }
                                    >
                                        {showOptionColumn && (
                                            <TableCell className="font-medium whitespace-nowrap">
                                                {item.label}
                                            </TableCell>
                                        )}
                                        <TableCell>
                                            <Input
                                                value={item.code}
                                                aria-label={`Code for ${item.label || 'this product'}`}
                                                aria-invalid={
                                                    itemError(index, 'code')
                                                        ? true
                                                        : undefined
                                                }
                                                className="font-mono"
                                                onChange={(event) =>
                                                    updateItem(index, {
                                                        code: event.target
                                                            .value,
                                                    })
                                                }
                                            />
                                            <FieldNote
                                                message={itemError(
                                                    index,
                                                    'code',
                                                )}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                inputMode="decimal"
                                                placeholder="0.00"
                                                className="text-right tabular-nums"
                                                aria-label={`Cost for ${item.label || 'this product'}`}
                                                aria-invalid={
                                                    itemError(
                                                        index,
                                                        'default_cost_price',
                                                    )
                                                        ? true
                                                        : undefined
                                                }
                                                value={
                                                    item.default_cost_price ??
                                                    ''
                                                }
                                                onChange={(event) =>
                                                    updateItem(index, {
                                                        default_cost_price:
                                                            event.target
                                                                .value || null,
                                                    })
                                                }
                                            />
                                            <FieldNote
                                                message={itemError(
                                                    index,
                                                    'default_cost_price',
                                                )}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                inputMode="decimal"
                                                placeholder="0.00"
                                                className="text-right tabular-nums"
                                                aria-label={`Price for ${item.label || 'this product'}`}
                                                aria-invalid={
                                                    itemError(
                                                        index,
                                                        'default_selling_price',
                                                    )
                                                        ? true
                                                        : undefined
                                                }
                                                value={
                                                    item.default_selling_price ??
                                                    ''
                                                }
                                                onChange={(event) =>
                                                    updateItem(index, {
                                                        default_selling_price:
                                                            event.target
                                                                .value || null,
                                                    })
                                                }
                                            />
                                            <FieldNote
                                                message={itemError(
                                                    index,
                                                    'default_selling_price',
                                                )}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={`Remove ${item.label || item.code}`}
                                                disabled={
                                                    form.data.variants
                                                        .length === 1
                                                }
                                                onClick={() =>
                                                    removeItem(index)
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </form>
    );
}
