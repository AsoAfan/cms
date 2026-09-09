import { Head, useForm } from '@inertiajs/react';
import { format, formatDistanceToNow } from 'date-fns';
import { CircleAlert, DatabaseBackup, Download, HardDrive } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
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
import backup from '@/routes/settings/backup';
import type { BreadcrumbItem } from '@/types';
import type { BackupRow } from '@/types/backups';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings' },
    { title: 'Backup' },
];

export default function BackupSettings({
    supported,
    backups,
    directory,
    keep,
}: {
    /** False where the records live on a database server this cannot copy. */
    supported: boolean;
    backups: BackupRow[];
    /** Where the copies are kept, so they can be found without the app. */
    directory: string;
    keep: number;
}) {
    const take = useForm({});

    return (
        <>
            <Head title="Backup" />

            <PageHeader
                title="Backup"
                description="A copy of everything the app has recorded — products, invoices, payments and settings — in one file."
                actions={
                    <Button
                        size="lg"
                        onClick={() =>
                            take.post(backup.store.url(), {
                                preserveScroll: true,
                            })
                        }
                        disabled={!supported || take.processing}
                    >
                        {take.processing ? <Spinner /> : <DatabaseBackup />}
                        {take.processing ? 'Backing up…' : 'Back up now'}
                    </Button>
                }
            />

            {!supported && (
                <Alert variant="destructive">
                    <CircleAlert />
                    <AlertTitle>This copy cannot be backed up here</AlertTitle>
                    <AlertDescription>
                        The records are kept on a database server rather than in
                        a file on this computer, so backups are whoever
                        administers that server&rsquo;s to take.
                    </AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Copies on this computer</CardTitle>
                    <CardDescription>
                        Taken when you press the button, and automatically
                        before every update. The newest {keep} are kept and
                        older ones are deleted to make room.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    {backups.length === 0 ? (
                        <EmptyState
                            icon={HardDrive}
                            title="No backups yet"
                            description="Press Back up now to take the first one."
                        />
                    ) : (
                        <ul className="grid gap-2">
                            {backups.map((row, index) => (
                                <li
                                    key={row.name}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                                >
                                    <div className="grid gap-1">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">
                                                {takenOn(row)}
                                            </span>
                                            {index === 0 && (
                                                <Badge variant="secondary">
                                                    Newest
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {formatDistanceToNow(
                                                new Date(row.taken_at),
                                                { addSuffix: true },
                                            )}{' '}
                                            &middot; {fileSize(row.bytes)}
                                        </p>
                                    </div>

                                    <Button
                                        variant="outline"
                                        render={
                                            <a
                                                href={backup.download.url(
                                                    row.name,
                                                )}
                                                download
                                            />
                                        }
                                    >
                                        <Download />
                                        Download
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>

            <Alert>
                <HardDrive />
                <AlertTitle>Keep a copy somewhere else</AlertTitle>
                <AlertDescription>
                    <p>
                        A backup on this computer survives a mistake, but not
                        the computer. Download the newest one now and then, and
                        put it on a USB stick or somewhere online.
                    </p>
                    <p>
                        On this machine they are in{' '}
                        <code className="break-all">{directory}</code>. To put
                        one back, contact support — restoring undoes everything
                        entered since the copy was made.
                    </p>
                </AlertDescription>
            </Alert>
        </>
    );
}

/**
 * A backup is identified by when it was taken, because that is the part
 * somebody can place against "before I deleted it".
 */
function takenOn(row: BackupRow): string {
    return format(new Date(row.taken_at), 'd MMM yyyy, HH:mm');
}

/** Rough, and deliberately so: this answers "does that look right?". */
function fileSize(bytes: number): string {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }

    return `${size < 10 && unit > 0 ? size.toFixed(1) : Math.round(size)} ${units[unit]}`;
}

BackupSettings.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
