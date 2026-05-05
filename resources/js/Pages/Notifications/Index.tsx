import { Head, router } from '@inertiajs/react';
import { Bell, BellOff, CalendarClock, CheckCheck } from 'lucide-react';
import { motion, useReducedMotion } from 'framer-motion';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { EmptyState } from '@/Components/UI/EmptyState';
import { cn } from '@/lib/cn';

interface NotificationRow {
    id: string;
    subscription_id: number | null;
    subscription_name: string;
    amount: number;
    currency: string;
    expected_at: string | null;
    read_at: string | null;
    created_at: string | null;
}

interface NotificationsIndexProps {
    notifications: NotificationRow[];
}

const formatPln = (value: number): string =>
    new Intl.NumberFormat('pl-PL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);

const formatDate = (iso: string): string =>
    new Date(iso).toLocaleDateString('pl-PL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });

const formatRelative = (iso: string): string => {
    const diffMs = Date.now() - new Date(iso).getTime();
    const diffMin = Math.round(diffMs / 60000);
    if (diffMin < 1) return 'just now';
    if (diffMin < 60) return `${diffMin} min ago`;
    const diffH = Math.round(diffMin / 60);
    if (diffH < 24) return `${diffH} h ago`;
    const diffD = Math.round(diffH / 24);
    if (diffD < 30) return `${diffD} d ago`;
    return formatDate(iso);
};

export default function NotificationsIndex({
    notifications,
}: NotificationsIndexProps) {
    const reduce = useReducedMotion();
    const unread = notifications.filter((n) => n.read_at === null).length;

    const handleOpen = (id: string) => {
        router.post(route('notifications.read', id));
    };

    const handleMarkAllRead = () => {
        router.post(
            route('notifications.read-all'),
            {},
            { preserveScroll: true },
        );
    };

    const listVariants = reduce
        ? undefined
        : {
              hidden: { opacity: 0 },
              show: {
                  opacity: 1,
                  transition: { staggerChildren: 0.04, delayChildren: 0.05 },
              },
          };
    const itemVariants = reduce
        ? undefined
        : {
              hidden: { opacity: 0, y: 6 },
              show: {
                  opacity: 1,
                  y: 0,
                  transition: { duration: 0.2, ease: 'easeOut' as const },
              },
          };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-semibold text-text-primary">
                            Inbox
                        </h1>
                        <p className="text-sm text-text-secondary mt-1">
                            Heads-up alerts about upcoming charges and detection
                            results.
                        </p>
                    </div>
                    {unread > 0 && (
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={handleMarkAllRead}
                            className="self-start sm:self-auto shrink-0"
                        >
                            <CheckCheck className="h-4 w-4" aria-hidden="true" />
                            Mark all as read
                        </Button>
                    )}
                </div>
            }
        >
            <Head title="Inbox" />

            {notifications.length === 0 ? (
                <EmptyState
                    icon={BellOff}
                    title="Nothing in your inbox yet"
                    description="We'll let you know a few days before each detected subscription charges your account."
                />
            ) : (
                <motion.ul
                    className="flex flex-col gap-3"
                    variants={listVariants}
                    initial={reduce ? false : 'hidden'}
                    animate="show"
                >
                    {notifications.map((n) => {
                        const isUnread = n.read_at === null;
                        return (
                            <motion.li key={n.id} variants={itemVariants}>
                                <Card
                                    className={cn(
                                        'transition-colors',
                                        isUnread &&
                                            'ring-1 ring-accent-neon/30 bg-accent-primary/5',
                                    )}
                                >
                                    <button
                                        type="button"
                                        onClick={() => handleOpen(n.id)}
                                        className="flex items-start gap-3 w-full text-left rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-surface"
                                    >
                                        <div
                                            className={cn(
                                                'rounded-xl p-2 ring-1 shrink-0',
                                                isUnread
                                                    ? 'bg-accent-primary/10 ring-accent-primary/30 text-accent-neon'
                                                    : 'bg-white/5 ring-white/10 text-text-secondary',
                                            )}
                                        >
                                            <Bell
                                                className="h-4 w-4"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-baseline gap-2 flex-wrap">
                                                <p className="text-sm font-medium text-text-primary">
                                                    Upcoming charge:{' '}
                                                    <span className="font-semibold">
                                                        {n.subscription_name}
                                                    </span>
                                                </p>
                                                {isUnread && (
                                                    <span className="inline-block h-1.5 w-1.5 rounded-full bg-accent-neon" />
                                                )}
                                            </div>
                                            <p className="text-xs text-text-secondary mt-1 flex items-center gap-x-2 gap-y-1 flex-wrap">
                                                <CalendarClock
                                                    className="h-3.5 w-3.5 shrink-0"
                                                    aria-hidden="true"
                                                />
                                                <span>
                                                    {n.expected_at
                                                        ? formatDate(n.expected_at)
                                                        : '—'}
                                                </span>
                                                <span className="text-text-secondary/50">
                                                    ·
                                                </span>
                                                <span className="font-mono tabular-nums">
                                                    {formatPln(n.amount)} {n.currency}
                                                </span>
                                                {n.created_at && (
                                                    <>
                                                        <span className="text-text-secondary/50">
                                                            ·
                                                        </span>
                                                        <span>
                                                            {formatRelative(
                                                                n.created_at,
                                                            )}
                                                        </span>
                                                    </>
                                                )}
                                            </p>
                                        </div>
                                    </button>
                                </Card>
                            </motion.li>
                        );
                    })}
                </motion.ul>
            )}
        </AuthenticatedLayout>
    );
}
