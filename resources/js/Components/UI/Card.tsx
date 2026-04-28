import { HTMLAttributes, forwardRef } from 'react';
import { cn } from '@/lib/cn';

interface CardProps extends HTMLAttributes<HTMLDivElement> {
    hoverable?: boolean;
    elevated?: boolean;
}

export const Card = forwardRef<HTMLDivElement, CardProps>(
    ({ hoverable = false, elevated = false, className, children, ...props }, ref) => {
        return (
            <div
                ref={ref}
                className={cn(
                    elevated ? 'glass-elevated' : 'glass',
                    'rounded-2xl p-6',
                    hoverable &&
                        'transition-all duration-300 hover:bg-white/[0.08] hover:border-white/20 hover:scale-[1.01]',
                    className,
                )}
                {...props}
            >
                {children}
            </div>
        );
    },
);

Card.displayName = 'Card';
