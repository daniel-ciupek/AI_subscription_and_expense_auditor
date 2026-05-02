import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { Sparkles } from 'lucide-react';
import { ToastContainer } from '@/Components/UI/Toast';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="relative min-h-screen flex flex-col items-center justify-center px-4 py-10 overflow-hidden">
            <div className="absolute inset-0 bg-mesh motion-safe:animate-mesh-shift -z-10" aria-hidden="true" />

            <Link
                href="/"
                className="flex items-center gap-2 mb-8 group rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-base"
                aria-label="Home"
            >
                <div className="rounded-2xl bg-accent-primary/10 p-2.5 ring-1 ring-accent-primary/30 group-hover:ring-accent-primary/60 transition-all">
                    <Sparkles className="h-6 w-6 text-accent-neon" />
                </div>
                <span className="text-text-primary text-lg font-semibold tracking-tight">
                    Subscription Auditor
                </span>
            </Link>

            <div className="w-full max-w-md glass-elevated rounded-3xl p-8 shadow-2xl">
                {children}
            </div>

            <ToastContainer />
        </div>
    );
}
