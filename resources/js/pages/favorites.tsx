import { Link } from '@inertiajs/react';
import { BookHeart, ArrowUpRight } from 'lucide-react';
import { RecipeCard } from '@/components/food/recipe-card';
import { Seo } from '@/components/food/seo';
import { Button } from '@/components/ui/button';
import type { Recipe } from '@/types/food';

export default function Favorites({ recipes }: { recipes: Recipe[] }) {
    return (
        <>
            <Seo title="Mon carnet de favoris" path="/mes-favoris" />
            <div className="site-container page-heading">
                <div className="eyebrow">
                    LES RECETTES QU’ON A ENVIE DE REFAIRE
                </div>
                <h1>
                    Vos petits <em>coups de cœur.</em>
                </h1>
                <p>
                    Un carnet personnel, à remplir au fil des envies. Vos bonnes
                    idées vous attendent ici.
                </p>
            </div>
            <section className="site-container page-content">
                <nav className="account-nav">
                    <Link href="/dashboard">Mes recettes</Link>
                    <Link href="/mes-favoris" className="active">
                        Mes favoris
                    </Link>
                    <Link href="/settings/profile">Mon compte</Link>
                </nav>
                {recipes.length ? (
                    <div className="recipe-grid">
                        {recipes.map((r, i) => (
                            <RecipeCard recipe={r} index={i} key={r.id} />
                        ))}
                    </div>
                ) : (
                    <div className="empty-state">
                        <BookHeart />
                        <h2>Le début d’un beau carnet.</h2>
                        <p>
                            Sur une fiche recette, cliquez sur « Garder la
                            recette » pour la retrouver ici.
                        </p>
                        <Button asChild className="pill-button">
                            <Link href="/recettes">
                                Trouver ma première envie{' '}
                                <ArrowUpRight size={16} />
                            </Link>
                        </Button>
                    </div>
                )}
            </section>
        </>
    );
}
