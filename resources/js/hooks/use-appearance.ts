import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

import type { Appearance } from '@/types';

const COOKIE_NAME = 'appearance';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 365;
const DARK_QUERY = '(prefers-color-scheme: dark)';

/**
 * Resolve `system` against the OS preference and write the result to <html>,
 * where Tailwind's `dark` variant and the root template's no-flash script
 * both look for it.
 */
function applyAppearance(appearance: Appearance): void {
    const root = document.documentElement;

    root.dataset.appearance = appearance;
    root.classList.toggle(
        'dark',
        appearance === 'dark' ||
            (appearance === 'system' && window.matchMedia(DARK_QUERY).matches),
    );
}

/**
 * The chosen theme, and a setter that persists it.
 *
 * The choice lives in a cookie rather than localStorage so the server can
 * stamp the theme onto <html> on the very first paint; the cookie is exempt
 * from encryption in `bootstrap/app.php` and comes back as the `appearance`
 * prop from `HandleInertiaRequests`.
 */
export function useAppearance(): {
    appearance: Appearance;
    updateAppearance: (appearance: Appearance) => void;
} {
    const [appearance, setAppearance] = useState<Appearance>(
        usePage().props.appearance,
    );

    const updateAppearance = useCallback((next: Appearance) => {
        setAppearance(next);
        applyAppearance(next);
        document.cookie = `${COOKIE_NAME}=${next}; path=/; max-age=${COOKIE_MAX_AGE}; SameSite=Lax`;
    }, []);

    // On `system`, keep following the OS while the page stays open.
    useEffect(() => {
        if (appearance !== 'system') {
            return;
        }

        const query = window.matchMedia(DARK_QUERY);
        const followSystem = () => applyAppearance('system');

        query.addEventListener('change', followSystem);

        return () => query.removeEventListener('change', followSystem);
    }, [appearance]);

    return { appearance, updateAppearance };
}
