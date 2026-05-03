import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    AlertTriangle,
    Receipt,
    TrendingDown,
    Check,
    GitMerge,
} from 'lucide-react';
import { motion, useReducedMotion } from 'framer-motion';
import { Button } from '@/Components/UI/Button';
import {
    Bar,
    BarChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card } from '@/Components/UI/Card';

interface SubscriptionDetail {
    id: number;
    name: string;
    amount: number;
    currency: string;
    billing_cycle_days: number;
    last_charge_at: string;
    next_expected_charge_at: string | null;
    category: { name: string; slug: string; color: string } | null;
    is_duplicate_of: { id: number; name: string } | null;
    duplicate_resolution: 'confirmed_duplicate' | 'kept_separate' | null;
}

interface Charge {
    id: number;
    posted_at: string;
    amount: number;
    description: string;
    counterparty: string | null;
}

interface Stats {
    charge_count: number;
    total_spent: number;
    avg_per_charge: number;
    lookback_days: number;
}

interface SubscriptionShowProps {
    subscription: SubscriptionDetail;
    monthlyCost: number;
    stats: Stats;
    charges: Charge[];
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

const formatTickDate = (iso: string): string =>
    new Date(iso).toLocaleDateString('pl-PL', {
        day: '2-digit',
        month: 'short',
    });

const cycleLabel = (days: number): string => {
    if (days >= 28 && days <= 32) return 'Monthly';
    if (days >= 6 && days <= 8) return 'Weekly';
    if (days >= 13 && days <= 15) return 'Biweekly';
    if (days >= 88 && days <= 95) return 'Quarterly';
    if (days >= 360 && days <= 370) return 'Yearly';
    return `Every ${days} days`;
};

interface ChartTooltipProps {
    active?: boolean;
    payload?: Array<{ payload: { posted_at: string; amount: number } }>;
}

const ChartTooltip = ({ active, payload }: ChartTooltipProps) => {
    if (!active || !payload || payload.length === 0) return null;
    const point = payload[0].payload;
    return (
        <div className="glass-elevated rounded-xl px-3 py-2 text-xs">
            <p className="font-medium text-text-primary">
                {formatTickDate(point.posted_at)}
            </p>
            <p className="font-mono tabular-nums text-text-secondary mt-0.5">
                {formatPln(point.amount)} PLN
            </p>
        </div>
    );
};

export default function SubscriptionShow({
    subscription,
    monthlyCost,
    stats,
    charges,
}: SubscriptionShowProps) {
    const reduce = useReducedMotion();
    const chartData = [...charges].reverse();

    const listVariants = reduce
        ? undefined
        : {
              hidden: { opacity: 0 },
              show: {
                  opacity: 1,
                  transition: { staggerChildren: 0.04, delayChildren: 0.05 },
              },
          };
    const itemVariants = reduce
        ? undefined
        : {
              hidden: { opacity: 0, x: -8 },
              show: {
                  opacity: 1,
                  x: 0,
                  transition: { duration: 0.2, ease: 'easeOut' as const },
              },
          };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3">
                    <Link
                        href={route('subscriptions.index')}
                        className="inline-flex items-center gap-1 text-xs text-text-secondary hover:text-text-primary transition-colors w-fit rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-base"
                    >
                        <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" />
                        Back to subscriptions
                    </Link>
                    <div className="flex items-start justify-between gap-4 flex-wrap">
                        <div className="min-w-0">
                            <h1 className="text-2xl font-semibold text-text-primary truncate">
                                {subscription.name}
                            </h1>
                            <p className="text-sm text-text-secondary mt-1 font-mono">
                                {cycleLabel(subscription.billing_cycle_days)} ·{' '}
                                {formatPln(subscription.amount)} {subscription.currency}
                            </p>
                        </div>
                        {subscription.category && (
                            <span
                                className="shrink-0 inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1"
                                style={{
                                    color: subscription.category.color,
                                    backgroundColor: `${subscription.category.color}1A`,
                                    borderColor: `${subscription.category.color}55`,
                                }}
                            >
                                {subscription.category.name}
                            </span>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={subscription.name} />

            {subscription.is_duplicate_of && (
                <Card className="mb-6 ring-1 ring-state-warning/30 bg-state-warning/5">
                    <div className="flex items-start gap-3">
                        <div className="rounded-xl bg-state-warning/10 p-2 ring-1 ring-state-warning/30 shrink-0">
                            <AlertTriangle
                                className="h-5 w-5 text-state-warning"
                                aria-hidden="true"
                            />
                        </div>
                        <div className="flex-1 min-w-0">
                            <h3 className="text-sm font-semibold text-text-primary">
                                Possible duplicate
                            </h3>
                            <p className="text-xs text-text-secondary mt-1">
                                This subscription looks like a duplicate of{' '}
                                <Link
                                    href={route(
                                        'subscriptions.show',
                                        subscription.is_duplicate_of.id,
                                    )}
                                    className="text-state-warning hover:underline rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-state-warning focus-visible:ring-offset-2 focus-visible:ring-offset-bg-surface"
                                >
                                    {subscription.is_duplicate_of.name}
                                </Link>
                                . Same billing cadence and similar amount, possibly
                                the same merchant under a different statement name.
                            </p>
                            {subscription.duplicate_resolution ===
                                'confirmed_duplicate' ? (
                                <p className="mt-3 text-xs inline-flex items-center gap-1.5 text-state-warning">
                                    <Check
                                        className="h-3.5 w-3.5"
                                        aria-hidden="true"
                                    />
                                    Marked as the same merchant. Detection will
                                    keep this flag.
                                </p>
                            ) : (
                                <div className="mt-4 flex flex-col sm:flex-row gap-2">
                                    <Button
                                        variant="primary"
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                route(
                                                    'subscriptions.confirm-duplicate',
                                                    subscription.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <GitMerge
                                            className="h-3.5 w-3.5"
                                            aria-hidden="true"
                                        />
                                        Mark as same
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            router.post(
                                                route(
                                                    'subscriptions.keep-separate',
                                                    subscription.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Keep separate
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>
                </Card>
            )}

            {subscription.duplicate_resolution === 'kept_separate' && (
                <Card className="mb-6 ring-1 ring-state-success/30 bg-state-success/5">
                    <div className="flex items-start gap-3">
                        <div className="rounded-xl bg-state-success/10 p-2 ring-1 ring-state-success/30 shrink-0">
                            <Check
                                className="h-5 w-5 text-state-success"
                                aria-hidden="true"
                            />
                        </div>
                        <div className="flex-1">
                            <h3 className="text-sm font-semibold text-text-primary">
                                Kept as a separate subscription
                            </h3>
                            <p className="text-xs text-text-secondary mt-1">
                                You marked this subscription as distinct from any
                                near-matches. Detection will leave it alone on
                                future runs.
                            </p>
                        </div>
                    </div>
                </Card>
            )}

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                <Card>
                    <p className="text-xs text-text-secondary">Monthly cost</p>
                    <p className="mt-2 text-2xl font-mono tabular-nums text-text-primary">
                        {formatPln(monthlyCost)}
                    </p>
                    <p className="text-[10px] text-text-secondary mt-1 uppercase tracking-wider">
                        {subscription.currency}
                    </p>
                </Card>
                <Card>
                    <p className="text-xs text-text-secondary">Total spent</p>
                    <p className="mt-2 text-2xl font-mono tabular-nums text-text-primary">
                        {formatPln(stats.total_spent)}
                    </p>
                    <p className="text-[10px] text-text-secondary mt-1 uppercase tracking-wider">
                        last {Math.round(stats.lookback_days / 30)} months
                    </p>
                </Card>
                <Card>
                    <p className="text-xs text-text-secondary">Charges</p>
                    <p className="mt-2 text-2xl font-mono tabular-nums text-text-primary">
                        {stats.charge_count}
                    </p>
                    <p className="text-[10px] text-text-secondary mt-1 uppercase tracking-wider">
                        recorded
                    </p>
                </Card>
                <Card>
                    <p className="text-xs text-text-secondary">Avg per charge</p>
                    <p className="mt-2 text-2xl font-mono tabular-nums text-text-primary">
                        {formatPln(stats.avg_per_charge)}
                    </p>
                    <p className="text-[10px] text-text-secondary mt-1 uppercase tracking-wider">
                        {subscription.currency}
                    </p>
                </Card>
            </div>

            <Card className="mb-6">
                <div className="flex items-baseline justify-between mb-1 gap-3 flex-wrap">
                    <h2 className="text-lg font-semibold text-text-primary">
                        Charge history
                    </h2>
                    <p className="text-xs text-text-secondary font-mono">
                        {stats.charge_count} charge{stats.charge_count === 1 ? '' : 's'}
                    </p>
                </div>
                <p className="text-xs text-text-secondary mb-4 flex items-center gap-x-2 gap-y-1 flex-wrap">
                    <CalendarClock className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                    <span>Last charged {formatDate(subscription.last_charge_at)}</span>
                    {subscription.next_expected_charge_at && (
                        <>
                            <span className="text-text-secondary/50">·</span>
                            <span>
                                Next expected{' '}
                                {formatDate(subscription.next_expected_charge_at)}
                            </span>
                        </>
                    )}
                </p>

                {chartData.length > 0 ? (
                    <div className="-mx-2">
                        <ResponsiveContainer width="100%" height={224}>
                            <BarChart
                                data={chartData}
                                margin={{ top: 8, right: 8, bottom: 0, left: 8 }}
                            >
                                <defs>
                                    <linearGradient
                                        id="chargesGradient"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="0%"
                                            stopColor="#22D3EE"
                                            stopOpacity={0.9}
                                        />
                                        <stop
                                            offset="100%"
                                            stopColor="#7C3AED"
                                            stopOpacity={0.6}
                                        />
                                    </linearGradient>
                                </defs>
                                <XAxis
                                    dataKey="posted_at"
                                    tickFormatter={formatTickDate}
                                    tick={{ fill: '#71717A', fontSize: 11 }}
                                    axisLine={false}
                                    tickLine={false}
                                    minTickGap={20}
                                />
                                <YAxis
                                    tickFormatter={(value: number) => formatPln(value)}
                                    tick={{ fill: '#71717A', fontSize: 11 }}
                                    axisLine={false}
                                    tickLine={false}
                                    width={64}
                                />
                                <Tooltip
                                    content={<ChartTooltip />}
                                    cursor={{ fill: 'rgba(34, 211, 238, 0.08)' }}
                                />
                                <Bar
                                    dataKey="amount"
                                    fill="url(#chargesGradient)"
                                    radius={[6, 6, 0, 0]}
                                    isAnimationActive={false}
                                />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                ) : (
                    <p className="text-sm text-text-secondary py-6 text-center">
                        No matching charges in the last{' '}
                        {Math.round(stats.lookback_days / 30)} months.
                    </p>
                )}
            </Card>

            {charges.length > 0 && (
                <Card>
                    <div className="flex items-center gap-2 mb-4">
                        <Receipt
                            className="h-4 w-4 text-text-secondary"
                            aria-hidden="true"
                        />
                        <h2 className="text-base font-semibold text-text-primary">
                            All charges
                        </h2>
                    </div>
                    <motion.ul
                        className="flex flex-col divide-y divide-white/5"
                        variants={listVariants}
                        initial={reduce ? false : 'hidden'}
                        animate="show"
                    >
                        {charges.map((charge) => (
                            <motion.li
                                key={charge.id}
                                variants={itemVariants}
                                className="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <div className="rounded-xl p-2 ring-1 bg-state-danger/10 ring-state-danger/30 text-state-danger shrink-0">
                                    <TrendingDown
                                        className="h-4 w-4"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm text-text-primary truncate">
                                        {charge.description}
                                    </p>
                                    <p className="text-xs text-text-secondary mt-0.5">
                                        {formatDate(charge.posted_at)}
                                        {charge.counterparty && (
                                            <> · {charge.counterparty}</>
                                        )}
                                    </p>
                                </div>
                                <p className="text-sm font-mono tabular-nums text-text-primary shrink-0">
                                    {formatPln(charge.amount)} {subscription.currency}
                                </p>
                            </motion.li>
                        ))}
                    </motion.ul>
                </Card>
            )}
        </AuthenticatedLayout>
    );
}
