import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    useCurrency,
    useCurrencyOption,
    useCurrencyOptions,
    useFormatMoneyIn,
    useRestate,
} from '@/hooks/use-currency';
import { convertToBase, parseMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

export type MoneyInputProps = {
    /** The amount as typed, e.g. `"18.00"`. Kept as a string so it round-trips exactly. */
    value: string;
    /** The currency this one field is being typed in. */
    currency: string;
    /** Called with the amount restated when the currency changes. */
    onChange: (value: string) => void;
    onCurrencyChange: (currency: string) => void;
    /** Spread from `<FormField>` so the label and error stay wired up. */
    id?: string;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
    'aria-label'?: string;
    placeholder?: string;
    autoFocus?: boolean;
    disabled?: boolean;
    className?: string;
    onKeyDown?: React.KeyboardEventHandler<HTMLInputElement>;
};

/**
 * An amount, with the currency it is written in attached to the field itself.
 *
 * **Picking a currency converts what is in the box.** A field showing $18.50 set
 * to dinars becomes 24,420 — the same money, said differently — at the published
 * rate the page was served with. Swapping the label and leaving the digits alone
 * would quietly turn eighteen dollars into eighteen dinars.
 *
 * The dropdown belongs to THIS field and changes nothing else on the form: a
 * dollar-priced line can sit next to a dinar-priced freight charge on the same
 * invoice.
 *
 * Whatever it ends up in, the server converts to the base currency before
 * anything is stored, using the same integer rounding as the "≈" line here — so
 * what is previewed is what is kept, to the last dinar.
 */
export function MoneyInput({
    value,
    currency,
    onChange,
    onCurrencyChange,
    placeholder = '0',
    className,
    onKeyDown,
    ...control
}: MoneyInputProps) {
    const { base } = useCurrency();
    const options = useCurrencyOptions();
    const option = useCurrencyOption(currency);
    const formatIn = useFormatMoneyIn();
    const restate = useRestate();

    const typed = parseMoney(value);
    const isBase = currency === base;
    const converted =
        typed === null || isBase ? null : convertToBase(typed, option.rate);

    function chooseCurrency(next: string) {
        if (next === currency) {
            return;
        }

        // Value first, then the currency: the field must never be readable as
        // the old number under the new currency, not even for one render.
        onChange(restate(value, currency, next));
        onCurrencyChange(next);
    }

    return (
        <div className={cn('flex flex-col gap-1', className)}>
            <InputGroup>
                <InputGroupInput
                    {...control}
                    inputMode="decimal"
                    placeholder={placeholder}
                    className="text-right tabular-nums"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    onKeyDown={onKeyDown}
                />
                {options.length > 1 && (
                    /*
                     * A segment of the field rather than a hint inside it. It
                     * was styled borderless and transparent to begin with and
                     * simply did not read as something you could click: a
                     * right-aligned number ran straight into it. The divider,
                     * the tint and the pointer cursor are what make it a
                     * control.
                     */
                    <InputGroupAddon
                        align="inline-end"
                        className="h-full py-0 pr-0"
                    >
                        <Select
                            value={currency}
                            onValueChange={(next) =>
                                chooseCurrency(String(next))
                            }
                        >
                            <SelectTrigger
                                size="sm"
                                aria-label="Currency for this amount"
                                title="Show this amount in another currency"
                                className="h-full cursor-pointer gap-1 rounded-none rounded-r-md border-0 border-l border-input bg-muted/60 px-2 font-mono text-xs font-medium hover:bg-muted dark:bg-input/50 dark:hover:bg-input"
                            >
                                {/* No `items` on the root, so the trigger shows
                                    the raw value — the code, which is the whole
                                    label this control has room for. The popup's
                                    items spell it out. */}
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.map((item) => (
                                    <SelectItem
                                        key={item.code}
                                        value={item.code}
                                    >
                                        <span className="font-mono">
                                            {item.code}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {item.name}
                                        </span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </InputGroupAddon>
                )}
            </InputGroup>

            {converted !== null && (
                <p className="text-right text-xs text-muted-foreground tabular-nums">
                    ≈ {formatIn(converted, base)}
                </p>
            )}
        </div>
    );
}
