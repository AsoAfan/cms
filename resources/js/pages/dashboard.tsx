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
                description="Sales, purchases and profit."
            />

            <Card>
                <CardContent>
                    <EmptyState
                        icon={LineChart}
                        title="Nothing to show yet"
                        description="Figures appear once you record purchases and sales."
                    />
                </CardContent>
            </Card>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
