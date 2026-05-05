import { Head, Link } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import {
    Sparkles,
    Upload,
    Repeat,
    ChartLine,
    AlertTriangle,
    Film,
    Music,
    Tv,
} from 'lucide-react';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { PageProps } from '@/types';

interface MockSub {
    name: string;
    icon: typeof Film;
    amount: number;
    color: string;
}

const MOCK_SUBS: MockSub[] = [
    { name: 'Netflix', icon: Film, amount: 49.99, color: '#EF4444' },
    { name: 'Spotify', icon: Music, amount: 19.99, color: '#10B981' },
    { name: 'Disney+', icon: Tv, amount: 28.99, color: '#22D3EE' },
];

export default function Welcome({
    auth,
}: PageProps<{ laravelVersion: string; phpVersion: string }>) {
    const reduce = useReducedMotion();
    const fadeUp = reduce
        ? { initial: false, animate: { opacity: 1, y: 0 } }
        : {
              initial: { opacity: 0, y: 20 },
              animate: { opacity: 1, y: 0 },
          };
    const fadeRight = reduce
        ? { initial: false, animate: { opacity: 1, x: 0 } }
        : {
              initial: { opacity: 0, x: 20 },
              animate: { opacity: 1, x: 0 },
          };
    const mockTotal = MOCK_SUBS.reduce((s, m) => s + m.amount, 0);

    return (
        <>
            <Head title="Welcome" />

            <div className="relative min-h-screen overflow-hidden">
                <div
                    className="fixed inset-0 bg-mesh motion-safe:animate-mesh-shift -z-10"
                    aria-hidden="true"
                />

                <header className="px-4 sm:px-6 py-6 max-w-7xl mx-auto flex items-center justify-between gap-3">
                    <Link
                        href="/"
                        className="flex items-center gap-2 group rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-base min-w-0"
                    >
                        <div className="rounded-2xl bg-accent-primary/10 p-2.5 ring-1 ring-accent-primary/30 group-hover:ring-accent-primary/60 transition-all shrink-0">
                            <Sparkles className="h-6 w-6 text-accent-neon" />
                        </div>
                        <span className="hidden sm:inline text-text-primary font-semibold tracking-tight truncate">
                            Subscription Auditor
                        </span>
                    </Link>

                    <nav className="flex items-center gap-3">
                        {auth.user ? (
                            <Link href={route('dashboard')}>
                                <Button variant="primary" size="sm">
                                    Dashboard
                                </Button>
                            </Link>
                        ) : (
                            <>
                                <Link href={route('login')}>
                                    <Button variant="ghost" size="sm">
                                        Log in
                                    </Button>
                                </Link>
                                <Link href={route('register')}>
                                    <Button variant="primary" size="sm">
                                        Sign up
                                    </Button>
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <main className="px-4 sm:px-6 max-w-7xl mx-auto pt-12 lg:pt-20 pb-20">
                    <div className="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center mb-20">
                        <motion.div
                            {...fadeUp}
                            transition={{ duration: reduce ? 0 : 0.5 }}
                            className="text-center lg:text-left max-w-xl mx-auto lg:mx-0"
                        >
                            <div className="inline-flex items-center gap-2 glass rounded-full px-4 py-1.5 text-xs text-text-secondary mb-6">
                                <Sparkles className="h-3.5 w-3.5 text-accent-neon" />
                                AI-driven financial insights
                            </div>
                            <h1 className="text-4xl md:text-5xl lg:text-6xl font-semibold tracking-tight text-text-primary mb-6">
                                Audit subscriptions{' '}
                                <span className="bg-gradient-to-r from-accent-primary to-accent-neon bg-clip-text text-transparent">
                                    with AI
                                </span>
                            </h1>
                            <p className="text-lg text-text-secondary mb-8">
                                Upload your bank CSV. We&apos;ll detect recurring
                                charges, categorize expenses, and flag duplicate
                                subscriptions — automatically.
                            </p>
                            <div className="flex items-center justify-center lg:justify-start gap-3">
                                <Link
                                    href={
                                        auth.user
                                            ? route('dashboard')
                                            : route('register')
                                    }
                                >
                                    <Button variant="primary" size="lg">
                                        Get started
                                    </Button>
                                </Link>
                                {!auth.user && (
                                    <Link href={route('login')}>
                                        <Button variant="ghost" size="lg">
                                            Log in
                                        </Button>
                                    </Link>
                                )}
                            </div>
                        </motion.div>

                        <motion.div
                            {...fadeRight}
                            transition={{
                                duration: reduce ? 0 : 0.6,
                                delay: reduce ? 0 : 0.15,
                            }}
                            className="relative max-w-md mx-auto w-full lg:max-w-none"
                            aria-hidden="true"
                        >
                            <div
                                className="absolute -inset-px rounded-3xl bg-gradient-to-br from-accent-primary/40 via-accent-neon/40 to-accent-primary/40 bg-[length:200%_200%] motion-safe:animate-gradient-shift opacity-70 blur-md"
                            />
                            <div className="relative glass-elevated rounded-3xl p-6 overflow-hidden">
                                <Sparkles
                                    className="absolute top-4 right-4 h-3 w-3 text-accent-neon motion-safe:animate-sparkle-float"
                                    style={{ animationDelay: '0s' }}
                                />
                                <Sparkles
                                    className="absolute top-12 right-12 h-2 w-2 text-accent-primary motion-safe:animate-sparkle-float"
                                    style={{ animationDelay: '1s' }}
                                />
                                <Sparkles
                                    className="absolute bottom-20 left-6 h-2.5 w-2.5 text-accent-neon motion-safe:animate-sparkle-float"
                                    style={{ animationDelay: '1.8s' }}
                                />

                                <div className="flex items-center justify-between mb-5">
                                    <div className="flex items-center gap-2">
                                        <div className="rounded-xl bg-accent-primary/10 p-1.5 ring-1 ring-accent-primary/30">
                                            <Repeat className="h-3.5 w-3.5 text-accent-neon" />
                                        </div>
                                        <span className="text-sm font-semibold text-text-primary">
                                            Top subscriptions
                                        </span>
                                    </div>
                                    <span className="text-[10px] text-text-secondary uppercase tracking-wider font-mono">
                                        Live preview
                                    </span>
                                </div>

                                <ul className="flex flex-col divide-y divide-white/5 mb-4">
                                    {MOCK_SUBS.map((sub) => {
                                        const Icon = sub.icon;
                                        return (
                                            <li
                                                key={sub.name}
                                                className="flex items-center gap-3 py-2.5 first:pt-0"
                                            >
                                                <div
                                                    className="rounded-xl p-2 ring-1 shrink-0"
                                                    style={{
                                                        backgroundColor: `${sub.color}1A`,
                                                        borderColor: `${sub.color}55`,
                                                        color: sub.color,
                                                    }}
                                                >
                                                    <Icon className="h-4 w-4" />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-sm text-text-primary font-medium">
                                                        {sub.name}
                                                    </p>
                                                    <p className="text-[10px] text-text-secondary uppercase tracking-wider">
                                                        Monthly
                                                    </p>
                                                </div>
                                                <p className="text-sm font-mono tabular-nums text-text-primary shrink-0">
                                                    {sub.amount.toFixed(2)}{' '}
                                                    <span className="text-xs text-text-secondary">
                                                        PLN
                                                    </span>
                                                </p>
                                            </li>
                                        );
                                    })}
                                </ul>

                                <div className="flex items-baseline justify-between pt-3 border-t border-white/10 mb-4">
                                    <span className="text-xs text-text-secondary uppercase tracking-wider">
                                        Total / month
                                    </span>
                                    <span className="text-lg font-mono tabular-nums text-text-primary">
                                        {mockTotal.toFixed(2)}{' '}
                                        <span className="text-xs text-text-secondary">
                                            PLN
                                        </span>
                                    </span>
                                </div>

                                <div className="rounded-2xl bg-state-warning/10 ring-1 ring-state-warning/30 p-3 flex items-start gap-2.5">
                                    <AlertTriangle className="h-4 w-4 text-state-warning shrink-0 mt-0.5" />
                                    <p className="text-xs text-text-secondary leading-relaxed">
                                        <span className="text-text-primary font-medium">
                                            AI flagged 1 possible duplicate
                                        </span>{' '}
                                        — Disney+ EU billed 28d apart from
                                        Disney+, similar amount.
                                    </p>
                                </div>
                            </div>
                        </motion.div>
                    </div>

                    <motion.div
                        {...fadeUp}
                        transition={{
                            duration: reduce ? 0 : 0.5,
                            delay: reduce ? 0 : 0.25,
                        }}
                        className="grid grid-cols-1 md:grid-cols-3 gap-4"
                    >
                        <Card hoverable>
                            <Upload
                                className="h-6 w-6 text-accent-neon mb-3"
                                aria-hidden="true"
                            />
                            <h3 className="font-semibold text-text-primary mb-1">
                                CSV Import
                            </h3>
                            <p className="text-sm text-text-secondary">
                                Drag &amp; drop statements from 5 Polish banks.
                                Auto-detected.
                            </p>
                        </Card>
                        <Card hoverable>
                            <Repeat
                                className="h-6 w-6 text-accent-neon mb-3"
                                aria-hidden="true"
                            />
                            <h3 className="font-semibold text-text-primary mb-1">
                                Subscription Detection
                            </h3>
                            <p className="text-sm text-text-secondary">
                                Recurring charges identified, duplicates flagged with AI.
                            </p>
                        </Card>
                        <Card hoverable>
                            <ChartLine
                                className="h-6 w-6 text-accent-neon mb-3"
                                aria-hidden="true"
                            />
                            <h3 className="font-semibold text-text-primary mb-1">
                                Smart Categories
                            </h3>
                            <p className="text-sm text-text-secondary">
                                AI classifies every transaction so you see where money flows.
                            </p>
                        </Card>
                    </motion.div>
                </main>
            </div>
        </>
    );
}
