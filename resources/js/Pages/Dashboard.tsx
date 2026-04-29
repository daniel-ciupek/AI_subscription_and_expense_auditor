import { Upload } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { EmptyState } from '@/Components/UI/EmptyState';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-semibold text-text-primary">
                        Dashboard
                    </h1>
                    <p className="text-sm text-text-secondary mt-1">
                        Your subscriptions and expense overview.
                    </p>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                <Card>
                    <p className="text-sm text-text-secondary">Monthly subscriptions</p>
                    <p className="mt-2 text-3xl font-mono tabular-nums text-text-primary">
                        — PLN
                    </p>
                </Card>
                <Card>
                    <p className="text-sm text-text-secondary">Total transactions</p>
                    <p className="mt-2 text-3xl font-mono tabular-nums text-text-primary">
                        0
                    </p>
                </Card>
                <Card>
                    <p className="text-sm text-text-secondary">Active subscriptions</p>
                    <p className="mt-2 text-3xl font-mono tabular-nums text-text-primary">
                        0
                    </p>
                </Card>
            </div>

            <EmptyState
                icon={Upload}
                title="No transactions yet"
                description="Upload your first bank CSV statement to start tracking subscriptions and expenses with AI."
                action={
                    <Link href={route('imports.create')}>
                        <Button variant="primary">Upload CSV</Button>
                    </Link>
                }
            />
        </AuthenticatedLayout>
    );
}
