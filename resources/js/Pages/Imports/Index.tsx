import { Head, Link, router } from '@inertiajs/react';
import { Upload, Trash2, CheckCircle2, XCircle, Loader2, Clock } from 'lucide-react';
import { motion, useReducedMotion } from 'framer-motion';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { EmptyState } from '@/Components/UI/EmptyState';
import { cn } from '@/lib/cn';

interface ImportRow {
    id: number;
    bank: string;
    original_filename: string;
    status: 'pending' | 'processing' | 'done' | 'failed';
    failed_reason: string | null;
    transactions_count: number;
    created_at: string;
}

const bankLabels: Record<string, string> = {
    mbank: 'mBank',
    pko_bp: 'PKO BP',
    ing: 'ING',
    santander: 'Santander',
    bgz_bnp_paribas: 'BGŻ BNP Paribas',
};

const statusBadge: Record<
    ImportRow['status'],
    { label: string; className: string; Icon: typeof CheckCircle2 }
> = {
    pending: {
        label: 'Pending',
        className: 'text-text-secondary bg-white/5 ring-1 ring-white/10',
        Icon: Clock,
    },
    processing: {
        label: 'Processing',
        className: 'text-accent-neon bg-accent-neon/10 ring-1 ring-accent-neon/30',
        Icon: Loader2,
    },
    done: {
        label: 'Done',
        className: 'text-state-success bg-state-success/10 ring-1 ring-state-success/30',
        Icon: CheckCircle2,
    },
    failed: {
        label: 'Failed',
        className: 'text-state-danger bg-state-danger/10 ring-1 ring-state-danger/30',
        Icon: XCircle,
    },
};

export default function ImportsIndex({ imports }: { imports: ImportRow[] }) {
    const handleDelete = (id: number) => {
        if (!confirm('Delete this import?')) return;
        router.delete(route('imports.destroy', id));
    };

    const reduce = useReducedMotion();
    const listVariants = reduce
        ? undefined
        : {
              hidden: { opacity: 0 },
              show: {
                  opacity: 1,
                  transition: { staggerChildren: 0.05, delayChildren: 0.05 },
              },
          };
    const itemVariants = reduce
        ? undefined
        : {
              hidden: { opacity: 0, y: 8 },
              show: {
                  opacity: 1,
                  y: 0,
                  transition: { duration: 0.22, ease: 'easeOut' as const },
              },
          };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-semibold text-text-primary">
                            Imports
                        </h1>
                        <p className="text-sm text-text-secondary mt-1">
                            CSV uploads and parsing status.
                        </p>
                    </div>
                    <Link href={route('imports.create')} className="self-start sm:self-auto shrink-0">
                        <Button>
                            <Upload className="h-4 w-4" aria-hidden="true" />
                            New import
                        </Button>
                    </Link>
                </div>
            }
        >
            <Head title="Imports" />

            {imports.length === 0 ? (
                <EmptyState
                    icon={Upload}
                    title="No imports yet"
                    description="Upload your first bank CSV to start tracking transactions and subscriptions."
                    action={
                        <Link href={route('imports.create')}>
                            <Button>Upload CSV</Button>
                        </Link>
                    }
                />
            ) : (
                <motion.div
                    className="flex flex-col gap-3"
                    variants={listVariants}
                    initial={reduce ? false : 'hidden'}
                    animate="show"
                >
                    {imports.map((imp) => {
                        const badge = statusBadge[imp.status];
                        const Icon = badge.Icon;
                        return (
                            <motion.div key={imp.id} variants={itemVariants}>
                            <Card className="flex items-center gap-4">
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <p className="text-sm font-medium text-text-primary truncate">
                                            {imp.original_filename}
                                        </p>
                                        <span
                                            className={cn(
                                                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium',
                                                badge.className,
                                            )}
                                        >
                                            <Icon
                                                className={cn(
                                                    'h-3 w-3',
                                                    imp.status === 'processing' &&
                                                        'animate-spin',
                                                )}
                                                aria-hidden="true"
                                            />
                                            {badge.label}
                                        </span>
                                    </div>
                                    <p className="text-xs text-text-secondary mt-1">
                                        {bankLabels[imp.bank] ?? imp.bank} •{' '}
                                        <span className="font-mono tabular-nums">
                                            {imp.transactions_count}
                                        </span>{' '}
                                        transactions •{' '}
                                        {new Date(imp.created_at).toLocaleString()}
                                    </p>
                                    {imp.status === 'failed' && imp.failed_reason && (
                                        <p className="text-xs text-state-danger mt-1.5">
                                            {imp.failed_reason}
                                        </p>
                                    )}
                                </div>
                                <button
                                    type="button"
                                    onClick={() => handleDelete(imp.id)}
                                    className="text-text-secondary hover:text-state-danger transition-colors p-2 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-state-danger focus-visible:ring-offset-2 focus-visible:ring-offset-bg-surface"
                                    aria-label={`Delete import ${imp.id}`}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </button>
                            </Card>
                            </motion.div>
                        );
                    })}
                </motion.div>
            )}
        </AuthenticatedLayout>
    );
}
