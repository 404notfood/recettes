import { Link } from '@inertiajs/react';

export function BowlMark({ className = '' }: { className?: string }) {
    return (
        <svg
            className={className}
            viewBox="0 0 40 40"
            fill="none"
            aria-hidden="true"
        >
            <path d="M7 23h26a13 13 0 0 1-26 0Z" fill="currentColor" />
            <path
                d="M13 18c-5-5 5-6 0-12m7 12c-5-5 5-6 0-12m7 12c-5-5 5-6 0-12"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
            />
            <path
                d="M15 37h10"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
            />
        </svg>
    );
}
export function Brand({ light = false }: { light?: boolean }) {
    return (
        <Link
            href="/"
            className={`brand ${light ? 'brand-light' : ''}`}
            aria-label="Cuistot Club, accueil"
        >
            <BowlMark className="brand-mark" />
            <span className="brand-wordmark">
                cuistot<span className="brand-dot">.</span>
                <span className="brand-club">club</span>
            </span>
        </Link>
    );
}
