import { ReactNode } from 'react';
import { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/cn';

interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    className?: string;
}

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'glass rounded-3xl p-8 flex flex-col items-center justify-center text-center gap-3',
                className,
            )}
        >
            <div className="rounded-full bg-accent-primary/10 p-4 ring-1 ring-accent-primary/20">
                <Icon className="h-8 w-8 text-accent-primary" aria-hidden="true" />
            </div>
            <h3 className="text-lg font-semibold text-text-primary">{title}</h3>
            {description && (
                <p className="text-sm text-text-secondary max-w-sm">{description}</p>
            )}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
