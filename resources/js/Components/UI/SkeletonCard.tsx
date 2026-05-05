import { cn } from '@/lib/cn';

interface SkeletonCardProps {
    lines?: number;
    className?: string;
}

export function SkeletonCard({ lines = 3, className }: SkeletonCardProps) {
    return (
        <div
            className={cn('glass rounded-2xl p-6 flex flex-col gap-3', className)}
            aria-hidden="true"
        >
            <div className="skeleton h-5 w-1/3" />
            {Array.from({ length: lines }).map((_, i) => (
                <div key={i} className="skeleton h-3" style={{ width: `${100 - i * 10}%` }} />
            ))}
        </div>
    );
}
