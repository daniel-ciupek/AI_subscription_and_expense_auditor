import { ReactNode, useId } from 'react';
import { cn } from '@/lib/cn';

interface FormFieldProps {
    label?: string;
    error?: string;
    helperText?: string;
    required?: boolean;
    children: (id: string) => ReactNode;
    className?: string;
}

export function FormField({
    label,
    error,
    helperText,
    required = false,
    children,
    className,
}: FormFieldProps) {
    const id = useId();

    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            {label && (
                <label
                    htmlFor={id}
                    className="text-sm font-medium text-text-secondary"
                >
                    {label}
                    {required && <span className="text-state-danger ml-0.5">*</span>}
                </label>
            )}
            {children(id)}
            {error ? (
                <p className="text-xs text-state-danger" role="alert">
                    {error}
                </p>
            ) : helperText ? (
                <p className="text-xs text-text-secondary/70">{helperText}</p>
            ) : null}
        </div>
    );
}
