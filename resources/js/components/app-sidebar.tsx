import { Link, usePage } from '@inertiajs/react';
import {
    BadgeDollarSign,
    Boxes,
    ChartLine,
    LayoutDashboard,
    Package,
    Receipt,
    ShoppingCart,
    Truck,
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
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import expenses from '@/routes/expenses';
import products from '@/routes/products';
import purchases from '@/routes/purchases';
import reports from '@/routes/reports';
import sales from '@/routes/sales';
import stock from '@/routes/stock';
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
            { title: 'Stock', href: stock.index.url(), icon: Boxes },
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
            { title: 'Suppliers', href: suppliers.index.url(), icon: Truck },
        ],
    },
    {
        label: 'Analysis',
        items: [
            { title: 'Reports', href: reports.summary.url(), icon: ChartLine },
        ],
    },
];

export function AppSidebar() {
    const { url, props } = usePage();

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
                                    {props.name}
                                </span>
                                <span className="truncate text-xs text-muted-foreground">
                                    Sales
                                </span>
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
