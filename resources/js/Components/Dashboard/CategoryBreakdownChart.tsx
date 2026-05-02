import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

export interface CategoryBreakdownEntry {
    slug: string;
    name: string;
    color: string;
    total: number;
}

interface CategoryBreakdownChartProps {
    data: CategoryBreakdownEntry[];
}

const formatPln = (value: number): string =>
    new Intl.NumberFormat('pl-PL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);

interface TooltipPayloadItem {
    payload: CategoryBreakdownEntry;
}

interface RechartsTooltipProps {
    active?: boolean;
    payload?: TooltipPayloadItem[];
}

const ChartTooltip = ({ active, payload }: RechartsTooltipProps) => {
    if (!active || !payload || payload.length === 0) {
        return null;
    }
    const entry = payload[0].payload;
    return (
        <div className="glass-elevated rounded-xl px-3 py-2 text-xs">
            <p className="font-medium text-text-primary">{entry.name}</p>
            <p className="font-mono tabular-nums text-text-secondary mt-0.5">
                {formatPln(entry.total)} PLN
            </p>
        </div>
    );
};

export const CategoryBreakdownChart = ({ data }: CategoryBreakdownChartProps) => {
    const total = data.reduce((sum, entry) => sum + entry.total, 0);

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
            <div className="relative h-64">
                <ResponsiveContainer width="100%" height="100%" minWidth={0}>
                    <PieChart>
                        <Pie
                            data={data}
                            dataKey="total"
                            nameKey="name"
                            innerRadius="60%"
                            outerRadius="90%"
                            paddingAngle={2}
                            stroke="none"
                        >
                            {data.map((entry) => (
                                <Cell key={entry.slug} fill={entry.color} />
                            ))}
                        </Pie>
                        <Tooltip content={<ChartTooltip />} />
                    </PieChart>
                </ResponsiveContainer>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span className="text-xs text-text-secondary uppercase tracking-wider">
                        Total spent
                    </span>
                    <span className="font-mono tabular-nums text-2xl text-text-primary mt-1">
                        {formatPln(total)}
                    </span>
                </div>
            </div>
            <ul className="flex flex-col gap-2">
                {data.map((entry) => {
                    const share = total > 0 ? (entry.total / total) * 100 : 0;
                    return (
                        <li
                            key={entry.slug}
                            className="flex items-center gap-3 text-sm"
                        >
                            <span
                                aria-hidden="true"
                                className="h-3 w-3 rounded-full shrink-0"
                                style={{ backgroundColor: entry.color }}
                            />
                            <span className="flex-1 text-text-primary">{entry.name}</span>
                            <span className="font-mono tabular-nums text-text-secondary">
                                {share.toFixed(1)}%
                            </span>
                            <span className="font-mono tabular-nums text-text-primary w-24 text-right">
                                {formatPln(entry.total)}
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
};
