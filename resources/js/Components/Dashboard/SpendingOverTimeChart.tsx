import { Area, AreaChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export interface SpendingPoint {
    date: string;
    total: number;
}

interface SpendingOverTimeChartProps {
    data: SpendingPoint[];
}

const formatPln = (value: number): string =>
    new Intl.NumberFormat('pl-PL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);

const formatTickDate = (iso: string): string => {
    const date = new Date(iso);
    return date.toLocaleDateString('pl-PL', { day: '2-digit', month: 'short' });
};

interface TooltipPayloadItem {
    payload: SpendingPoint;
}

interface RechartsTooltipProps {
    active?: boolean;
    payload?: TooltipPayloadItem[];
}

const ChartTooltip = ({ active, payload }: RechartsTooltipProps) => {
    if (!active || !payload || payload.length === 0) {
        return null;
    }
    const point = payload[0].payload;
    return (
        <div className="glass-elevated rounded-xl px-3 py-2 text-xs">
            <p className="font-medium text-text-primary">{formatTickDate(point.date)}</p>
            <p className="font-mono tabular-nums text-text-secondary mt-0.5">
                {formatPln(point.total)} PLN
            </p>
        </div>
    );
};

export const SpendingOverTimeChart = ({ data }: SpendingOverTimeChartProps) => {
    const total = data.reduce((sum, point) => sum + point.total, 0);
    const peak = data.reduce((max, point) => Math.max(max, point.total), 0);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-baseline justify-between gap-4 flex-wrap">
                <div>
                    <p className="text-xs text-text-secondary uppercase tracking-wider">
                        Last 90 days
                    </p>
                    <p className="font-mono tabular-nums text-2xl text-text-primary mt-1">
                        {formatPln(total)} PLN
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-xs text-text-secondary uppercase tracking-wider">
                        Peak day
                    </p>
                    <p className="font-mono tabular-nums text-sm text-text-primary mt-1">
                        {formatPln(peak)} PLN
                    </p>
                </div>
            </div>

            <div className="h-56 -mx-2">
                <ResponsiveContainer width="100%" height="100%" minWidth={0}>
                    <AreaChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: 8 }}>
                        <defs>
                            <linearGradient id="spendingGradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stopColor="#7C3AED" stopOpacity={0.6} />
                                <stop offset="100%" stopColor="#7C3AED" stopOpacity={0} />
                            </linearGradient>
                        </defs>
                        <XAxis
                            dataKey="date"
                            tickFormatter={formatTickDate}
                            tick={{ fill: '#71717A', fontSize: 11 }}
                            axisLine={false}
                            tickLine={false}
                            minTickGap={32}
                        />
                        <YAxis
                            tickFormatter={(value: number) => formatPln(value)}
                            tick={{ fill: '#71717A', fontSize: 11 }}
                            axisLine={false}
                            tickLine={false}
                            width={64}
                        />
                        <Tooltip content={<ChartTooltip />} cursor={{ stroke: '#22D3EE', strokeOpacity: 0.3 }} />
                        <Area
                            type="monotone"
                            dataKey="total"
                            stroke="#7C3AED"
                            strokeWidth={2}
                            fill="url(#spendingGradient)"
                            isAnimationActive={false}
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
};
