import { FormField } from '@/components/form-field';
import { MoneyInput } from '@/components/money-input';
import { FieldGroup } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCurrency } from '@/hooks/use-currency';
import type { ProductFormData } from '@/types/catalog';

export type ProductFieldsProps = {
    data: ProductFormData;
    errors: Partial<Record<keyof ProductFormData, string>>;
    onChange: (key: keyof ProductFormData, value: string) => void;
    autoFocus?: boolean;
};

/**
 * The four things a product is. Shared by both drawers so creating and editing
 * one never drift apart.
 *
 * Cost and price are both required — a product nobody has priced cannot be sold.
 * Either can be typed in a supplier's currency and converts on the way in; each
 * field carries its own switcher, so a dollar cost can sit beside a dinar price.
 */
export function ProductFields({
    data,
    errors,
    onChange,
    autoFocus = false,
}: ProductFieldsProps) {
    const { base } = useCurrency();

    return (
        <FieldGroup>
            <FormField label="Name" error={errors.name}>
                {(control) => (
                    <Input
                        {...control}
                        value={data.name}
                        placeholder="Blackout Eyelet Curtain 117x137"
                        autoFocus={autoFocus}
                        autoComplete="off"
                        onChange={(event) =>
                            onChange('name', event.target.value)
                        }
                    />
                )}
            </FormField>

            <div className="grid gap-4 sm:grid-cols-2">
                <FormField
                    label="Cost"
                    error={errors.cost_price}
                    description="What you pay for it."
                >
                    {(control) => (
                        <MoneyInput
                            {...control}
                            value={data.cost_price}
                            currency={data.cost_price_currency || base}
                            onChange={(value) => onChange('cost_price', value)}
                            onCurrencyChange={(currency) =>
                                onChange('cost_price_currency', currency)
                            }
                        />
                    )}
                </FormField>

                <FormField
                    label="Price"
                    error={errors.selling_price}
                    description="What you charge for it."
                >
                    {(control) => (
                        <MoneyInput
                            {...control}
                            value={data.selling_price}
                            currency={data.selling_price_currency || base}
                            onChange={(value) =>
                                onChange('selling_price', value)
                            }
                            onCurrencyChange={(currency) =>
                                onChange('selling_price_currency', currency)
                            }
                        />
                    )}
                </FormField>
            </div>

            <FormField label="Description" error={errors.description}>
                {(control) => (
                    <Textarea
                        {...control}
                        rows={2}
                        value={data.description}
                        placeholder="Optional."
                        onChange={(event) =>
                            onChange('description', event.target.value)
                        }
                    />
                )}
            </FormField>
        </FieldGroup>
    );
}

/**
 * Both drawers start from the same shape, so an empty form and a loaded one
 * only ever differ by the values in it.
 */
export function emptyProductForm(base: string): ProductFormData {
    return {
        name: '',
        description: '',
        cost_price: '',
        cost_price_currency: base,
        selling_price: '',
        selling_price_currency: base,
    };
}
