import type { ComponentProps } from 'react';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/** One choice in a dropdown: what is stored, and what the user reads. */
export type SelectOption = {
    value: string;
    label: string;
    /** Listed but not choosable — a state the form can be in without asking for it. */
    disabled?: boolean;
};

export type OptionSelectProps = Omit<
    ComponentProps<typeof SelectTrigger>,
    'value' | 'defaultValue' | 'onChange' | 'children'
> & {
    value: string;
    options: readonly SelectOption[];
    onChange: (value: string) => void;
    /** Shown while nothing is chosen. An empty value counts as nothing. */
    placeholder?: string;
};

/**
 * A dropdown that reads back the chosen option's label.
 *
 * Base UI's `<Select.Value>` prints the raw value unless the root is handed the
 * labels through `items` — a customer select left the id "1" sitting in the
 * trigger where the name belongs, and it never corrects itself, because the
 * fallback is the stringified value rather than the item's text.
 *
 * Taking the options as data is what keeps the two from drifting: the list in
 * the popup and the labels behind the trigger are the same array. Compose the
 * primitives directly only where an item needs richer markup than a label, and
 * then pass `items` yourself.
 */
export function OptionSelect({
    value,
    options,
    onChange,
    placeholder,
    ...trigger
}: OptionSelectProps) {
    return (
        <Select
            items={options}
            value={value}
            onValueChange={(next) => onChange(String(next))}
        >
            <SelectTrigger {...trigger}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem
                        key={option.value}
                        value={option.value}
                        disabled={option.disabled}
                    >
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
