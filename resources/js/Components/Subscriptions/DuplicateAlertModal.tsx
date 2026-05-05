import { useEffect } from 'react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { Sparkles, AlertTriangle } from 'lucide-react';
import { Button } from '@/Components/UI/Button';

interface DuplicatePair {
    id: number;
    name: string;
    duplicateOfName: string;
}

interface DuplicateAlertModalProps {
    open: boolean;
    duplicates: DuplicatePair[];
    onReview: () => void;
    onDismiss: () => void;
}

export function DuplicateAlertModal({
    open,
    duplicates,
    onReview,
    onDismiss,
}: DuplicateAlertModalProps) {
    const reduce = useReducedMotion();

    useEffect(() => {
        if (!open) return;
        const handleKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onDismiss();
        };
        document.addEventListener('keydown', handleKey);
        document.body.style.overflow = 'hidden';
        return () => {
            document.removeEventListener('keydown', handleKey);
            document.body.style.overflow = '';
        };
    }, [open, onDismiss]);

    return (
        <AnimatePresence>
            {open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: reduce ? 0 : 0.2 }}
                        className="absolute inset-0 bg-black/70 backdrop-blur-sm"
                        onClick={onDismiss}
                        aria-hidden="true"
                    />

                    <motion.div
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="dup-modal-title"
                        aria-describedby="dup-modal-desc"
                        initial={
                            reduce
                                ? { opacity: 0 }
                                : { opacity: 0, scale: 0.92, y: 20 }
                        }
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={
                            reduce
                                ? { opacity: 0 }
                                : { opacity: 0, scale: 0.95, y: 10 }
                        }
                        transition={{
                            duration: reduce ? 0 : 0.3,
                            ease: 'easeOut',
                        }}
                        className="relative w-full max-w-md"
                    >
                        <div
                            className="absolute -inset-px rounded-3xl bg-gradient-to-r from-accent-primary via-accent-neon to-accent-primary bg-[length:200%_200%] motion-safe:animate-gradient-shift opacity-80 blur-sm"
                            aria-hidden="true"
                        />

                        <div className="relative glass-elevated rounded-3xl p-6 overflow-hidden">
                            <Sparkles
                                className="absolute top-4 right-6 h-3 w-3 text-accent-neon motion-safe:animate-sparkle-float"
                                style={{ animationDelay: '0s' }}
                                aria-hidden="true"
                            />
                            <Sparkles
                                className="absolute top-10 right-16 h-2 w-2 text-accent-primary motion-safe:animate-sparkle-float"
                                style={{ animationDelay: '0.7s' }}
                                aria-hidden="true"
                            />
                            <Sparkles
                                className="absolute bottom-12 left-8 h-2.5 w-2.5 text-accent-neon motion-safe:animate-sparkle-float"
                                style={{ animationDelay: '1.4s' }}
                                aria-hidden="true"
                            />

                            <div className="flex items-start gap-3 mb-4">
                                <div className="rounded-2xl bg-gradient-to-br from-accent-primary/20 to-accent-neon/20 p-2.5 ring-1 ring-accent-neon/30 shrink-0">
                                    <AlertTriangle
                                        className="h-5 w-5 text-accent-neon"
                                        aria-hidden="true"
                                    />
                                </div>
                                <div className="flex-1">
                                    <h2
                                        id="dup-modal-title"
                                        className="text-lg font-semibold text-text-primary"
                                    >
                                        AI found possible duplicate
                                        {duplicates.length === 1 ? '' : 's'}
                                    </h2>
                                    <p
                                        id="dup-modal-desc"
                                        className="text-sm text-text-secondary mt-1"
                                    >
                                        We spotted{' '}
                                        <span className="text-text-primary font-medium">
                                            {duplicates.length}
                                        </span>{' '}
                                        subscription
                                        {duplicates.length === 1 ? '' : 's'} that
                                        might be the same merchant under a different
                                        statement name.
                                    </p>
                                </div>
                            </div>

                            <ul className="flex flex-col gap-2 mb-5 max-h-48 overflow-y-auto pr-1">
                                {duplicates.slice(0, 5).map((pair) => (
                                    <li
                                        key={pair.id}
                                        className="text-sm flex items-center gap-2 rounded-xl bg-bg-base/40 ring-1 ring-white/5 px-3 py-2"
                                    >
                                        <span className="text-text-primary truncate flex-1 min-w-0">
                                            {pair.name}
                                        </span>
                                        <span className="text-text-secondary text-xs shrink-0">
                                            ≈
                                        </span>
                                        <span className="text-text-secondary truncate flex-1 min-w-0 text-right">
                                            {pair.duplicateOfName}
                                        </span>
                                    </li>
                                ))}
                                {duplicates.length > 5 && (
                                    <li className="text-xs text-text-secondary text-center pt-1">
                                        + {duplicates.length - 5} more
                                    </li>
                                )}
                            </ul>

                            <div className="flex justify-end gap-2">
                                <Button variant="ghost" size="sm" onClick={onDismiss}>
                                    Dismiss
                                </Button>
                                <Button variant="primary" size="sm" onClick={onReview}>
                                    Review duplicates
                                </Button>
                            </div>
                        </div>
                    </motion.div>
                </div>
            )}
        </AnimatePresence>
    );
}
