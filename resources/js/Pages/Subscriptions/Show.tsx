import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import {
    ArrowLeft,
    CalendarClock,
    AlertTriangle,
    Receipt,
    TrendingDown,
    Check,
    GitMerge,
    Pencil,
    Trash2,
    AlertCircle,
} from 'lucide-react';
import { motion, useReducedMotion } from 'framer-motion';
import { Button } from '@/Components/UI/Button';
import { Modal } from '@/Components/UI/Modal';
import { FormField } from '@/Components/UI/FormField';
import { Input } from '@/Components/UI/Input';
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
    category_id: number | null;
    category: { name: string; slug: string; color: string } | null;
    is_duplicate_of: { id: number; name: string } | null;
    duplicate_resolution: 'confirmed_duplicate' | 'kept_separate' | null;
}

interface CategoryOption {
    id: number;
    name: string;
    slug: string;
    color: string;
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
    categories: CategoryOption[];
    monthlyCost: number;
    stats: Stats;
    charges: Charge[];
}

const cyclePresets: Array<{ label: string; days: number }> = [
    { label: 'Weekly', days: 7 },
    { label: 'Biweekly', days: 14 },
    { label: 'Monthly', days: 30 },
    { label: 'Quarterly', days: 90 },
    { label: 'Yearly', days: 365 },
];

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
    if (days >= 6 && days <= 8) return 'Weekly';
    if (days >= 13 && days <= 15) return 'Biweekly';
    if (days >= 25 && days <= 35) return 'Monthly';
    if (days >= 85 && days <= 95) return 'Quarterly';
    if (days >= 350 && days <= 380) return 'Yearly';
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
    categories,
    monthlyCost,
    stats,
    charges,
}: SubscriptionShowProps) {
    const reduce = useReducedMotion();
    const chartData = [...charges].reverse();

    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const editForm = useForm({
        name: subscription.name,
        amount: subscription.amount.toFixed(2),
        currency: subscription.currency,
        billing_cycle_days: String(subscription.billing_cycle_days),
        last_charge_at: subscription.last_charge_at,
        category_id: subscription.category_id !== null
            ? String(subscription.category_id)
            : '',
    });

    const handleEditSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        editForm.transform((data) => ({
            ...data,
            amount: data.amount,
            billing_cycle_days: Number(data.billing_cycle_days),
            category_id: data.category_id === '' ? null : Number(data.category_id),
        }));
        editForm.patch(route('subscriptions.update', subscription.id), {
            preserveScroll: true,
            onSuccess: () => {
                setEditOpen(false);
                editForm.reset();
            },
        });
    };

    const handleDelete = () => {
        router.delete(route('subscriptions.destroy', subscription.id));
    };

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
                        <div className="shrink-0 flex items-center gap-2 flex-wrap">
                            {subscription.category && (
                                <span
                                    className="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1"
                                    style={{
                                        color: subscription.category.color,
                                        backgroundColor: `${subscription.category.color}1A`,
                                        borderColor: `${subscription.category.color}55`,
                                    }}
                                >
                                    {subscription.category.name}
                                </span>
                            )}
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setEditOpen(true)}
                            >
                                <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                                Edit
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setDeleteOpen(true)}
                                className="text-state-danger hover:text-state-danger hover:bg-state-danger/10"
                            >
                                <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                                Delete
                            </Button>
                        </div>
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

            <Modal
                open={editOpen}
                onClose={() => setEditOpen(false)}
                title="Edit subscription"
                description="Adjust the merchant, amount, or billing cadence."
                maxWidth="lg"
            >
                <form onSubmit={handleEditSubmit} className="flex flex-col gap-4">
                    <FormField
                        label="Name"
                        required
                        error={editForm.errors.name}
                    >
                        {(id) => (
                            <Input
                                id={id}
                                value={editForm.data.name}
                                onChange={(e) =>
                                    editForm.setData('name', e.target.value)
                                }
                                error={Boolean(editForm.errors.name)}
                                autoFocus
                            />
                        )}
                    </FormField>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <FormField
                            label="Amount"
                            required
                            error={editForm.errors.amount}
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    inputMode="decimal"
                                    value={editForm.data.amount}
                                    onChange={(e) =>
                                        editForm.setData('amount', e.target.value)
                                    }
                                    error={Boolean(editForm.errors.amount)}
                                    className="font-mono tabular-nums"
                                />
                            )}
                        </FormField>

                        <FormField
                            label="Currency"
                            required
                            error={editForm.errors.currency}
                            helperText="3-letter ISO code (PLN, EUR, USD…)"
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    value={editForm.data.currency}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'currency',
                                            e.target.value.toUpperCase().slice(0, 3),
                                        )
                                    }
                                    maxLength={3}
                                    error={Boolean(editForm.errors.currency)}
                                    className="font-mono uppercase"
                                />
                            )}
                        </FormField>
                    </div>

                    <FormField
                        label="Billing cycle (days)"
                        required
                        error={editForm.errors.billing_cycle_days}
                    >
                        {(id) => (
                            <div className="flex flex-col gap-2">
                                <Input
                                    id={id}
                                    type="number"
                                    min="1"
                                    max="730"
                                    value={editForm.data.billing_cycle_days}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'billing_cycle_days',
                                            e.target.value,
                                        )
                                    }
                                    error={Boolean(
                                        editForm.errors.billing_cycle_days,
                                    )}
                                    className="font-mono tabular-nums"
                                />
                                <div className="flex flex-wrap gap-1.5">
                                    {cyclePresets.map((preset) => {
                                        const active =
                                            Number(editForm.data.billing_cycle_days) ===
                                            preset.days;
                                        return (
                                            <button
                                                key={preset.days}
                                                type="button"
                                                onClick={() =>
                                                    editForm.setData(
                                                        'billing_cycle_days',
                                                        String(preset.days),
                                                    )
                                                }
                                                className={`text-xs px-2.5 py-1 rounded-full ring-1 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-elevated ${
                                                    active
                                                        ? 'bg-accent-primary/20 text-accent-neon ring-accent-neon/40'
                                                        : 'text-text-secondary ring-white/10 hover:bg-white/5 hover:text-text-primary'
                                                }`}
                                            >
                                                {preset.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </FormField>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <FormField
                            label="Last charged"
                            required
                            error={editForm.errors.last_charge_at}
                        >
                            {(id) => (
                                <Input
                                    id={id}
                                    type="date"
                                    value={editForm.data.last_charge_at}
                                    max={new Date().toISOString().slice(0, 10)}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'last_charge_at',
                                            e.target.value,
                                        )
                                    }
                                    error={Boolean(editForm.errors.last_charge_at)}
                                />
                            )}
                        </FormField>

                        <FormField
                            label="Category"
                            error={editForm.errors.category_id}
                        >
                            {(id) => (
                                <select
                                    id={id}
                                    value={editForm.data.category_id}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'category_id',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-2xl px-4 text-sm bg-bg-surface border border-white/10 text-text-primary transition-colors duration-200 focus:outline-none focus:border-accent-neon/50 focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-base"
                                >
                                    <option value="">Uncategorized</option>
                                    {categories.map((cat) => (
                                        <option key={cat.id} value={cat.id}>
                                            {cat.name}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </FormField>
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setEditOpen(false)}
                            disabled={editForm.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            loading={editForm.processing}
                        >
                            Save changes
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal
                open={deleteOpen}
                onClose={() => setDeleteOpen(false)}
                title="Delete subscription?"
                maxWidth="sm"
            >
                <div className="flex items-start gap-3 mb-5">
                    <div className="rounded-xl bg-state-danger/10 p-2 ring-1 ring-state-danger/30 shrink-0">
                        <AlertCircle
                            className="h-5 w-5 text-state-danger"
                            aria-hidden="true"
                        />
                    </div>
                    <p className="text-sm text-text-secondary">
                        This removes <span className="text-text-primary font-medium">{subscription.name}</span> from
                        your subscription list. The matching transactions stay
                        intact. Detection can re-create it on the next run.
                    </p>
                </div>
                <div className="flex items-center justify-end gap-2">
                    <Button
                        variant="ghost"
                        onClick={() => setDeleteOpen(false)}
                    >
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleDelete}>
                        <Trash2 className="h-4 w-4" aria-hidden="true" />
                        Delete
                    </Button>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
