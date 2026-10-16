import { Link, router } from '@inertiajs/react';
import {
    Search,
    SlidersHorizontal,
    X,
    ArrowLeft,
    ArrowRight,
    CookingPot,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { RecipeCard } from '@/components/food/recipe-card';
import { Seo } from '@/components/food/seo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { BlurFade } from '@/components/ui/blur-fade';
import {
    tagLabels,
    type Category,
    type Pagination,
    type Recipe,
} from '@/types/food';

type Filters = {
    source?: string;
    q?: string;
    category?: string;
    difficulty?: string;
    time?: string;
    tag?: string;
    sort?: string;
};
export default function Recipes({
    recipes,
    categories,
    filters,
    menuLibraryAvailable = false,
}: {
    recipes: Pagination<Recipe>;
    categories: Category[];
    filters: Filters;
    menuLibraryAvailable?: boolean;
}) {
    const [search, setSearch] = useState(filters.q || '');
    function apply(key: keyof Filters, value: string) {
        const next = { ...filters, [key]: value };
        router.get(
            '/recettes',
            Object.fromEntries(Object.entries(next).filter(([, v]) => v)),
            { preserveState: true, preserveScroll: true },
        );
    }
    function submit(e: FormEvent) {
        e.preventDefault();
        apply('q', search.trim());
    }
    const active = Object.entries(filters).some(
        ([key, value]) => key !== 'source' && Boolean(value),
    );
    return (
        <>
            <Seo
                title={
                    filters.source === 'menus'
                        ? 'Mes recettes de menus'
                        : 'Toutes les recettes'
                }
                path="/recettes"
                privatePreview={filters.source === 'menus'}
            />
            <section className="catalog-heading site-container">
                <div className="eyebrow">LE CARNET DES BONNES IDÉES</div>
                <h1>
                    À quoi avez-vous <em>envie de goûter ?</em>
                </h1>
                <p>
                    Un dîner express, un grand brunch ou une petite douceur.
                    Trouvez votre prochaine bonne idée.
                </p>
            </section>
            <section className="site-container catalog-content">
                {menuLibraryAvailable && (
                    <div
                        className="category-pills"
                        aria-label="Catalogue de recettes"
                    >
                        <button
                            aria-pressed={!filters.source}
                            className={!filters.source ? 'selected' : ''}
                            onClick={() => apply('source', '')}
                        >
                            Toutes les recettes publiées
                        </button>
                        <button
                            aria-pressed={filters.source === 'menus'}
                            className={
                                filters.source === 'menus' ? 'selected' : ''
                            }
                            onClick={() => apply('source', 'menus')}
                        >
                            Mes recettes de menus
                        </button>
                    </div>
                )}
                {filters.source === 'menus' && (
                    <p>
                        Les recettes créées pour vos menus sont enregistrées
                        dans votre carnet. Vous pouvez les cuisiner
                        immédiatement ; elles restent privées jusqu’à leur
                        validation par l’équipe.
                    </p>
                )}
                <form className="catalog-search" onSubmit={submit}>
                    <Search size={22} />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Rechercher une recette…"
                        aria-label="Rechercher une recette"
                    />
                    <Button type="submit" className="pill-button">
                        Rechercher <ArrowRight size={16} />
                    </Button>
                </form>
                <div className="category-pills">
                    <button
                        className={!filters.category ? 'selected' : ''}
                        onClick={() => apply('category', '')}
                    >
                        Toutes les envies
                    </button>
                    {categories.map((c) => (
                        <button
                            key={c.id}
                            className={
                                filters.category === c.slug ? 'selected' : ''
                            }
                            onClick={() => apply('category', c.slug)}
                        >
                            {c.name}
                            {!filters.source && <span>{c.recipes_count}</span>}
                        </button>
                    ))}
                </div>
                <div className="catalog-filters">
                    <span>
                        <SlidersHorizontal size={16} />
                        Affiner l’envie
                    </span>
                    <select
                        aria-label="Temps total"
                        value={filters.time || ''}
                        onChange={(e) => apply('time', e.target.value)}
                    >
                        <option value="">Tous les temps</option>
                        <option value="15">15 minutes maximum</option>
                        <option value="30">30 minutes maximum</option>
                        <option value="60">1 heure maximum</option>
                    </select>
                    <select
                        aria-label="Difficulté"
                        value={filters.difficulty || ''}
                        onChange={(e) => apply('difficulty', e.target.value)}
                    >
                        <option value="">Toutes les difficultés</option>
                        <option value="facile">Facile</option>
                        <option value="moyen">Intermédiaire</option>
                        <option value="avance">Avancé</option>
                    </select>
                    <select
                        aria-label="Préférence alimentaire"
                        value={filters.tag || ''}
                        onChange={(e) => apply('tag', e.target.value)}
                    >
                        <option value="">Toutes les préférences</option>
                        {Object.entries(tagLabels).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Trier les recettes"
                        value={filters.sort || 'recent'}
                        onChange={(e) => apply('sort', e.target.value)}
                    >
                        <option value="recent">Les plus récentes</option>
                        <option value="time">Les plus rapides</option>
                        <option value="rating">Les mieux notées</option>
                    </select>
                    {active && (
                        <button
                            className="reset-filters"
                            onClick={() => {
                                setSearch('');
                                router.get(
                                    '/recettes',
                                    filters.source
                                        ? { source: filters.source }
                                        : {},
                                );
                            }}
                        >
                            <X size={14} />
                            Effacer
                        </button>
                    )}
                </div>
                <div className="results-label">
                    <strong>{recipes.total}</strong> recette
                    {recipes.total === 1 ? '' : 's'} à découvrir
                    {filters.q && <span> pour « {filters.q} »</span>}
                </div>
                <div className="recipe-grid">
                    {recipes.data.map((r, i) => (
                        <BlurFade key={r.id} delay={i * 0.03}>
                            <RecipeCard recipe={r} index={i} />
                        </BlurFade>
                    ))}
                </div>
                {!recipes.data.length && (
                    <div className="empty-state">
                        <CookingPot />
                        <h2>
                            {filters.source === 'menus'
                                ? 'Aucune recette de menu ici pour le moment.'
                                : 'On n’a pas encore cette recette.'}
                        </h2>
                        <p>
                            {filters.source === 'menus'
                                ? 'Les recettes créées pour compléter vos menus apparaîtront ici dès que leur préparation sera terminée. Si des filtres sont actifs, essayez de les retirer.'
                                : 'Essayez une autre recherche, ou partagez la vôtre !'}
                        </p>
                        <Button asChild className="pill-button">
                            <Link
                                href={
                                    filters.source === 'menus'
                                        ? '/menus'
                                        : '/proposer'
                                }
                            >
                                {filters.source === 'menus'
                                    ? 'Préparer un menu'
                                    : 'Proposer une recette'}
                            </Link>
                        </Button>
                    </div>
                )}
                {recipes.last_page > 1 && (
                    <nav className="pagination" aria-label="Pagination">
                        {recipes.links.map((link, i) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={link.active ? 'active' : ''}
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                >
                                    {i === 0 ? (
                                        <ArrowLeft size={16} />
                                    ) : i === recipes.links.length - 1 ? (
                                        <ArrowRight size={16} />
                                    ) : (
                                        link.label
                                    )}
                                </Link>
                            ) : (
                                <span key={i}>
                                    {i === 0 ? (
                                        <ArrowLeft size={16} />
                                    ) : i === recipes.links.length - 1 ? (
                                        <ArrowRight size={16} />
                                    ) : (
                                        link.label
                                    )}
                                </span>
                            ),
                        )}
                    </nav>
                )}
            </section>
        </>
    );
}
