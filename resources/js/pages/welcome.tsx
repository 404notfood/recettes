import { Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    ChefHat,
    Clock,
    Heart,
    Leaf,
    Search,
    Sparkles,
    Utensils,
    Coffee,
    CakeSlice,
    Wheat,
    Check,
} from 'lucide-react';
import { useState } from 'react';
import { RecipeCard } from '@/components/food/recipe-card';
import { Seo } from '@/components/food/seo';
import { Button } from '@/components/ui/button';
import { BlurFade } from '@/components/ui/blur-fade';
import { Marquee } from '@/components/ui/marquee';
import type { Category, Recipe } from '@/types/food';

const categoryIcons: Record<string, typeof Leaf> = {
    leaf: Leaf,
    utensils: Utensils,
    coffee: Coffee,
    cake: CakeSlice,
    wheat: Wheat,
};

export default function Welcome({
    recipes,
    categories,
    recipeCount,
}: {
    recipes: Recipe[];
    categories: Category[];
    recipeCount: number;
}) {
    const [search, setSearch] = useState('');
    const featured = recipes[0];
    return (
        <>
            <Seo title="Les bonnes idées finissent dans l’assiette" />
            <section className="site-container home-hero">
                <div className="hero-copy">
                    <div className="eyebrow">
                        <span className="small-sun">✳</span> LE GOÛT DES CHOSES
                        SIMPLES
                    </div>
                    <h1>
                        Les bonnes idées
                        <br />
                        finissent dans
                        <br />
                        <span className="italic-heading">
                            l’assiette<span className="orange-dot">.</span>
                        </span>
                    </h1>
                    <p>
                        Des recettes qui donnent envie.
                        <br />
                        Des gens qui aiment partager.
                        <br />
                        Et ce petit plaisir de dire{' '}
                        <em>« c’est moi qui l’ai fait ».</em>
                    </p>
                    <form
                        className="hero-search"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get(
                                '/recettes',
                                search.trim() ? { q: search.trim() } : {},
                            );
                        }}
                    >
                        <Search size={21} />
                        <input
                            aria-label="Chercher une recette"
                            placeholder="Une envie, un ingrédient, une idée…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button
                            type="submit"
                            className="search-submit"
                            aria-label="Rechercher"
                        >
                            <ArrowRight size={21} />
                        </Button>
                    </form>
                    <div className="hero-suggestions">
                        <span>On vous souffle une idée :</span>
                        <Link href="/recettes?time=30">
                            Vite fait, bien fait
                        </Link>
                        <Link href="/recettes?tag=vegetarien">
                            Tout végétal
                        </Link>
                    </div>
                    <div className="hero-signature">
                        <div className="signature-icon">
                            <Heart size={17} />
                        </div>
                        <span>
                            À découvrir librement.
                            <br />
                            <strong>À partager avec tout le monde.</strong>
                        </span>
                    </div>
                </div>
                <div className="hero-visual">
                    <div className="hero-photo-wrap">
                        <img
                            src={featured?.image_url || '/images/salad.webp'}
                            alt={
                                featured?.title ||
                                'Une assiette généreuse de légumes colorés'
                            }
                            width="700"
                            height="780"
                            fetchPriority="high"
                            className="hero-image"
                        />
                        <div className="hero-photo-shade" />
                        {featured && (
                            <Link
                                href={`/recettes/${featured.slug}`}
                                className="hero-recipe"
                            >
                                <span>LA RECETTE À ESSAYER</span>
                                <h2>{featured.title}</h2>
                                <div>
                                    <span>
                                        <Clock size={15} />
                                        {featured.total_time} min
                                    </span>
                                    {featured.tags.some(
                                        (t) =>
                                            t === 'vegetarien' || t === 'vegan',
                                    ) && (
                                        <span>
                                            <Leaf size={15} />
                                            Végétarien
                                        </span>
                                    )}
                                    <span className="hero-recipe-arrow">
                                        <ArrowUpRight size={24} />
                                    </span>
                                </div>
                            </Link>
                        )}
                    </div>
                    <div className="hero-sticker">
                        <span>CUISINÉ AVEC</span>
                        <Heart size={27} />
                        <span>BEAUCOUP D’ENVIE</span>
                    </div>
                    <div className="hero-floating-note">
                        <span className="note-leaf">
                            <Leaf size={20} />
                        </span>
                        <div>
                            Pas besoin d’être chef.
                            <br />
                            <strong>Juste d’avoir envie.</strong>
                        </div>
                    </div>
                    <span className="hero-caption">
                        UNE BONNE RECETTE. UN BON MOMENT.
                    </span>
                </div>
            </section>
            <div className="flavor-ribbon" aria-hidden="true">
                <Marquee
                    pauseOnHover
                    repeat={3}
                    className="[--duration:50s] [--gap:3rem]"
                >
                    {['Cuisiner', 'Partager', 'Se régaler', 'Recommencer'].map(
                        (word) => (
                            <span key={word} className="ribbon-word">
                                {word}
                                <span>✳</span>
                            </span>
                        ),
                    )}
                </Marquee>
            </div>
            <section className="site-container home-categories" id="envies">
                <div className="section-heading">
                    <div>
                        <div className="eyebrow">
                            À CHAQUE ENVIE, SON ASSIETTE
                        </div>
                        <h2>Qu’est-ce qui vous ferait plaisir ?</h2>
                    </div>
                    <Link href="/recettes" className="text-arrow">
                        Tout explorer <ArrowUpRight size={19} />
                    </Link>
                </div>
                <div className="category-grid">
                    {categories.map((category, i) => {
                        const Icon = categoryIcons[category.icon] || Utensils;
                        return (
                            <Link
                                href={`/recettes?category=${category.slug}`}
                                className={`category-tile category-${i % 5}`}
                                key={category.id}
                            >
                                <span className="category-illustration">
                                    <Icon strokeWidth={1.3} />
                                </span>
                                <span className="category-name">
                                    {category.name}
                                </span>
                                <span className="category-count">
                                    {category.recipes_count ?? 0} recette
                                    {category.recipes_count === 1 ? '' : 's'}
                                </span>
                                <ArrowUpRight
                                    className="category-arrow"
                                    size={18}
                                />
                            </Link>
                        );
                    })}
                </div>
            </section>
            <section className="recipe-section">
                <div className="site-container">
                    <div className="section-heading">
                        <div>
                            <div className="eyebrow">
                                <Sparkles size={14} /> LES BONNES IDÉES DU
                                CARNET
                            </div>
                            <h2>Ça sent déjà bon.</h2>
                            <p>
                                Des recettes généreuses pour remettre un peu de
                                plaisir au menu.
                            </p>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            className="pill-button"
                        >
                            <Link href="/recettes">
                                Toutes les recettes <ArrowUpRight size={17} />
                            </Link>
                        </Button>
                    </div>
                    <div className="recipe-grid">
                        {recipes.map((recipe, i) => (
                            <BlurFade key={recipe.id} delay={i * 0.04} inView>
                                <RecipeCard recipe={recipe} index={i} />
                            </BlurFade>
                        ))}
                    </div>
                    {recipes.length === 0 && (
                        <div className="empty-state">
                            <ChefHat />
                            <h3>Le carnet s’ouvre tout juste.</h3>
                            <p>Partagez la toute première recette.</p>
                            <Link href="/proposer">
                                À vous de jouer <ArrowRight size={17} />
                            </Link>
                        </div>
                    )}
                    <div className="recipe-section-bottom">
                        <span>
                            {recipeCount} bonnes idées, et bientôt la vôtre.
                        </span>
                        <Link href="/recettes">
                            On passe à table ? <ArrowRight size={18} />
                        </Link>
                    </div>
                </div>
            </section>
            <section className="site-container editorial-section">
                <div className="editorial-photo">
                    <img
                        src="/images/bread.webp"
                        alt="Pains de campagne dorés aux graines"
                        loading="lazy"
                        width="700"
                        height="650"
                    />
                    <span className="editorial-tag">
                        LE BONHEUR EST FAIT MAISON
                    </span>
                </div>
                <div className="editorial-copy">
                    <div className="eyebrow">LE PETIT SUPPLÉMENT D’ÂME</div>
                    <h2>
                        La meilleure recette ?<br />
                        <em>Celle qu’on partage.</em>
                    </h2>
                    <p>
                        Le gâteau de votre grand-mère. Les pâtes du dimanche.
                        Votre invention avec les restes du frigo. Ici, toutes
                        les bonnes idées ont leur place.
                    </p>
                    <div className="editorial-benefits">
                        <span>
                            <Check size={17} />
                            Des recettes accessibles à tous
                        </span>
                        <span>
                            <Check size={17} />
                            Un carnet pour garder vos coups de cœur
                        </span>
                        <span>
                            <Check size={17} />
                            La possibilité de proposer, même sans compte
                        </span>
                    </div>
                    <Link href="/a-propos" className="text-arrow">
                        Bienvenue chez Cuistot Club <ArrowUpRight size={19} />
                    </Link>
                </div>
            </section>
            <section className="site-container contribution-banner">
                <div className="contribution-drawing">
                    <ChefHat size={82} strokeWidth={1} />
                    <span>
                        à vous
                        <br />
                        <em>de jouer !</em>
                    </span>
                </div>
                <div>
                    <div className="eyebrow">ON GARDE UNE PLACE POUR VOUS</div>
                    <h2>
                        Vous avez une recette
                        <br />
                        qui fait toujours son petit effet ?
                    </h2>
                    <p>
                        Partagez-la. Quelqu’un, quelque part, va se régaler
                        grâce à vous.
                    </p>
                </div>
                <Button asChild className="pill-button contribution-button">
                    <Link href="/proposer">
                        Partager ma recette <ArrowUpRight size={18} />
                    </Link>
                </Button>
            </section>
        </>
    );
}
