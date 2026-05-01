import { Head, Link, useForm } from '@inertiajs/react';
import { Repeat, Upload, CalendarClock, AlertTriangle, RefreshCw } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { EmptyState } from '@/Components/UI/EmptyState';
import { cn } from '@/lib/cn';

interface SubscriptionRow {
    id: number;
    name: string;
    amount: number;
    currency: string;
    billing_cycle_days: number;
    last_charge_at: string;
    next_expected_charge_at: string | null;
    category: {
        name: string;
        slug: string;
        color: string;
    } | null;
    is_duplicate_of_id: number | null;
    duplicate_of_name: string | null;
}

interface SubscriptionsIndexProps {
    subscriptions: SubscriptionRow[];
    monthlyTotal: number;
    duplicateCount: number;
    transactionsCount: number;
}

const formatPln = (value: number): string =>
    new Intl.NumberFormat('pl-PL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);

const formatDate = (iso: string): string =>
    new Date(iso).toLocaleDateString('pl-PL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

const cycleLabel = (days: number): string => {
    if (days >= 28 && days <= 32) return 'Monthly';
    if (days >= 6 && days <= 8) return 'Weekly';
    if (days >= 13 && days <= 15) return 'Biweekly';
    if (days >= 88 && days <= 95) return 'Quarterly';
    if (days >= 360 && days <= 370) return 'Yearly';
    return `Every ${days} days`;
};

export default function SubscriptionsIndex({
    subscriptions,
    monthlyTotal,
    duplicateCount,
    transactionsCount,
}: SubscriptionsIndexProps) {
    const detectForm = useForm({});
    const runDetection = () => {
        if (detectForm.processing) {
            return;
        }
        detectForm.post(route('subscriptions.detect'), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-text-primary">
                            Subscriptions
                        </h1>
                        <p className="text-sm text-text-secondary mt-1">
                            Recurring charges detected from your imports.
                        </p>
                    </div>
                    {subscriptions.length > 0 && (
                        <Button
                            variant="ghost"
                            onClick={runDetection}
                            loading={detectForm.processing}
                            disabled={detectForm.processing}
                        >
                            {!detectForm.processing && (
                                <RefreshCw className="h-4 w-4" aria-hidden="true" />
                            )}
                            Re-run detection
                        </Button>
                    )}
                </div>
            }
        >
            <Head title="Subscriptions" />

            {subscriptions.length === 0 ? (
                transactionsCount === 0 ? (
                    <EmptyState
                        icon={Repeat}
                        title="No subscriptions detected yet"
                        description="Once you import a few months of bank statements, recurring charges with consistent amounts will appear here automatically."
                        action={
                            <Link href={route('imports.create')}>
                                <Button variant="primary">
                                    <Upload className="h-4 w-4" aria-hidden="true" />
                                    Upload CSV
                                </Button>
                            </Link>
                        }
                    />
                ) : (
                    <EmptyState
                        icon={Repeat}
                        title="No recurring charges found in your transactions"
                        description={`We've analyzed your ${transactionsCount} transactions but haven't found any subscriptions yet. Detection needs at least two charges from the same merchant 25–35 days apart with consistent amounts — usually that means ~2 months of history.`}
                        action={
                            <div className="flex flex-wrap gap-2 justify-center">
                                <Button
                                    variant="primary"
                                    onClick={runDetection}
                                    loading={detectForm.processing}
                                    disabled={detectForm.processing}
                                >
                                    {!detectForm.processing && (
                                        <RefreshCw className="h-4 w-4" aria-hidden="true" />
                                    )}
                                    Run detection now
                                </Button>
                                <Link href={route('imports.create')}>
                                    <Button variant="ghost">
                                        <Upload className="h-4 w-4" aria-hidden="true" />
                                        Upload more CSVs
                                    </Button>
                                </Link>
                            </div>
                        }
                    />
                )
            ) : (
                <>
                    <Card className="mb-6">
                        <div className="flex items-baseline justify-between">
                            <p className="text-sm text-text-secondary">
                                Estimated monthly cost
                            </p>
                            <p className="text-xs text-text-secondary font-mono">
                                {subscriptions.length} subscription
                                {subscriptions.length === 1 ? '' : 's'}
                            </p>
                        </div>
                        <p className="mt-2 text-3xl font-mono tabular-nums text-text-primary">
                            {formatPln(monthlyTotal)} PLN
                        </p>
                    </Card>

                    {duplicateCount > 0 && (
                        <Card className="mb-6 ring-1 ring-state-warning/30 bg-state-warning/5">
                            <div className="flex items-start gap-3">
                                <div className="rounded-xl bg-state-warning/10 p-2 ring-1 ring-state-warning/30 shrink-0">
                                    <AlertTriangle
                                        className="h-5 w-5 text-state-warning"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div className="flex-1">
                                    <h3 className="text-sm font-semibold text-text-primary">
                                        {duplicateCount} possible duplicate
                                        {duplicateCount === 1 ? '' : 's'} detected
                                    </h3>
                                    <p className="text-xs text-text-secondary mt-1">
                                        We found subscriptions billed at the same cadence
                                        with similar amounts. They might be the same
                                        merchant under different statement names.
                                    </p>
                                </div>
                            </div>
                        </Card>
                    )}

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {subscriptions.map((sub) => {
                            const isDuplicate = sub.is_duplicate_of_id !== null;
                            return (
                                <Card
                                    key={sub.id}
                                    hoverable
                                    className={cn(
                                        isDuplicate &&
                                            'ring-1 ring-state-warning/30 bg-state-warning/5',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex-1 min-w-0">
                                            <h3 className="text-base font-semibold text-text-primary truncate">
                                                {sub.name}
                                            </h3>
                                            <p className="text-xs text-text-secondary mt-0.5 font-mono">
                                                {cycleLabel(sub.billing_cycle_days)}
                                            </p>
                                        </div>
                                        {sub.category && (
                                            <span
                                                className="shrink-0 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1"
                                                style={{
                                                    color: sub.category.color,
                                                    backgroundColor: `${sub.category.color}1A`,
                                                    borderColor: `${sub.category.color}55`,
                                                }}
                                            >
                                                {sub.category.name}
                                            </span>
                                        )}
                                    </div>

                                    <p className="mt-4 text-2xl font-mono tabular-nums text-text-primary">
                                        {formatPln(sub.amount)}{' '}
                                        <span className="text-sm text-text-secondary">
                                            {sub.currency}
                                        </span>
                                    </p>

                                    {isDuplicate && sub.duplicate_of_name && (
                                        <div className="mt-3 flex items-center gap-2 text-xs text-state-warning">
                                            <AlertTriangle
                                                className="h-3.5 w-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            <span className="truncate">
                                                Possible duplicate of{' '}
                                                <span className="font-medium">
                                                    {sub.duplicate_of_name}
                                                </span>
                                            </span>
                                        </div>
                                    )}

                                    <div className="mt-4 pt-4 border-t border-white/5 flex items-center gap-2 text-xs text-text-secondary">
                                        <CalendarClock
                                            className="h-3.5 w-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span>
                                            Last charge {formatDate(sub.last_charge_at)}
                                            {sub.next_expected_charge_at && (
                                                <>
                                                    {' '}
                                                    · next expected{' '}
                                                    {formatDate(
                                                        sub.next_expected_charge_at,
                                                    )}
                                                </>
                                            )}
                                        </span>
                                    </div>
                                </Card>
                            );
                        })}
                    </div>
                </>
            )}
        </AuthenticatedLayout>
    );
}
