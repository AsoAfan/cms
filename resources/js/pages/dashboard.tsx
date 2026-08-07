import { Head } from '@inertiajs/react';
import { LineChart } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard' }];

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />

            <PageHeader
                title="Dashboard"
                description="Sales, purchases and profit at a glance."
            />

            <Card>
                <CardContent>
                    <EmptyState
                        icon={LineChart}
                        title="No figures yet"
                        description="KPI tiles, the trend chart and recent activity appear here once there are transactions to derive them from."
                    />
                </CardContent>
            </Card>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
