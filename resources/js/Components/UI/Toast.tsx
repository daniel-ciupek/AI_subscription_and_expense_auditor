import { useEffect, useState } from 'react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { CheckCircle2, AlertCircle, Info, X } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { User } from '@/types';
import { cn } from '@/lib/cn';

type ToastVariant = 'success' | 'error' | 'info';

interface ToastItem {
    id: string;
    variant: ToastVariant;
    message: string;
}

interface SharedFlash {
    success?: string | null;
    error?: string | null;
    info?: string | null;
}

type PageWithFlash = {
    flash?: SharedFlash;
    auth: { user: User };
} & Record<string, unknown>;

const variantConfig: Record<
    ToastVariant,
    { icon: typeof CheckCircle2; iconClass: string }
> = {
    success: { icon: CheckCircle2, iconClass: 'text-state-success' },
    error: { icon: AlertCircle, iconClass: 'text-state-danger' },
    info: { icon: Info, iconClass: 'text-accent-neon' },
};

export function ToastContainer() {
    const { props } = usePage<PageWithFlash>();
    const [toasts, setToasts] = useState<ToastItem[]>([]);
    const reduce = useReducedMotion();

    useEffect(() => {
        const flash = props.flash ?? {};
        const incoming: ToastItem[] = [];

        if (flash.success) {
            incoming.push({
                id: `success-${Date.now()}`,
                variant: 'success',
                message: flash.success,
            });
        }
        if (flash.error) {
            incoming.push({
                id: `error-${Date.now()}`,
                variant: 'error',
                message: flash.error,
            });
        }
        if (flash.info) {
            incoming.push({
                id: `info-${Date.now()}`,
                variant: 'info',
                message: flash.info,
            });
        }

        if (incoming.length > 0) {
            setToasts((prev) => [...prev, ...incoming]);
        }
    }, [props.flash]);

    useEffect(() => {
        if (toasts.length === 0) return;
        const timers = toasts.map((toast) =>
            setTimeout(() => {
                setToasts((prev) => prev.filter((t) => t.id !== toast.id));
            }, 4000),
        );
        return () => timers.forEach(clearTimeout);
    }, [toasts]);

    const dismiss = (id: string) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    };

    return (
        <div
            aria-live="polite"
            aria-atomic="true"
            className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none"
        >
            <AnimatePresence>
                {toasts.map((toast) => {
                    const { icon: Icon, iconClass } = variantConfig[toast.variant];
                    return (
                        <motion.div
                            key={toast.id}
                            initial={{ opacity: 0, x: 40, scale: 0.95 }}
                            animate={{ opacity: 1, x: 0, scale: 1 }}
                            exit={{ opacity: 0, x: 40, scale: 0.95 }}
                            transition={{ duration: reduce ? 0 : 0.2, ease: 'easeOut' }}
                            className={cn(
                                'glass-elevated rounded-2xl p-4 pr-10 shadow-xl flex items-start gap-3',
                                'pointer-events-auto relative',
                            )}
                        >
                            <Icon className={cn('h-5 w-5 shrink-0 mt-0.5', iconClass)} aria-hidden="true" />
                            <p className="text-sm text-text-primary flex-1">{toast.message}</p>
                            <button
                                onClick={() => dismiss(toast.id)}
                                aria-label="Dismiss"
                                className="absolute top-3 right-3 text-text-secondary hover:text-text-primary transition-colors rounded-md p-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-elevated"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        </motion.div>
                    );
                })}
            </AnimatePresence>
        </div>
    );
}
