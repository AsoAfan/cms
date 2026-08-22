import { Link, usePage } from '@inertiajs/react';
import { BadgeDollarSign } from 'lucide-react';
import type { ReactNode } from 'react';

import { AppearanceToggle } from '@/components/appearance-toggle';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

export type AuthLayoutProps = {
    children: ReactNode;
    title: string;
    description: string;
};

/**
 * The signed-out shell: a single centred card, no navigation to speak of.
 */
export default function AuthLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const name = usePage().props.name;

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-muted/40 p-6">
            <div className="absolute top-4 right-4">
                <AppearanceToggle />
            </div>

            <Link href={home()} className="flex items-center gap-2 font-medium">
                <div className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                    <BadgeDollarSign className="size-4" />
                </div>
                {name}
            </Link>

            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent>{children}</CardContent>
            </Card>
        </div>
    );
}
