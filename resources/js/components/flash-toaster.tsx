import { router } from '@inertiajs/react';
import { useEffect } from 'react';

import { toast } from '@/components/ui/toast';

/**
 * Turns flash data from the server into a toast.
 *
 * Listens on the router's `flash` event rather than reading a shared prop, so
 * a message fires exactly once and never resurfaces when the user navigates
 * back to the page it was shown on.
 */
export function FlashToaster() {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = event.detail.flash.toast;

            if (flash) {
                toast.add({ title: flash.message, type: flash.type });
            }
        });
    }, []);

    return null;
}
