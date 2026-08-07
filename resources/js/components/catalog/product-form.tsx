import { useForm } from '@inertiajs/react';

import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCurrency } from '@/hooks/use-currency';
import type { ProductFormData } from '@/types/catalog';

export type ProductFormProps = {
    product?: ProductFormData;
    action: { url: string; method: 'post' | 'put' };
    title: string;
    /** Extra header actions, e.g. delete on the edit screen. */
    headerActions?: React.ReactNode;
    submitLabel: string;
};

export function ProductForm({
    product,
    action,
    title,
    headerActions,
    submitLabel,
}: ProductFormProps) {
    const currency = useCurrency();

    const form = useForm<ProductFormData>({
        name: product?.name ?? '',
        code: product?.code ?? '',
        description: product?.description ?? '',
        default_cost_price: product?.default_cost_price ?? '',
        default_selling_price: product?.default_selling_price ?? '',
        is_active: product?.is_active ?? true,
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();

        if (action.method === 'put') {
            form.put(action.url, { preserveScroll: true });
        } else {
            form.post(action.url);
        }
    }

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

            <Card className="max-w-2xl">
                <CardContent>
                    <FieldGroup>
                        <FormField label="Name" error={form.errors.name}>
                            {(control) => (
                                <Input
                                    {...control}
                                    value={form.data.name}
                                    placeholder="Blackout Eyelet Curtain 117x137"
                                    autoFocus
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                            )}
                        </FormField>

                        <FormField
                            label="Code"
                            error={form.errors.code}
                            description="Unique. Printed on the label."
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    value={form.data.code}
                                    placeholder="BEC-117-137"
                                    className="font-mono"
                                    onChange={(event) =>
                                        form.setData('code', event.target.value)
                                    }
                                />
                            )}
                        </FormField>

                        <div className="grid gap-6 sm:grid-cols-2">
                            <FormField
                                label={`Cost (${currency.code})`}
                                error={form.errors.default_cost_price}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        inputMode="decimal"
                                        placeholder="0.00"
                                        className="text-right tabular-nums"
                                        value={
                                            form.data.default_cost_price ?? ''
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'default_cost_price',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>

                            <FormField
                                label={`Price (${currency.code})`}
                                error={form.errors.default_selling_price}
                            >
                                {(control) => (
                                    <Input
                                        {...control}
                                        inputMode="decimal"
                                        placeholder="0.00"
                                        className="text-right tabular-nums"
                                        value={
                                            form.data.default_selling_price ??
                                            ''
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'default_selling_price',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        </div>

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
                                    form.setData('is_active', checked === true)
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
        </form>
    );
}
