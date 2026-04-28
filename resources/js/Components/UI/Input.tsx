import { InputHTMLAttributes, forwardRef } from 'react';
import { cn } from '@/lib/cn';

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    error?: boolean;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
    ({ error = false, className, type = 'text', ...props }, ref) => {
        return (
            <input
                ref={ref}
                type={type}
                className={cn(
                    'h-10 w-full rounded-2xl px-4 text-sm',
                    'bg-bg-surface border text-text-primary placeholder:text-text-secondary/50',
                    'transition-colors duration-200',
                    'focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-bg-base',
                    'disabled:opacity-50 disabled:cursor-not-allowed',
                    error
                        ? 'border-state-danger/50 focus-visible:ring-state-danger'
                        : 'border-white/10 focus:border-accent-neon/50 focus-visible:ring-accent-neon',
                    className,
                )}
                {...props}
            />
        );
    },
);

Input.displayName = 'Input';
