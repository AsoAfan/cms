import { router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { useEffect, useRef } from 'react';

import { toast } from '@/components/ui/toast';
import update from '@/routes/settings/update';

/** Remembered per tab, so the same release is announced once and not on every visit. */
const ANNOUNCED = 'update-announced';

/**
 * Notices new releases on the client's behalf, and says so once.
 *
 * The owner of a shop will not think to go looking in Settings for an update,
 * so the app has to raise it. Two things do that: the dot on the sidebar's
 * Updates entry, which stays for as long as something is waiting, and one toast
 * per release, which is the part that actually interrupts.
 *
 * The check itself runs from here rather than on the server during a page load.
 * A shop's internet is not reliable, and a settings screen — or worse, every
 * screen — that waits twenty seconds on a dead connection reads as a broken
 * app. By the time this fires the page is already on screen, so a slow or
 * failed check costs the user nothing and is never mentioned to them.
 *
 * Mounted once in `AppLayout`, which is a persistent layout, so this survives
 * navigation. The props it reads change on every visit, which is what gives a
 * long-lived tab a fresh chance to check.
 */
export function UpdateNotice() {
    const announcement = usePage().props.update;
    const checking = useRef(false);

    const stale = announcement?.stale ?? false;

    useEffect(() => {
        if (!stale || checking.current) {
            return;
        }

        checking.current = true;

        router.post(
            update.check.url(),
            {},
            {
                // Only the shared prop can have changed, and the user is in the
                // middle of something: keep their page, their scroll and their
                // half-filled form exactly as they are.
                only: ['update'],
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    checking.current = false;
                },
            },
        );
    }, [stale]);

    const waiting = announcement?.available ? announcement.latest : null;

    useEffect(() => {
        if (!waiting) {
            return;
        }

        if (readAnnounced() === waiting.sha) {
            return;
        }

        writeAnnounced(waiting.sha);

        toast.add({
            type: 'info',
            title: 'An update is ready',
            description: `Version from ${format(new Date(waiting.committed_at), 'd MMM yyyy')}. It takes under a minute to install.`,
            timeout: 0,
            actionProps: {
                children: 'Install',
                onClick: () => router.visit(update.index.url()),
            },
        });
    }, [waiting]);

    return null;
}

/**
 * Session storage, guarded.
 *
 * A browser set to block site data throws on the accessor itself rather than
 * returning nothing, and an update notice is not worth taking a screen down
 * for. Losing it costs one repeated toast.
 */
function readAnnounced(): string | null {
    try {
        return sessionStorage.getItem(ANNOUNCED);
    } catch {
        return null;
    }
}

function writeAnnounced(sha: string): void {
    try {
        sessionStorage.setItem(ANNOUNCED, sha);
    } catch {
        // Nothing to do: the toast simply shows again next time.
    }
}
