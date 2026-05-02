import { Head, Link } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import { Sparkles, Upload, Repeat, ChartLine } from 'lucide-react';
import { Button } from '@/Components/UI/Button';
import { Card } from '@/Components/UI/Card';
import { PageProps } from '@/types';

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

                <main className="px-4 sm:px-6 max-w-7xl mx-auto pt-16 pb-20">
                    <motion.div
                        {...fadeUp}
                        transition={{ duration: reduce ? 0 : 0.5 }}
                        className="text-center max-w-3xl mx-auto mb-16"
                    >
                        <div className="inline-flex items-center gap-2 glass rounded-full px-4 py-1.5 text-xs text-text-secondary mb-6">
                            <Sparkles className="h-3.5 w-3.5 text-accent-neon" />
                            AI-driven financial insights
                        </div>
                        <h1 className="text-4xl md:text-6xl font-semibold tracking-tight text-text-primary mb-6">
                            Audit subscriptions{' '}
                            <span className="bg-gradient-to-r from-accent-primary to-accent-neon bg-clip-text text-transparent">
                                with AI
                            </span>
                        </h1>
                        <p className="text-lg text-text-secondary mb-8 max-w-xl mx-auto">
                            Upload your bank CSV. We&apos;ll detect recurring
                            charges, categorize expenses, and flag duplicate
                            subscriptions — automatically.
                        </p>
                        <div className="flex items-center justify-center gap-3">
                            <Link
                                href={auth.user ? route('dashboard') : route('register')}
                            >
                                <Button variant="primary" size="lg">
                                    Get started
                                </Button>
                            </Link>
                        </div>
                    </motion.div>

                    <motion.div
                        {...fadeUp}
                        transition={{ duration: reduce ? 0 : 0.5, delay: reduce ? 0 : 0.1 }}
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
