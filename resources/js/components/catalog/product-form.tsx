import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Spinner } from '@/components/ui/spinner';
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
import { rebuildItemRows } from '@/lib/variants';
import type { Attribute, ItemFormRow, ProductFormData } from '@/types/catalog';

export type ProductFormProps = {
    attributes: Attribute[];
    product?: ProductFormData;
    /** Where the form posts, and how. */
    action: { url: string; method: 'post' | 'put' };
    title: string;
    description: string;
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

export function ProductForm({
    attributes,
    product,
    action,
    title,
    description,
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
        name = form.data.name,
    ) {
        const chosen = attributes.filter((attribute) =>
            attributeIds.includes(attribute.id),
        );

        form.setData(
            'variants',
            rebuildItemRows(name, chosen, values, form.data.variants),
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

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <PageHeader
                title={title}
                description={description}
                actions={
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && (
                            <Spinner data-icon="inline-start" />
                        )}
                        {submitLabel}
                    </Button>
                }
            />

            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                    <CardDescription>
                        What this product is and how it is grouped.
                    </CardDescription>
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
                                        form.setData('name', event.target.value)
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
                                    form.setData('is_active', checked === true)
                                }
                            />
                            <FieldLabel
                                htmlFor="is_active"
                                className="font-normal"
                            >
                                Active — available to buy and sell
                            </FieldLabel>
                        </Field>
                    </FieldGroup>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Options</CardTitle>
                    <CardDescription>
                        Pick the options this product varies along. An item is
                        generated for every combination. Leave all unticked for
                        a product sold as a single item.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {attributes.length === 0 && (
                        <Alert>
                            <AlertDescription>
                                No attributes defined yet. Add one under
                                Catalogue → Attributes to build a variant
                                matrix.
                            </AlertDescription>
                        </Alert>
                    )}

                    {isEditing && selectedAttributeIds.length > 0 && (
                        <Alert>
                            <AlertDescription>
                                A saved item&apos;s options are fixed —
                                &ldquo;117cm / 137cm&rdquo; is part of what that
                                item is. You can add or remove options to change
                                which items exist, but the ones that remain keep
                                their meaning.
                            </AlertDescription>
                        </Alert>
                    )}

                    {attributes.map((attribute) => {
                        const isChosen = selectedAttributeIds.includes(
                            attribute.id,
                        );

                        return (
                            <div
                                key={attribute.id}
                                className="flex flex-col gap-2 rounded-lg border p-3"
                            >
                                <Field orientation="horizontal">
                                    <Checkbox
                                        id={`attribute-${attribute.id}`}
                                        checked={isChosen}
                                        onCheckedChange={(checked) =>
                                            toggleAttribute(
                                                attribute,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <FieldLabel
                                        htmlFor={`attribute-${attribute.id}`}
                                    >
                                        {attribute.name}
                                    </FieldLabel>
                                </Field>

                                {isChosen && (
                                    <div className="flex flex-wrap gap-3 pl-6">
                                        {attribute.values.map((value) => (
                                            <Field
                                                key={value.id}
                                                orientation="horizontal"
                                                className="w-auto items-center"
                                            >
                                                <Checkbox
                                                    id={`value-${value.id}`}
                                                    checked={(
                                                        selectedValues[
                                                            attribute.id
                                                        ] ?? []
                                                    ).includes(value.id)}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleValue(
                                                            attribute,
                                                            value.id,
                                                            checked === true,
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
                                        ))}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>
                        Items
                        <Badge variant="secondary" className="ml-2">
                            {form.data.variants.length}
                        </Badge>
                    </CardTitle>
                    <CardDescription>
                        Prices here are defaults that pre-fill data entry. Every
                        purchase and sale records its own price, so changing
                        these never rewrites history.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {typeof form.errors.variants === 'string' && (
                        <Alert variant="destructive" className="mb-4">
                            <AlertDescription>
                                {form.errors.variants}
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {chosenAttributes.length > 0 && (
                                        <TableHead>Options</TableHead>
                                    )}
                                    <TableHead>Code</TableHead>
                                    <TableHead className="text-right">
                                        Cost
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Selling price
                                    </TableHead>
                                    <TableHead />
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
                                        {chosenAttributes.length > 0 && (
                                            <TableCell className="whitespace-nowrap">
                                                <span className="font-medium">
                                                    {item.label}
                                                </span>
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
                                                onChange={(event) =>
                                                    updateItem(index, {
                                                        code: event.target
                                                            .value,
                                                    })
                                                }
                                            />
                                            {itemError(index, 'code') && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    {itemError(index, 'code')}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <InputGroup>
                                                <InputGroupAddon>
                                                    {currency.code}
                                                </InputGroupAddon>
                                                <InputGroupInput
                                                    inputMode="decimal"
                                                    placeholder="0.00"
                                                    className="text-right"
                                                    aria-label={`Cost for ${item.label || 'this product'}`}
                                                    value={
                                                        item.default_cost_price ??
                                                        ''
                                                    }
                                                    onChange={(event) =>
                                                        updateItem(index, {
                                                            default_cost_price:
                                                                event.target
                                                                    .value ||
                                                                null,
                                                        })
                                                    }
                                                />
                                            </InputGroup>
                                            {itemError(
                                                index,
                                                'default_cost_price',
                                            ) && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    {itemError(
                                                        index,
                                                        'default_cost_price',
                                                    )}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <InputGroup>
                                                <InputGroupAddon>
                                                    {currency.code}
                                                </InputGroupAddon>
                                                <InputGroupInput
                                                    inputMode="decimal"
                                                    placeholder="0.00"
                                                    className="text-right"
                                                    aria-label={`Selling price for ${item.label || 'this product'}`}
                                                    value={
                                                        item.default_selling_price ??
                                                        ''
                                                    }
                                                    onChange={(event) =>
                                                        updateItem(index, {
                                                            default_selling_price:
                                                                event.target
                                                                    .value ||
                                                                null,
                                                        })
                                                    }
                                                />
                                            </InputGroup>
                                            {itemError(
                                                index,
                                                'default_selling_price',
                                            ) && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    {itemError(
                                                        index,
                                                        'default_selling_price',
                                                    )}
                                                </p>
                                            )}
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

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
