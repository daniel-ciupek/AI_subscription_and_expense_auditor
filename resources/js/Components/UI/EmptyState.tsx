import { ReactNode } from 'react';
import { LucideIcon, Sparkles } from 'lucide-react';
import { motion, useReducedMotion } from 'framer-motion';
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
    const reduce = useReducedMotion();

    return (
        <div
            className={cn(
                'glass rounded-3xl p-10 flex flex-col items-center justify-center text-center gap-3',
                className,
            )}
        >
            <div className="relative w-32 h-32 flex items-center justify-center mb-2">
                <motion.div
                    aria-hidden="true"
                    className="absolute inset-0 rounded-full bg-gradient-to-br from-accent-primary/30 to-accent-neon/10 blur-2xl"
                    animate={
                        reduce ? undefined : { opacity: [0.6, 0.9, 0.6] }
                    }
                    transition={{
                        duration: 4,
                        repeat: Infinity,
                        ease: 'easeInOut',
                    }}
                />

                <span
                    aria-hidden="true"
                    className="absolute inset-0 rounded-full ring-1 ring-accent-primary/15"
                />
                <motion.span
                    aria-hidden="true"
                    className="absolute inset-3 rounded-full ring-1 ring-accent-primary/20"
                    animate={
                        reduce
                            ? undefined
                            : { scale: [1, 1.04, 1], opacity: [0.6, 1, 0.6] }
                    }
                    transition={{
                        duration: 3.5,
                        repeat: Infinity,
                        ease: 'easeInOut',
                    }}
                />
                <motion.span
                    aria-hidden="true"
                    className="absolute inset-6 rounded-full ring-1 ring-accent-neon/30"
                    animate={
                        reduce
                            ? undefined
                            : { scale: [1, 1.06, 1], opacity: [0.5, 0.9, 0.5] }
                    }
                    transition={{
                        duration: 3,
                        repeat: Infinity,
                        ease: 'easeInOut',
                        delay: 0.5,
                    }}
                />

                <Sparkles
                    aria-hidden="true"
                    className="absolute top-1 right-3 h-3 w-3 text-accent-neon motion-safe:animate-sparkle-float"
                    style={{ animationDelay: '0s' }}
                />
                <Sparkles
                    aria-hidden="true"
                    className="absolute bottom-3 left-2 h-2.5 w-2.5 text-accent-primary motion-safe:animate-sparkle-float"
                    style={{ animationDelay: '1.2s' }}
                />

                <div className="relative rounded-full bg-bg-surface/80 backdrop-blur p-5 ring-1 ring-white/10">
                    <Icon
                        className="h-8 w-8 text-accent-neon"
                        aria-hidden="true"
                    />
                </div>
            </div>

            <h3 className="text-lg font-semibold text-text-primary">{title}</h3>
            {description && (
                <p className="text-sm text-text-secondary max-w-sm">{description}</p>
            )}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
