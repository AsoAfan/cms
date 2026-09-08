import { useForm } from '@inertiajs/react';

import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { CustomerFormData } from '@/types/customers';

export type CustomerFormProps = {
    customer?: CustomerFormData;
    action: { url: string; method: 'post' | 'put' };
    title: string;
    /** Extra header actions, e.g. delete on the edit screen. */
    headerActions?: React.ReactNode;
    submitLabel: string;
};

export function CustomerForm({
    customer,
    action,
    title,
    headerActions,
    submitLabel,
}: CustomerFormProps) {
    const form = useForm<CustomerFormData>({
        name: customer?.name ?? '',
        phone: customer?.phone ?? '',
        email: customer?.email ?? '',
        address: customer?.address ?? '',
        notes: customer?.notes ?? '',
        is_active: customer?.is_active ?? true,
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
                        <FormField
                            label="Name"
                            error={form.errors.name}
                            description="How they are picked on the sale screen. A second Ahmed gets his phone number in the name."
                        >
                            {(control) => (
                                <Input
                                    {...control}
                                    value={form.data.name}
                                    placeholder="Ahmed Karim"
                                    autoFocus
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                />
                            )}
                        </FormField>

                        <div className="grid gap-6 sm:grid-cols-2">
                            <FormField label="Phone" error={form.errors.phone}>
                                {(control) => (
                                    <Input
                                        {...control}
                                        type="tel"
                                        value={form.data.phone ?? ''}
                                        onChange={(event) =>
                                            form.setData(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>

                            <FormField label="Email" error={form.errors.email}>
                                {(control) => (
                                    <Input
                                        {...control}
                                        type="email"
                                        value={form.data.email ?? ''}
                                        onChange={(event) =>
                                            form.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                    />
                                )}
                            </FormField>
                        </div>

                        <FormField label="Address" error={form.errors.address}>
                            {(control) => (
                                <Textarea
                                    {...control}
                                    rows={2}
                                    value={form.data.address ?? ''}
                                    onChange={(event) =>
                                        form.setData(
                                            'address',
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
                                    rows={3}
                                    value={form.data.notes ?? ''}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
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
