import { Head, useForm, usePage } from '@inertiajs/react';
import { format, formatDistanceToNow } from 'date-fns';
import {
    CircleAlert,
    CircleCheck,
    Download,
    RefreshCw,
    TriangleAlert,
} from 'lucide-react';

import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import update from '@/routes/settings/update';
import type { BreadcrumbItem } from '@/types';
import type { Release } from '@/types/updates';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings' },
    { title: 'Updates' },
];

export default function UpdateSettings({
    configured,
    remote,
    branch,
    installed,
}: {
    configured: boolean;
    remote: string;
    branch: string;
    installed: Release | null;
}) {
    // Whether anything is waiting is the same answer the sidebar's dot reads,
    // so the two can never disagree. This screen only shows it in full.
    const announcement = usePage().props.update;

    const check = useForm({ force: 1 });
    const install = useForm({});

    const busy = check.processing || install.processing;
    const waiting = announcement?.available ? announcement.latest : null;
    const changes = announcement?.changes ?? [];

    return (
        <>
            <Head title="Updates" />

            <PageHeader
                title="Updates"
                description="The app checks for new versions on its own and tells you when one is ready. You can also check here."
            />

            {!configured && (
                <Alert variant="destructive">
                    <CircleAlert />
                    <AlertTitle>This copy cannot receive updates</AlertTitle>
                    <AlertDescription>
                        No update source has been set up. Whoever installed the
                        app needs to fill in <code>UPDATE_REMOTE</code>.
                    </AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>What you have now</CardTitle>
                    <CardDescription>
                        {configured
                            ? `Following ${branch} on ${remote}`
                            : 'No update source configured.'}
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    {installed ? (
                        <div className="grid gap-1">
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-semibold tracking-tight">
                                    {releaseDate(installed)}
                                </span>
                                <Badge variant="secondary">
                                    {installed.short}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {installed.subject}
                            </p>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            This copy was not installed with git, so its version
                            cannot be read and updates cannot be applied.
                        </p>
                    )}

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            variant="outline"
                            onClick={() =>
                                check.post(update.check.url(), {
                                    preserveScroll: true,
                                })
                            }
                            disabled={!configured || busy}
                        >
                            {check.processing ? <Spinner /> : <RefreshCw />}
                            {check.processing ? 'Checking…' : 'Check now'}
                        </Button>

                        {announcement?.checked_at && (
                            <span className="text-sm text-muted-foreground">
                                Last checked{' '}
                                {formatDistanceToNow(
                                    new Date(announcement.checked_at),
                                    { addSuffix: true },
                                )}
                            </span>
                        )}
                    </div>
                </CardContent>
            </Card>

            {announcement?.checked_at && !waiting && (
                <Alert>
                    <CircleCheck className="text-emerald-600 dark:text-emerald-500" />
                    <AlertTitle>You have the newest version</AlertTitle>
                    <AlertDescription>
                        There is nothing to install.
                    </AlertDescription>
                </Alert>
            )}

            {waiting && (
                <Card className="border-primary/40">
                    <CardHeader>
                        <CardTitle>An update is ready</CardTitle>
                        <CardDescription>
                            Released {releaseDate(waiting)} · version{' '}
                            {waiting.short}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        {changes.length > 0 && (
                            <div className="grid gap-2">
                                <p className="text-sm font-medium">
                                    What&rsquo;s new
                                </p>
                                <ul className="grid gap-1 text-sm text-muted-foreground">
                                    {changes.map((change, index) => (
                                        <li
                                            key={`${index}-${change}`}
                                            className="flex gap-2"
                                        >
                                            <span aria-hidden>&bull;</span>
                                            <span>{change}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <Alert>
                            <TriangleAlert />
                            <AlertTitle>
                                Leave this window open while it runs
                            </AlertTitle>
                            <AlertDescription>
                                Your records are copied to a backup first. If
                                anything goes wrong the update undoes itself and
                                puts everything back. It usually takes under a
                                minute.
                            </AlertDescription>
                        </Alert>

                        <div>
                            <Button
                                size="lg"
                                onClick={() =>
                                    install.post(update.store.url(), {
                                        preserveScroll: true,
                                    })
                                }
                                disabled={busy}
                            >
                                {install.processing ? (
                                    <Spinner />
                                ) : (
                                    <Download />
                                )}
                                {install.processing
                                    ? 'Installing…'
                                    : 'Install update'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}
        </>
    );
}

/**
 * A release is identified to the user by the day it was made, because that is
 * the part they can place against "it started doing this on Tuesday". The sha
 * sits beside it for the support call.
 */
function releaseDate(release: Release): string {
    return format(new Date(release.committed_at), 'd MMM yyyy');
}

UpdateSettings.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
