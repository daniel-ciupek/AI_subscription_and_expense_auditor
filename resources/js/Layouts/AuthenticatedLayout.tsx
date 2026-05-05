import { Link, usePage, router } from '@inertiajs/react';
import { PropsWithChildren, ReactNode } from 'react';
import {
    LayoutDashboard,
    Upload,
    Repeat,
    User,
    LogOut,
    Sparkles,
    Bell,
    LucideIcon,
} from 'lucide-react';
import { ToastContainer } from '@/Components/UI/Toast';
import { cn } from '@/lib/cn';

interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    routeName: string;
    showBadge?: boolean;
}

const navItems: NavItem[] = [
    { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, routeName: 'dashboard' },
    { label: 'Imports', href: '/imports', icon: Upload, routeName: 'imports.*' },
    { label: 'Subscriptions', href: '/subscriptions', icon: Repeat, routeName: 'subscriptions.*' },
    { label: 'Inbox', href: '/notifications', icon: Bell, routeName: 'notifications.*', showBadge: true },
    { label: 'Profile', href: '/profile', icon: User, routeName: 'profile.edit' },
];

function isActive(routeName: string): boolean {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth } = usePage().props;
    const user = auth.user;
    const unreadCount = auth.unreadNotificationsCount ?? 0;

    const handleLogout = () => {
        router.post(route('logout'));
    };

    return (
        <div className="relative min-h-screen overflow-hidden">
            <div className="fixed inset-0 bg-mesh motion-safe:animate-mesh-shift -z-10" aria-hidden="true" />

            {/* Sidebar — desktop */}
            <aside
                className="hidden md:flex fixed inset-y-0 left-0 w-64 flex-col p-4 z-30"
                aria-label="Sidebar navigation"
            >
                <div className="glass-elevated rounded-3xl flex-1 flex flex-col p-4">
                    <Link
                        href="/dashboard"
                        className="flex items-center gap-2 px-2 py-3 mb-2 group rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-elevated"
                        aria-label="Home"
                    >
                        <div className="rounded-xl bg-accent-primary/10 p-2 ring-1 ring-accent-primary/30 group-hover:ring-accent-primary/60 transition-all">
                            <Sparkles className="h-5 w-5 text-accent-neon" />
                        </div>
                        <span className="text-text-primary font-semibold tracking-tight">
                            Auditor
                        </span>
                    </Link>

                    <nav className="flex flex-col gap-1 flex-1">
                        {navItems.map((item) => {
                            const active = isActive(item.routeName);
                            const Icon = item.icon;
                            const showBadge = item.showBadge && unreadCount > 0;
                            return (
                                <Link
                                    key={item.routeName}
                                    href={item.href}
                                    className={cn(
                                        'flex items-center gap-3 px-3 py-2.5 rounded-2xl text-sm transition-all duration-200',
                                        'focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-elevated',
                                        active
                                            ? 'bg-accent-primary/15 text-text-primary ring-1 ring-accent-primary/30'
                                            : 'text-text-secondary hover:text-text-primary hover:bg-white/5',
                                    )}
                                    aria-current={active ? 'page' : undefined}
                                >
                                    <Icon className="h-5 w-5 shrink-0" aria-hidden="true" />
                                    <span className="flex-1">{item.label}</span>
                                    {showBadge && (
                                        <span
                                            className="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-[10px] font-semibold bg-accent-neon/20 text-accent-neon ring-1 ring-accent-neon/40 tabular-nums"
                                            aria-label={`${unreadCount} unread notifications`}
                                        >
                                            {unreadCount > 99 ? '99+' : unreadCount}
                                        </span>
                                    )}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="mt-auto pt-4 border-t border-white/10">
                        <div className="px-3 py-2 mb-2">
                            <p className="text-sm font-medium text-text-primary truncate">
                                {user.name}
                            </p>
                            <p className="text-xs text-text-secondary truncate">
                                {user.email}
                            </p>
                        </div>
                        <button
                            onClick={handleLogout}
                            className="flex items-center gap-3 px-3 py-2.5 rounded-2xl text-sm w-full text-text-secondary hover:text-state-danger hover:bg-state-danger/10 transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-state-danger focus-visible:ring-offset-2 focus-visible:ring-offset-bg-elevated"
                        >
                            <LogOut className="h-5 w-5" aria-hidden="true" />
                            <span>Log out</span>
                        </button>
                    </div>
                </div>
            </aside>

            {/* Main content */}
            <div className="md:pl-64 min-h-screen flex flex-col pb-20 md:pb-0">
                {header && (
                    <header className="px-4 md:px-8 py-6">
                        <div className="mx-auto max-w-7xl">{header}</div>
                    </header>
                )}
                <main className="flex-1 px-4 md:px-8 pb-8">
                    <div className="mx-auto max-w-7xl">{children}</div>
                </main>
            </div>

            {/* Bottom nav — mobile */}
            <nav
                className="md:hidden fixed bottom-0 inset-x-0 z-40 px-2 pb-2 pt-1"
                aria-label="Bottom navigation"
            >
                <div className="glass-elevated rounded-3xl flex justify-around py-2">
                    {navItems.map((item) => {
                        const active = isActive(item.routeName);
                        const Icon = item.icon;
                        const showBadge = item.showBadge && unreadCount > 0;
                        return (
                            <Link
                                key={item.routeName}
                                href={item.href}
                                className={cn(
                                    'relative flex flex-col items-center gap-0.5 px-3 py-1.5 rounded-2xl text-[10px] transition-all duration-200',
                                    'focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-neon focus-visible:ring-offset-2 focus-visible:ring-offset-bg-elevated',
                                    active
                                        ? 'text-accent-neon'
                                        : 'text-text-secondary hover:text-text-primary',
                                )}
                                aria-current={active ? 'page' : undefined}
                            >
                                <div className="relative">
                                    <Icon className="h-5 w-5" aria-hidden="true" />
                                    {showBadge && (
                                        <span
                                            className="absolute -top-1 -right-1.5 inline-flex items-center justify-center min-w-[1rem] h-4 px-1 rounded-full text-[9px] font-semibold bg-accent-neon text-bg-base tabular-nums"
                                            aria-label={`${unreadCount} unread notifications`}
                                        >
                                            {unreadCount > 9 ? '9+' : unreadCount}
                                        </span>
                                    )}
                                </div>
                                <span>{item.label}</span>
                            </Link>
                        );
                    })}
                </div>
            </nav>

            <ToastContainer />
        </div>
    );
}
