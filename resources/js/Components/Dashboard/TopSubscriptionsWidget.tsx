import { Repeat } from 'lucide-react';

export interface TopSubscriptionEntry {
    id: number;
    name: string;
    monthly_cost: number;
    currency: string;
    billing_cycle_days: number;
    category: {
        name: string;
        slug: string;
        color: string;
    } | null;
}

interface TopSubscriptionsWidgetProps {
    items: TopSubscriptionEntry[];
}

const formatPln = (value: number): string =>
    new Intl.NumberFormat('pl-PL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);

const cycleHint = (days: number): string => {
    if (days >= 28 && days <= 32) return '/mo';
    if (days >= 6 && days <= 8) return '/wk';
    if (days >= 13 && days <= 15) return '/2wk';
    if (days >= 88 && days <= 95) return '/qtr';
    if (days >= 360 && days <= 370) return '/yr';
    return `/${days}d`;
};

export const TopSubscriptionsWidget = ({ items }: TopSubscriptionsWidgetProps) => {
    if (items.length === 0) {
        return null;
    }

    return (
        <ul className="flex flex-col divide-y divide-white/5">
            {items.map((sub) => (
                <li
                    key={sub.id}
                    className="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                >
                    <div
                        className="rounded-xl p-2 ring-1 shrink-0"
                        style={{
                            color: sub.category?.color ?? '#A1A1AA',
                            backgroundColor: `${sub.category?.color ?? '#A1A1AA'}1A`,
                            borderColor: `${sub.category?.color ?? '#A1A1AA'}55`,
                        }}
                    >
                        <Repeat className="h-4 w-4" aria-hidden="true" />
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="text-sm text-text-primary truncate">{sub.name}</p>
                        {sub.category && (
                            <p className="text-xs text-text-secondary mt-0.5">
                                {sub.category.name}
                            </p>
                        )}
                    </div>
                    <div className="text-right shrink-0">
                        <p className="text-sm font-mono tabular-nums text-text-primary">
                            {formatPln(sub.monthly_cost)} {sub.currency}
                        </p>
                        <p className="text-xs text-text-secondary font-mono">
                            {cycleHint(sub.billing_cycle_days)}
                        </p>
                    </div>
                </li>
            ))}
        </ul>
    );
};
