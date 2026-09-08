import { Link, usePage } from '@inertiajs/react';
import {
    BadgeDollarSign,
    ChartLine,
    Coins,
    HandCoins,
    Landmark,
    LayoutDashboard,
    Package,
    Receipt,
    RefreshCw,
    ShoppingCart,
    Truck,
    Users,
    Wallet,
} from 'lucide-react';

import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import customers from '@/routes/customers';
import expenses from '@/routes/expenses';
import loans from '@/routes/loans';
import products from '@/routes/products';
import purchases from '@/routes/purchases';
import reports from '@/routes/reports';
import sales from '@/routes/sales';
import banks from '@/routes/settings/banks';
import exchangeRates from '@/routes/settings/exchange-rates';
import update from '@/routes/settings/update';
import suppliers from '@/routes/suppliers';
import type { NavGroup } from '@/types';

/**
 * Sections light up as their phases land. Every destination here already has a
 * route; nothing is linked before it exists.
 */
const navigation: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: dashboard.url(),
                icon: LayoutDashboard,
            },
        ],
    },
    {
        label: 'Catalogue',
        items: [
            { title: 'Products', href: products.index.url(), icon: Package },
        ],
    },
    {
        label: 'Trade',
        items: [
            {
                title: 'Purchases',
                href: purchases.index.url(),
                icon: ShoppingCart,
            },
            { title: 'Sales', href: sales.index.url(), icon: Receipt },
            { title: 'Expenses', href: expenses.index.url(), icon: Wallet },
        ],
    },
    {
        label: 'Contacts',
        items: [
            { title: 'Customers', href: customers.index.url(), icon: Users },
            { title: 'Suppliers', href: suppliers.index.url(), icon: Truck },
        ],
    },
    {
        label: 'Analysis',
        items: [
            { title: 'Reports', href: reports.index.url(), icon: ChartLine },
            // A position rather than a period: what is owed each way today.
            { title: 'Loans', href: loans.index.url(), icon: HandCoins },
        ],
    },
    {
        label: 'Settings',
        items: [
            {
                title: 'Exchange rates',
                href: exchangeRates.index.url(),
                icon: Coins,
            },
            { title: 'Banks', href: banks.index.url(), icon: Landmark },
            // Last, because it is the one thing here that is about the
            // software rather than the business.
            { title: 'New updates', href: update.index.url(), icon: RefreshCw },
        ],
    },
];

export function AppSidebar() {
    const { url, props } = usePage();

    // The one nav entry that can carry news. A dot rather than a count: there
    // is only ever one answer, and it has to stay visible when the sidebar is
    // collapsed to icons, which `SidebarMenuBadge` otherwise hides.
    const updateWaiting = props.update?.available ?? false;
    const updateHref = update.index.url();

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            render={<Link href={dashboard()} />}
                        >
                            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                                <BadgeDollarSign className="size-4" />
                            </div>
                            <div className="grid flex-1 text-left leading-tight">
                                <span className="truncate font-medium">
                                    CMS
                                </span>
                                {/*<span className="truncate text-xs text-muted-foreground">*/}
                                {/*    Sales*/}
                                {/*</span>*/}
                            </div>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {navigation.map((group) => (
                    <SidebarGroup key={group.label}>
                        <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {group.items.map((item) => (
                                    <SidebarMenuItem key={item.href}>
                                        <SidebarMenuButton
                                            isActive={
                                                url === item.href ||
                                                url.startsWith(`${item.href}/`)
                                            }
                                            tooltip={item.title}
                                            render={
                                                <Link
                                                    href={item.href}
                                                    prefetch
                                                />
                                            }
                                        >
                                            <item.icon />
                                            <span>{item.title}</span>
                                        </SidebarMenuButton>

                                        {item.href === updateHref &&
                                            updateWaiting && (
                                                <SidebarMenuBadge className="top-1.5 right-1.5 size-2 min-w-0 rounded-full bg-primary p-0 group-data-[collapsible=icon]:flex">
                                                    <span className="sr-only">
                                                        An update is ready to
                                                        install
                                                    </span>
                                                </SidebarMenuBadge>
                                            )}
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarRail />
        </Sidebar>
    );
}
