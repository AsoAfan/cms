import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { AppSidebar } from '@/components/app-sidebar';
import { AppTopbar } from '@/components/app-topbar';
import { FlashToaster } from '@/components/flash-toaster';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/toast';
import { TooltipProvider } from '@/components/ui/tooltip';
import type { BreadcrumbItem } from '@/types';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

/**
 * The authenticated shell: collapsible sidebar, sticky topbar with
 * breadcrumbs, and the toast outlet.
 *
 * Applied as a persistent layout (`Page.layout = ...`), so it survives
 * navigation and keeps the sidebar's open state and scroll position.
 */
export default function AppLayout({ children, breadcrumbs }: AppLayoutProps) {
    const sidebarOpen = usePage().props.sidebarOpen;

    return (
        <TooltipProvider>
            <Toaster>
                <SidebarProvider defaultOpen={sidebarOpen}>
                    <AppSidebar />
                    <SidebarInset>
                        <AppTopbar breadcrumbs={breadcrumbs} />
                        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                            {children}
                        </div>
                    </SidebarInset>
                </SidebarProvider>
                <FlashToaster />
            </Toaster>
        </TooltipProvider>
    );
}
