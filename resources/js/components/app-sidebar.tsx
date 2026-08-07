import { Link, usePage } from '@inertiajs/react';
import {
    BadgeDollarSign,
    LayoutDashboard,
    Package,
    Receipt,
    ShoppingCart,
    SlidersHorizontal,
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
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import attributes from '@/routes/attributes';
import products from '@/routes/products';
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
            {
                title: 'Attributes',
                href: attributes.index.url(),
                icon: SlidersHorizontal,
            },
        ],
    },
];

/**
 * Destinations still to be built, shown disabled so the shape of the app is
 * visible from day one. Each is removed from here as its phase delivers it.
 */
const upcoming: NavGroup[] = [
    {
        label: 'Trade',
        items: [
            { title: 'Purchases', href: '/purchases', icon: ShoppingCart },
            { title: 'Sales', href: '/sales', icon: Receipt },
            { title: 'Expenses', href: '/expenses', icon: Wallet },
        ],
    },
    {
        label: 'Contacts',
        items: [
            { title: 'Suppliers', href: '/suppliers', icon: Truck },
            { title: 'Customers', href: '/customers', icon: Users },
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

                {upcoming.map((group) => (
                    <SidebarGroup key={group.label}>
                        <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {group.items.map((item) => (
                                    <SidebarMenuItem key={item.href}>
                                        <SidebarMenuButton
                                            disabled
                                            tooltip={`${item.title} — coming soon`}
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
