import FoodLayout from '@/layouts/food-layout';
import { Link } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';
export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <FoodLayout>
            <div className="site-container page-content">
                <nav className="account-nav mt-8">
                    <Link href="/dashboard">Mes recettes</Link>
                    <Link href="/mes-favoris">Mes favoris</Link>
                    <Link href="/settings/profile" className="active">
                        Mon compte
                    </Link>
                </nav>
                {breadcrumbs.length > 0 && (
                    <div className="breadcrumbs-food">
                        {breadcrumbs.map((item, i) => (
                            <span key={i}>{item.title}</span>
                        ))}
                    </div>
                )}
                {children}
            </div>
        </FoodLayout>
    );
}
