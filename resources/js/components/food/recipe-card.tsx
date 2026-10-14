import { Link } from '@inertiajs/react';
import { ArrowUpRight, Clock, Leaf, Star } from 'lucide-react';
import type { Recipe } from '@/types/food';

export function RecipeCard({
    recipe,
    index = 0,
}: {
    recipe: Recipe;
    index?: number;
}) {
    return (
        <article className="recipe-card">
            <Link
                href={`/recettes/${recipe.slug}`}
                className="recipe-photo"
                aria-label={`Voir ${recipe.title}`}
            >
                <img
                    src={recipe.image_url}
                    alt={recipe.title}
                    loading="lazy"
                    decoding="async"
                    width="700"
                    height="520"
                />
                <span className="recipe-category">{recipe.category.name}</span>
                <span className="recipe-card-arrow">
                    <ArrowUpRight size={21} />
                </span>
                {recipe.tags.some(
                    (t) => t === 'vegan' || t === 'vegetarien',
                ) && (
                    <span className="recipe-leaf" title="Cuisine végétarienne">
                        <Leaf size={17} />
                    </span>
                )}
            </Link>
            <div className="recipe-card-meta">
                <span>
                    <Clock size={14} />
                    {recipe.total_time} min
                </span>
                <span className="meta-dot" />
                <span>
                    {recipe.difficulty === 'avance'
                        ? 'Avancé'
                        : recipe.difficulty === 'moyen'
                          ? 'Intermédiaire'
                          : 'Facile'}
                </span>
                {recipe.rating && (
                    <span className="recipe-rating">
                        <Star size={13} fill="currentColor" />
                        {recipe.rating.toLocaleString('fr-FR')}
                    </span>
                )}
            </div>
            <Link href={`/recettes/${recipe.slug}`}>
                <h3>{recipe.title}</h3>
            </Link>
            <div className="recipe-author">
                <span
                    className={`author-avatar avatar-${index % 3}`}
                    aria-hidden="true"
                >
                    {recipe.author_name.charAt(0)}
                </span>
                <span>{recipe.author_name}</span>
            </div>
        </article>
    );
}
