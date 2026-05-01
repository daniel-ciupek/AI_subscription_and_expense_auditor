import { Link } from '@inertiajs/react';
import { AlertTriangle, Sparkles, TrendingUp, type LucideIcon } from 'lucide-react';
import { cn } from '@/lib/cn';

export type AiAlertType = 'warning' | 'info';

export interface AiAlert {
    type: AiAlertType;
    title: string;
    message: string;
    action_label: string | null;
    action_url: string | null;
}

interface AiAlertWidgetProps {
    alerts: AiAlert[];
}

const stylesByType: Record<
    AiAlertType,
    { icon: LucideIcon; bg: string; ring: string; iconColor: string }
> = {
    warning: {
        icon: AlertTriangle,
        bg: 'bg-state-warning/5',
        ring: 'ring-state-warning/30',
        iconColor: 'text-state-warning',
    },
    info: {
        icon: TrendingUp,
        bg: 'bg-accent-neon/5',
        ring: 'ring-accent-neon/30',
        iconColor: 'text-accent-neon',
    },
};

export const AiAlertWidget = ({ alerts }: AiAlertWidgetProps) => {
    if (alerts.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center gap-2 text-xs text-text-secondary uppercase tracking-wider">
                <Sparkles className="h-3.5 w-3.5 text-accent-neon" aria-hidden="true" />
                <span>Insights</span>
            </div>
            {alerts.map((alert, idx) => {
                const style = stylesByType[alert.type];
                const Icon = style.icon;
                return (
                    <div
                        key={idx}
                        className={cn(
                            'glass rounded-2xl p-4 ring-1 flex items-start gap-3',
                            style.bg,
                            style.ring,
                        )}
                    >
                        <div
                            className={cn(
                                'rounded-xl p-2 ring-1 shrink-0',
                                style.bg,
                                style.ring,
                                style.iconColor,
                            )}
                        >
                            <Icon className="h-4 w-4" aria-hidden="true" />
                        </div>
                        <div className="flex-1 min-w-0">
                            <h3 className="text-sm font-semibold text-text-primary">
                                {alert.title}
                            </h3>
                            <p className="text-xs text-text-secondary mt-1">
                                {alert.message}
                            </p>
                            {alert.action_label && alert.action_url && (
                                <Link
                                    href={alert.action_url}
                                    className="inline-block mt-2 text-xs text-accent-neon hover:text-accent-neon/80 transition-colors"
                                >
                                    {alert.action_label} →
                                </Link>
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
};
