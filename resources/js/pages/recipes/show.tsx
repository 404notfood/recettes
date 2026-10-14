import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    ArrowUpRight,
    Check,
    ChefHat,
    Clock,
    Heart,
    Minus,
    Plus,
    Printer,
    RotateCcw,
    Share2,
    Star,
    Timer,
    Users,
    Play,
    Pause,
} from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import { RecipeCard } from '@/components/food/recipe-card';
import { Seo } from '@/components/food/seo';
import { Button } from '@/components/ui/button';
import { tagLabels, type Recipe } from '@/types/food';

type Review = {
    id: number;
    user_id: number;
    author: string;
    content: string;
    rating: number | null;
    reply: string | null;
    date: string;
};
const number = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 });

function KitchenTimer({ initialMinutes }: { initialMinutes: number }) {
    const [minutes, setMinutes] = useState(initialMinutes || 5);
    const [remaining, setRemaining] = useState<number | null>(null);
    const [deadline, setDeadline] = useState<number | null>(null);
    useEffect(() => {
        if (!deadline) return;
        const id = window.setInterval(() => {
            const left = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
            setRemaining(left);
            if (left === 0) {
                setDeadline(null);
                toast.success('La minuterie est terminée. À table !');
            }
        }, 250);
        return () => window.clearInterval(id);
    }, [deadline]);
    const seconds = remaining ?? minutes * 60;
    return (
        <div className="timer">
            <Timer size={18} />
            <label htmlFor="timer-minutes">Minuterie</label>
            <input
                id="timer-minutes"
                type="number"
                min="1"
                max="240"
                value={minutes}
                disabled={Boolean(deadline)}
                onChange={(e) => {
                    setMinutes(
                        Math.max(1, Math.min(240, Number(e.target.value) || 1)),
                    );
                    setRemaining(null);
                }}
            />
            <span>min</span>
            <output aria-live="off">
                {String(Math.floor(seconds / 60)).padStart(2, '0')}:
                {String(seconds % 60).padStart(2, '0')}
            </output>
            <button
                aria-label={
                    deadline
                        ? 'Mettre la minuterie en pause'
                        : 'Démarrer la minuterie'
                }
                onClick={() =>
                    deadline
                        ? setDeadline(null)
                        : setDeadline(
                              Date.now() + (seconds || minutes * 60) * 1000,
                          )
                }
            >
                {deadline ? <Pause size={15} /> : <Play size={15} />}
            </button>
            <button
                aria-label="Réinitialiser la minuterie"
                onClick={() => {
                    setDeadline(null);
                    setRemaining(null);
                }}
            >
                <RotateCcw size={15} />
            </button>
        </div>
    );
}
function ReviewReply({ review, slug }: { review: Review; slug: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reply: review.reply || '' });
    return (
        <div className="mt-3">
            <Button variant="ghost" size="sm" onClick={() => setOpen(!open)}>
                {review.reply ? 'Modifier la réponse' : 'Répondre'}
            </Button>
            {open && (
                <form
                    className="field mt-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(
                            `/recettes/${slug}/avis/${review.id}/reponse`,
                            {
                                preserveScroll: true,
                                onSuccess: () => setOpen(false),
                            },
                        );
                    }}
                >
                    <textarea
                        aria-label="Votre réponse"
                        value={form.data.reply}
                        onChange={(e) => form.setData('reply', e.target.value)}
                        rows={3}
                        required
                    />
                    {form.errors.reply && (
                        <p className="field-error">{form.errors.reply}</p>
                    )}
                    <Button
                        type="submit"
                        size="sm"
                        className="mt-2"
                        disabled={form.processing}
                    >
                        Publier ma réponse
                    </Button>
                </form>
            )}
        </div>
    );
}

export default function RecipeShow({
    recipe,
    isFavorite,
    reviews,
    related,
    canReply,
}: {
    recipe: Recipe;
    isFavorite: boolean;
    reviews: Review[];
    related: Recipe[];
    canReply: boolean;
}) {
    const user = usePage().props.auth.user;
    const ownReview = reviews.find((r) => r.user_id === user?.id);
    const reviewForm = useForm({
        content: ownReview?.content || '',
        rating: ownReview?.rating || 0,
    });
    const [servings, setServings] = useState(recipe.servings);
    const [cooking, setCooking] = useState(false);
    const [activeStep, setActiveStep] = useState(0);
    const [completed, setCompleted] = useState<number[]>([]);
    const steps = recipe.steps || [];
    const ingredients = recipe.ingredients || [];
    const siteUrl = String(usePage().props.siteUrl || 'https://idee.test');
    const schema: Record<string, unknown> = {
        '@context': 'https://schema.org',
        '@type': 'Recipe',
        name: recipe.title,
        description: recipe.description,
        image: recipe.image_url.startsWith('http')
            ? recipe.image_url
            : siteUrl + recipe.image_url,
        author: { '@type': 'Person', name: recipe.author_name },
        recipeYield: `${recipe.servings} portions`,
        prepTime: `PT${recipe.prep_time}M`,
        cookTime: `PT${recipe.cook_time}M`,
        totalTime: `PT${recipe.total_time}M`,
        recipeCategory: recipe.category.name,
        datePublished: recipe.published_at,
        recipeIngredient: ingredients.map((i) =>
            [i.quantity, i.unit, i.name].filter(Boolean).join(' '),
        ),
        recipeInstructions: steps.map((text, i) => ({
            '@type': 'HowToStep',
            text,
            position: i + 1,
        })),
        ...(recipe.rating
            ? {
                  aggregateRating: {
                      '@type': 'AggregateRating',
                      ratingValue: recipe.rating,
                      reviewCount: recipe.reviews_count,
                  },
              }
            : {}),
    };
    function toggleStep(i: number) {
        setCompleted((old) =>
            old.includes(i) ? old.filter((n) => n !== i) : [...old, i],
        );
    }
    async function share() {
        try {
            await navigator.clipboard.writeText(window.location.href);
            toast.success('Le lien de la recette est copié.');
        } catch {
            toast.info(
                'Copiez l’adresse de cette page pour partager la recette.',
            );
        }
    }
    function submitReview(e: FormEvent) {
        e.preventDefault();
        reviewForm.transform((d) => ({ ...d, rating: d.rating || null }));
        reviewForm.post(`/recettes/${recipe.slug}/avis`, {
            preserveScroll: true,
        });
    }
    return (
        <>
            <Seo
                title={recipe.title}
                description={recipe.description}
                image={recipe.image_url}
                path={`/recettes/${recipe.slug}`}
                schema={schema}
                privatePreview={recipe.private_preview}
            />
            <article className="site-container recipe-detail">
                {recipe.private_preview && (
                    <p className="menu-notice">
                        Cette recette a été créée pour vos menus. Elle est
                        disponible dans votre compte et attend une validation
                        avant publication.
                    </p>
                )}
                <nav className="breadcrumbs-food" aria-label="Fil d’Ariane">
                    <Link href="/">Cuistot Club</Link>
                    <span>/</span>
                    <Link href="/recettes">Les recettes</Link>
                    <span>/</span>
                    <Link href={`/recettes?category=${recipe.category.slug}`}>
                        {recipe.category.name}
                    </Link>
                </nav>
                <div className="detail-top">
                    <figure className="detail-visual">
                        <div className="detail-image">
                            <img
                                src={recipe.image_url}
                                alt={recipe.title}
                                width="800"
                                height="700"
                                fetchPriority="high"
                            />
                        </div>
                        {recipe.image_credit && (
                            <figcaption className="photo-credit">
                                Photo d’illustration :{' '}
                                <a
                                    href={recipe.image_credit.source}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title={recipe.image_credit.title}
                                >
                                    {recipe.image_credit.author}
                                </a>
                                {' · '}
                                <a
                                    href={recipe.image_credit.license_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {recipe.image_credit.license}
                                </a>
                                {' · image adaptée'}
                            </figcaption>
                        )}
                    </figure>
                    <div className="detail-copy">
                        <div className="eyebrow">
                            {recipe.category.name.toUpperCase()}
                        </div>
                        <h1>{recipe.title}</h1>
                        <p>{recipe.description}</p>
                        <div className="detail-tags">
                            {recipe.tags.map((tag) => (
                                <span key={tag}>{tagLabels[tag] || tag}</span>
                            ))}
                        </div>
                        <div className="detail-author">
                            <span className="author-avatar">
                                {recipe.author_name.charAt(0)}
                            </span>
                            Une recette de {recipe.author_name}
                            {recipe.rating && (
                                <span className="recipe-rating inline-flex items-center gap-1">
                                    <Star size={14} fill="currentColor" />
                                    {recipe.rating} ({recipe.reviews_count})
                                </span>
                            )}
                        </div>
                        <div className="detail-stats">
                            <div>
                                <Clock size={23} />
                                <span>
                                    <strong>{recipe.prep_time} min</strong>
                                    <small>Préparation</small>
                                </span>
                            </div>
                            <div>
                                <Timer size={23} />
                                <span>
                                    <strong>
                                        {recipe.cook_time
                                            ? `${recipe.cook_time} min`
                                            : 'Sans cuisson'}
                                    </strong>
                                    <small>Cuisson</small>
                                </span>
                            </div>
                            <div>
                                <ChefHat size={23} />
                                <span>
                                    <strong>
                                        {recipe.difficulty === 'facile'
                                            ? 'Facile'
                                            : recipe.difficulty === 'moyen'
                                              ? 'Intermédiaire'
                                              : 'Avancé'}
                                    </strong>
                                    <small>Difficulté</small>
                                </span>
                            </div>
                        </div>
                        <div className="detail-actions">
                            <Button
                                className="pill-button"
                                onClick={() => {
                                    setCooking(!cooking);
                                    document
                                        .getElementById('preparation')
                                        ?.scrollIntoView({
                                            behavior: 'smooth',
                                            block: 'start',
                                        });
                                }}
                            >
                                <ChefHat size={16} />
                                {cooking
                                    ? 'Quitter le mode cuisine'
                                    : 'C’est parti, je cuisine'}
                            </Button>
                            {user ? (
                                <Button
                                    variant="outline"
                                    className="pill-button"
                                    aria-pressed={isFavorite}
                                    onClick={() => {
                                        if (isFavorite)
                                            router.delete(
                                                `/recettes/${recipe.slug}/favori`,
                                                { preserveScroll: true },
                                            );
                                        else
                                            router.put(
                                                `/recettes/${recipe.slug}/favori`,
                                                {},
                                                { preserveScroll: true },
                                            );
                                    }}
                                >
                                    <Heart
                                        size={16}
                                        fill={
                                            isFavorite ? 'currentColor' : 'none'
                                        }
                                    />
                                    {isFavorite
                                        ? 'Dans mon carnet'
                                        : 'Garder la recette'}
                                </Button>
                            ) : (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="pill-button"
                                >
                                    <Link href="/register">
                                        <Heart size={16} />
                                        Garder la recette
                                    </Link>
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => window.print()}
                                aria-label="Imprimer la recette"
                            >
                                <Printer size={18} />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={share}
                                aria-label="Partager la recette"
                            >
                                <Share2 size={18} />
                            </Button>
                        </div>
                    </div>
                </div>
                <div className="recipe-body" id="preparation">
                    <aside className="ingredients-panel">
                        <h2>Les ingrédients</h2>
                        <div className="portions">
                            <Users size={15} />
                            <span>
                                Pour {servings} personne
                                {servings > 1 ? 's' : ''}
                            </span>
                            <button
                                aria-label="Diminuer les portions"
                                disabled={servings <= 1}
                                onClick={() =>
                                    setServings((s) => Math.max(1, s - 1))
                                }
                            >
                                <Minus size={13} />
                            </button>
                            <button
                                aria-label="Augmenter les portions"
                                disabled={servings >= 100}
                                onClick={() =>
                                    setServings((s) => Math.min(100, s + 1))
                                }
                            >
                                <Plus size={13} />
                            </button>
                        </div>
                        <div className="ingredient-list">
                            {ingredients.map((item, i) => (
                                <label className="ingredient-check" key={i}>
                                    <input
                                        type="checkbox"
                                        aria-label={`Préparé : ${item.name}`}
                                    />
                                    <span>
                                        {item.quantity ? (
                                            <strong>
                                                {number.format(
                                                    (item.quantity * servings) /
                                                        recipe.servings,
                                                )}{' '}
                                                {item.unit}{' '}
                                            </strong>
                                        ) : null}
                                        {item.name}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <small>
                            Les quantités s’adaptent aux portions. Le temps de
                            cuisson peut varier selon la quantité préparée.
                        </small>
                    </aside>
                    <section className="steps-panel">
                        <div className="cooking-toolbar">
                            <h2 className="text-[34px]">La préparation</h2>
                            <span>
                                {completed.length}/{steps.length} étapes
                                terminées
                            </span>
                        </div>
                        {cooking ? (
                            <div className="cooking-focus">
                                <div className="eyebrow mb-3">
                                    MODE CUISINE · ÉTAPE {activeStep + 1} SUR{' '}
                                    {steps.length}
                                </div>
                                <h3>Un geste à la fois.</h3>
                                <p>{steps[activeStep]}</p>
                                <div className="cooking-navigation">
                                    <Button
                                        variant="outline"
                                        disabled={activeStep === 0}
                                        onClick={() =>
                                            setActiveStep((s) => s - 1)
                                        }
                                    >
                                        <ArrowLeft size={15} />
                                        Précédent
                                    </Button>
                                    <Button
                                        onClick={() => {
                                            setCompleted((old) =>
                                                old.includes(activeStep)
                                                    ? old
                                                    : [...old, activeStep],
                                            );
                                            if (activeStep < steps.length - 1)
                                                setActiveStep((s) => s + 1);
                                            else {
                                                setCooking(false);
                                                toast.success(
                                                    'Tout est prêt. Bon appétit !',
                                                );
                                            }
                                        }}
                                    >
                                        {activeStep === steps.length - 1
                                            ? 'À table !'
                                            : 'Étape suivante'}
                                        <ArrowRight size={15} />
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            steps.map((step, i) => (
                                <div
                                    className={`cooking-step ${completed.includes(i) ? 'complete' : ''}`}
                                    key={i}
                                >
                                    <button
                                        aria-label={`Marquer l’étape ${i + 1} ${completed.includes(i) ? 'non terminée' : 'terminée'}`}
                                        aria-pressed={completed.includes(i)}
                                        onClick={() => toggleStep(i)}
                                    >
                                        {completed.includes(i) ? (
                                            <Check size={17} />
                                        ) : (
                                            String(i + 1).padStart(2, '0')
                                        )}
                                    </button>
                                    <p>{step}</p>
                                </div>
                            ))
                        )}
                        <KitchenTimer initialMinutes={recipe.cook_time} />
                    </section>
                </div>
                <section className="reviews-section">
                    <h2>
                        Et chez vous, <em>c’était comment ?</em>
                    </h2>
                    <p>
                        Une question, une variante, un retour gourmand : la
                        discussion est ouverte.
                    </p>
                    {user ? (
                        <form className="review-form" onSubmit={submitReview}>
                            <div
                                className="rating-buttons"
                                role="group"
                                aria-label="Votre note"
                            >
                                {[1, 2, 3, 4, 5].map((n) => (
                                    <button
                                        type="button"
                                        key={n}
                                        aria-label={`${n} étoile${n > 1 ? 's' : ''}`}
                                        aria-pressed={
                                            reviewForm.data.rating === n
                                        }
                                        onClick={() =>
                                            reviewForm.setData(
                                                'rating',
                                                reviewForm.data.rating === n
                                                    ? 0
                                                    : n,
                                            )
                                        }
                                    >
                                        <Star
                                            size={22}
                                            fill={
                                                reviewForm.data.rating >= n
                                                    ? 'currentColor'
                                                    : 'none'
                                            }
                                        />
                                    </button>
                                ))}
                                <span className="ml-2 self-center text-[10px] text-muted-foreground">
                                    Note facultative
                                </span>
                            </div>
                            <div className="field">
                                <label htmlFor="review-content">
                                    Votre commentaire
                                </label>
                                <textarea
                                    id="review-content"
                                    rows={3}
                                    value={reviewForm.data.content}
                                    onChange={(e) =>
                                        reviewForm.setData(
                                            'content',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    placeholder="Votre petit retour, votre astuce ou votre question…"
                                />
                                {reviewForm.errors.content && (
                                    <p className="field-error">
                                        {reviewForm.errors.content}
                                    </p>
                                )}
                                {reviewForm.errors.rating && (
                                    <p className="field-error">
                                        {reviewForm.errors.rating}
                                    </p>
                                )}
                            </div>
                            <Button
                                type="submit"
                                className="pill-button justify-self-start"
                                disabled={reviewForm.processing}
                            >
                                {ownReview
                                    ? 'Mettre à jour mon avis'
                                    : 'Partager mon avis'}
                                <ArrowUpRight size={16} />
                            </Button>
                        </form>
                    ) : (
                        <div className="review-form">
                            <p className="text-sm">
                                La recette est accessible à tous. Pour laisser
                                un avis et garder vos favoris, rejoignez le
                                carnet.
                            </p>
                            <Button
                                asChild
                                className="pill-button justify-self-start"
                            >
                                <Link href="/register">
                                    Créer mon compte <ArrowUpRight size={16} />
                                </Link>
                            </Button>
                        </div>
                    )}
                    {reviews.map((review) => (
                        <article className="review-item" key={review.id}>
                            <header>
                                <strong>{review.author}</strong>
                                <small>
                                    {review.date}
                                    {review.rating
                                        ? ` · ${review.rating}/5`
                                        : ''}
                                </small>
                            </header>
                            <p>{review.content}</p>
                            {review.reply && (
                                <div className="review-reply">
                                    <strong>
                                        Réponse de l’auteur ou de l’équipe
                                    </strong>
                                    <p>{review.reply}</p>
                                </div>
                            )}
                            {canReply && (
                                <ReviewReply
                                    review={review}
                                    slug={recipe.slug}
                                />
                            )}
                        </article>
                    ))}
                    {!reviews.length && (
                        <p className="mt-6 text-xs text-muted-foreground">
                            Le premier petit mot sera peut-être le vôtre.
                        </p>
                    )}
                </section>
                {related.length > 0 && (
                    <section className="related-section">
                        <div className="section-heading">
                            <div>
                                <div className="eyebrow">
                                    POUR PROLONGER LE PLAISIR
                                </div>
                                <h2>Encore une petite idée ?</h2>
                            </div>
                            <Link href="/recettes" className="text-arrow">
                                Tout le carnet <ArrowUpRight size={18} />
                            </Link>
                        </div>
                        <div className="recipe-grid">
                            {related.map((r, i) => (
                                <RecipeCard recipe={r} index={i} key={r.id} />
                            ))}
                        </div>
                    </section>
                )}
            </article>
        </>
    );
}
