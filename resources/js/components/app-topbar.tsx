import { Link, router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { Fragment } from 'react';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { logout } from '@/routes';
import type { BreadcrumbItem as Crumb } from '@/types';

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}

export function AppTopbar({ breadcrumbs = [] }: { breadcrumbs?: Crumb[] }) {
    const user = usePage().props.auth.user;

    return (
        <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-2 border-b bg-background/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <SidebarTrigger className="-ml-1" />
            <Separator orientation="vertical" className="mr-1 h-4" />

            <Breadcrumb className="min-w-0 flex-1">
                <BreadcrumbList>
                    {breadcrumbs.map((crumb, index) => {
                        const isLast = index === breadcrumbs.length - 1;

                        return (
                            <Fragment key={`${crumb.title}-${index}`}>
                                <BreadcrumbItem
                                    className={isLast ? '' : 'hidden sm:block'}
                                >
                                    {isLast || !crumb.href ? (
                                        <BreadcrumbPage className="truncate">
                                            {crumb.title}
                                        </BreadcrumbPage>
                                    ) : (
                                        <BreadcrumbLink
                                            render={<Link href={crumb.href} />}
                                        >
                                            {crumb.title}
                                        </BreadcrumbLink>
                                    )}
                                </BreadcrumbItem>
                                {!isLast && (
                                    <BreadcrumbSeparator className="hidden sm:block" />
                                )}
                            </Fragment>
                        );
                    })}
                </BreadcrumbList>
            </Breadcrumb>

            {user && (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={
                            <Button
                                variant="ghost"
                                size="icon"
                                className="rounded-full"
                                aria-label="Account menu"
                            />
                        }
                    >
                        <Avatar className="size-8">
                            <AvatarFallback>
                                {initials(user.name)}
                            </AvatarFallback>
                        </Avatar>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel>
                            <div className="grid leading-tight">
                                <span className="truncate font-medium">
                                    {user.name}
                                </span>
                                <span className="truncate text-xs font-normal text-muted-foreground">
                                    {user.email}
                                </span>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuGroup>
                            <DropdownMenuItem
                                onClick={() => router.post(logout.url())}
                            >
                                <LogOut />
                                Log out
                            </DropdownMenuItem>
                        </DropdownMenuGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </header>
    );
}
