import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export type PageHeaderProps = {
    title: string;
    description?: string;
    /** Primary actions for the page, e.g. a "New purchase" button. */
    actions?: ReactNode;
    className?: string;
};

/**
 * The title block at the top of every screen.
 */
export function PageHeader({
    title,
    description,
    actions,
    className,
}: PageHeaderProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="grid gap-1">
                <h1 className="text-2xl font-semibold tracking-tight">
                    {title}
                </h1>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
