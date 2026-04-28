import { ButtonHTMLAttributes, forwardRef } from 'react';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/cn';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'lg';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
}

const variantClasses: Record<Variant, string> = {
    primary:
        'bg-accent-primary text-white hover:bg-accent-primary/90 hover:shadow-glow active:scale-[0.98]',
    secondary:
        'glass text-text-primary hover:glass-elevated active:scale-[0.98]',
    ghost: 'text-text-secondary hover:text-text-primary hover:bg-white/5',
    danger:
        'bg-state-danger text-white hover:bg-state-danger/90 hover:shadow-glow-danger active:scale-[0.98]',
};

const sizeClasses: Record<Size, string> = {
    sm: 'h-8 px-3 text-sm',
    md: 'h-10 px-4 text-sm',
    lg: 'h-12 px-6 text-base',
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
    (
        {
            variant = 'primary',
            size = 'md',
            loading = false,
            disabled,
            className,
            children,
            ...props
        },
        ref,
    ) => {
        return (
            <button
                ref={ref}
                disabled={disabled || loading}
                className={cn(
                    'inline-flex items-center justify-center gap-2 rounded-2xl font-medium',
                    'transition-all duration-200',
                    'disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100',
                    variantClasses[variant],
                    sizeClasses[size],
                    className,
                )}
                {...props}
            >
                {loading && <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />}
                {children}
            </button>
        );
    },
);

Button.displayName = 'Button';
