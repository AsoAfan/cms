import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';

export type EmptyStateProps = {
    icon?: LucideIcon;
    title: string;
    description?: string;
    /** The action that resolves the emptiness — usually "create the first one". */
    action?: ReactNode;
    className?: string;
};

/**
 * Shown wherever a list has nothing in it, including after a search that
 * matched nothing.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: EmptyStateProps) {
    return (
        <Empty className={className}>
            <EmptyHeader>
                {Icon && (
                    <EmptyMedia variant="icon">
                        <Icon />
                    </EmptyMedia>
                )}
                <EmptyTitle>{title}</EmptyTitle>
                {description && (
                    <EmptyDescription>{description}</EmptyDescription>
                )}
            </EmptyHeader>
            {action && <EmptyContent>{action}</EmptyContent>}
        </Empty>
    );
}
