import { Upload, ArrowDownRight, ArrowUpRight } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import {
    CategoryBreakdownChart,
    type CategoryBreakdownEntry,
} from '@/Components/Dashboard/CategoryBreakdownChart';
import {
    SpendingOverTimeChart,
    type SpendingPoint,
} from '@/Components/Dashboard/SpendingOverTimeChart';
import {
    TopSubscriptionsWidget,
    type TopSubscriptionEntry,
} from '@/Components/Dashboard/TopSubscriptionsWidget';
import { EmptyState } from '@/Components/UI/EmptyState';
import { Head, Link } from '@inertiajs/react';
import { cn } from '@/lib/cn';

interface Stats {
    transactions: number;
    subscriptions: number;
    monthly_subscriptions_total: number;
}

interface RecentTransaction {
    id: number;
    posted_at: string;
    amount: string;
    currency: string;
    description: string;
    counterparty: string | null;
}

interface DashboardProps {
    stats: Stats;
    recentTransactions: RecentTransaction[];
    categoryBreakdown: CategoryBreakdownEntry[];
    spendingOverTime: SpendingPoint[];
    topSubscriptions: TopSubscriptionEntry[];
}

const formatAmount = (amount: string, currency: string): string => {
    const value = Number.parseFloat(amount);
    const formatted = new Intl.NumberFormat('pl-PL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Math.abs(value));
    const sign = value < 0 ? '-' : '+';
    return `${sign}${formatted} ${currency}`;
};

const formatDate = (iso: string): string => {
    const date = new Date(iso);
    return date.toLocaleDateString('pl-PL', {
        day: '2-digit',
        month: 'short',
    });
};

export default function Dashboard({
    stats,
    recentTransactions,
    categoryBreakdown,
    spendingOverTime,
    topSubscriptions,
}: DashboardProps) {
    const hasTransactions = stats.transactions > 0;
    const hasBreakdown = categoryBreakdown.length > 0;
    const hasSpendingTrend = spendingOverTime.some((point) => point.total > 0);
    const hasTopSubscriptions = topSubscriptions.length > 0;

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
                        {stats.monthly_subscriptions_total > 0
                            ? `${stats.monthly_subscriptions_total.toFixed(2)} PLN`
                            : '— PLN'}
                    </p>
                </Card>
                <Card>
                    <p className="text-sm text-text-secondary">Total transactions</p>
                    <p className="mt-2 text-3xl font-mono tabular-nums text-text-primary">
                        {stats.transactions}
                    </p>
                </Card>
                <Card>
                    <p className="text-sm text-text-secondary">Active subscriptions</p>
                    <p className="mt-2 text-3xl font-mono tabular-nums text-text-primary">
                        {stats.subscriptions}
                    </p>
                </Card>
            </div>

            {hasSpendingTrend && (
                <Card className="mb-6">
                    <div className="flex items-baseline justify-between mb-4">
                        <h2 className="text-lg font-semibold text-text-primary">
                            Spending over time
                        </h2>
                    </div>
                    <SpendingOverTimeChart data={spendingOverTime} />
                </Card>
            )}

            {(hasBreakdown || hasTopSubscriptions) && (
                <div
                    className={cn(
                        'grid gap-4 mb-6',
                        hasBreakdown && hasTopSubscriptions
                            ? 'grid-cols-1 lg:grid-cols-3'
                            : 'grid-cols-1',
                    )}
                >
                    {hasBreakdown && (
                        <Card className={hasTopSubscriptions ? 'lg:col-span-2' : ''}>
                            <div className="flex items-baseline justify-between mb-4">
                                <h2 className="text-lg font-semibold text-text-primary">
                                    Spending by category
                                </h2>
                                <span className="text-xs text-text-secondary font-mono">
                                    {categoryBreakdown.length} categor
                                    {categoryBreakdown.length === 1 ? 'y' : 'ies'}
                                </span>
                            </div>
                            <CategoryBreakdownChart data={categoryBreakdown} />
                        </Card>
                    )}
                    {hasTopSubscriptions && (
                        <Card>
                            <div className="flex items-baseline justify-between mb-4">
                                <h2 className="text-lg font-semibold text-text-primary">
                                    Top subscriptions
                                </h2>
                                <Link
                                    href={route('subscriptions.index')}
                                    className="text-xs text-accent-neon hover:text-accent-neon/80 transition-colors"
                                >
                                    View all
                                </Link>
                            </div>
                            <TopSubscriptionsWidget items={topSubscriptions} />
                        </Card>
                    )}
                </div>
            )}

            {hasTransactions ? (
                <Card>
                    <div className="flex items-baseline justify-between mb-4">
                        <h2 className="text-lg font-semibold text-text-primary">
                            Recent transactions
                        </h2>
                        <span className="text-xs text-text-secondary font-mono">
                            last {recentTransactions.length}
                        </span>
                    </div>
                    <ul className="flex flex-col divide-y divide-white/5">
                        {recentTransactions.map((tx) => {
                            const isExpense = Number.parseFloat(tx.amount) < 0;
                            const Icon = isExpense ? ArrowDownRight : ArrowUpRight;
                            return (
                                <li
                                    key={tx.id}
                                    className="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                                >
                                    <div
                                        className={cn(
                                            'rounded-xl p-2 ring-1',
                                            isExpense
                                                ? 'bg-state-danger/10 ring-state-danger/30 text-state-danger'
                                                : 'bg-state-success/10 ring-state-success/30 text-state-success',
                                        )}
                                    >
                                        <Icon className="h-4 w-4" aria-hidden="true" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm text-text-primary truncate">
                                            {tx.description || tx.counterparty || '(no description)'}
                                        </p>
                                        <p className="text-xs text-text-secondary mt-0.5">
                                            {formatDate(tx.posted_at)}
                                            {tx.counterparty && tx.description ? ` • ${tx.counterparty}` : ''}
                                        </p>
                                    </div>
                                    <p
                                        className={cn(
                                            'text-sm font-mono tabular-nums shrink-0',
                                            isExpense ? 'text-text-primary' : 'text-state-success',
                                        )}
                                    >
                                        {formatAmount(tx.amount, tx.currency)}
                                    </p>
                                </li>
                            );
                        })}
                    </ul>
                </Card>
            ) : (
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
            )}
        </AuthenticatedLayout>
    );
}
