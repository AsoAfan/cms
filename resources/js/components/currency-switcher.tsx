import { Coins } from 'lucide-react';
import { useCallback, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCurrency } from '@/hooks/use-currency';
import { RATE_SCALE } from '@/lib/money';

const COOKIE_NAME = 'display_currency';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

/**
 * Changes the currency figures are READ in. It changes nothing that is stored:
 * every amount stays in the base currency, and this only converts on the way to
 * the screen.
 *
 * The choice lives in a cookie, like the theme does, so the server can share it
 * back on the next visit and the first paint is already right. Nothing is
 * fetched — the rates came with the page — so switching is instant.
 */
export function CurrencySwitcher() {
    const { base, display, currencies } = useCurrency();
    const [selected, setSelected] = useState(display);

    const choose = useCallback((next: string) => {
        setSelected(next);
        document.cookie = `${COOKIE_NAME}=${next}; path=/; max-age=${COOKIE_MAX_AGE}; SameSite=Lax`;
        // The prop feeding `useFormatMoney` comes from the server, so the
        // figures on screen only change once it has re-shared it.
        window.location.reload();
    }, []);

    if (currencies.length < 2) {
        return null;
    }

    const active = currencies.find((currency) => currency.code === selected);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        variant="ghost"
                        size="sm"
                        className="gap-1.5 font-mono text-xs"
                        aria-label="Change the currency figures are shown in"
                    />
                }
            >
                <Coins className="size-4" />
                {selected}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <DropdownMenuRadioGroup
                    value={selected}
                    onValueChange={(value) => choose(String(value))}
                >
                    {/*
                     * The label lives inside the radio group, not beside it:
                     * Base UI reads a menu label through the group's context,
                     * so a loose one throws rather than renders — and being
                     * inside is what names the group to a screen reader.
                     */}
                    <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                        Show figures in
                    </DropdownMenuLabel>
                    {currencies.map((currency) => (
                        <DropdownMenuRadioItem
                            key={currency.code}
                            value={currency.code}
                        >
                            <span className="font-mono">{currency.code}</span>
                            <span className="text-muted-foreground">
                                {currency.name}
                            </span>
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>

                {/*
                 * A converted figure is only as good as the rate behind it, so
                 * the rate and its date are named rather than left to be
                 * assumed. Nothing is being restated in dinars — those are the
                 * figures as recorded.
                 */}
                {active !== undefined && active.code !== base && (
                    <>
                        <DropdownMenuSeparator />
                        <p className="px-2 py-1.5 text-xs text-muted-foreground">
                            Converted from {base} at {active.rate / RATE_SCALE}
                            {active.rate_on ? ` as at ${active.rate_on}` : ''}.
                            Amounts are recorded in {base}.
                        </p>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
