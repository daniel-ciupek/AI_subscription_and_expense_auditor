import { ReactNode, useEffect, useRef } from 'react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { X } from 'lucide-react';
import { cn } from '@/lib/cn';

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title?: string;
    description?: string;
    children: ReactNode;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl';
    showClose?: boolean;
}

const maxWidthClasses = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-2xl',
};

export function Modal({
    open,
    onClose,
    title,
    description,
    children,
    maxWidth = 'md',
    showClose = true,
}: ModalProps) {
    const dialogRef = useRef<HTMLDivElement>(null);
    const reduce = useReducedMotion();

    useEffect(() => {
        if (!open) return;

        const handleKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };

        document.addEventListener('keydown', handleKey);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', handleKey);
            document.body.style.overflow = '';
        };
    }, [open, onClose]);

    return (
        <AnimatePresence>
            {open && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: reduce ? 0 : 0.2 }}
                        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
                        onClick={onClose}
                        aria-hidden="true"
                    />
                    <motion.div
                        ref={dialogRef}
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby={title ? 'modal-title' : undefined}
                        aria-describedby={description ? 'modal-description' : undefined}
                        initial={{ opacity: 0, scale: 0.95, y: 10 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.95, y: 10 }}
                        transition={{ duration: reduce ? 0 : 0.2, ease: 'easeOut' }}
                        className={cn(
                            'relative w-full glass-elevated rounded-3xl p-6 shadow-2xl',
                            maxWidthClasses[maxWidth],
                        )}
                    >
                        {showClose && (
                            <button
                                onClick={onClose}
                                aria-label="Close modal"
                                className="absolute top-4 right-4 text-text-secondary hover:text-text-primary transition-colors rounded-lg p-1"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        )}
                        {title && (
                            <h2
                                id="modal-title"
                                className="text-lg font-semibold text-text-primary mb-1 pr-8"
                            >
                                {title}
                            </h2>
                        )}
                        {description && (
                            <p
                                id="modal-description"
                                className="text-sm text-text-secondary mb-4"
                            >
                                {description}
                            </p>
                        )}
                        <div>{children}</div>
                    </motion.div>
                </div>
            )}
        </AnimatePresence>
    );
}
