import { Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Camera,
    Check,
    ChefHat,
    Plus,
    Save,
    Send,
    Trash2,
} from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { Seo } from '@/components/food/seo';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { tagLabels, type Category, type Ingredient } from '@/types/food';

type Editable = {
    id: number;
    title: string;
    description: string;
    category_id: number;
    prep_time: number;
    cook_time: number;
    servings: number;
    difficulty: string;
    ingredients: Ingredient[];
    steps: string[];
    tags: string[];
    status: string;
};
type RecipeForm = {
    title: string;
    description: string;
    category_id: string;
    prep_time: string;
    cook_time: string;
    servings: string;
    difficulty: string;
    author_name: string;
    guest_email: string;
    ingredients: { name: string; quantity: string; unit: string }[];
    steps: string[];
    tags: string[];
    image: File | null;
    intent: string;
    website: string;
};

export default function CreateRecipe({
    categories,
    recipe,
}: {
    categories: Category[];
    recipe?: Editable;
}) {
    const user = usePage().props.auth.user;
    const [preview, setPreview] = useState<string | null>(null);
    const form = useForm<RecipeForm>({
        title: recipe?.title || '',
        description: recipe?.description || '',
        category_id: String(recipe?.category_id || ''),
        prep_time: String(recipe?.prep_time || 15),
        cook_time: String(recipe?.cook_time ?? 0),
        servings: String(recipe?.servings || 4),
        difficulty: recipe?.difficulty || 'facile',
        author_name: user?.name || '',
        guest_email: '',
        ingredients: recipe?.ingredients.map((i) => ({
            name: i.name,
            quantity: i.quantity ? String(i.quantity) : '',
            unit: i.unit || '',
        })) || [
            { name: '', quantity: '', unit: '' },
            { name: '', quantity: '', unit: '' },
        ],
        steps: recipe?.steps || [''],
        tags: recipe?.tags || [],
        image: null,
        intent: 'submit',
        website: '',
    });
    const errors = form.errors as Partial<Record<string, string>>;
    const data = form.data;
    useEffect(() => {
        if (!data.image) return;
        const url = URL.createObjectURL(data.image);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [data.image]);
    function submit(e: FormEvent, intent = 'submit') {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            intent,
            ...(recipe ? { _method: 'put' } : {}),
        }));
        form.post(recipe ? `/mes-recettes/${recipe.id}` : '/recettes', {
            forceFormData: true,
            preserveScroll: true,
            onError: () => {
                document
                    .querySelector<HTMLElement>('[aria-invalid="true"]')
                    ?.focus();
            },
        });
    }
    function ingredient(
        index: number,
        key: 'name' | 'quantity' | 'unit',
        value: string,
    ) {
        form.setData(
            'ingredients',
            data.ingredients.map((item, i) =>
                i === index ? { ...item, [key]: value } : item,
            ),
        );
    }
    const completed = [
        Boolean(data.title && data.description && data.category_id),
        data.ingredients.some((i) => i.name.trim()),
        data.steps.some((s) => s.length >= 10),
    ];
    const error = (key: string) =>
        errors[key] ? (
            <p className="field-error" role="alert">
                {errors[key]}
            </p>
        ) : null;
    return (
        <>
            <Seo
                title={
                    recipe
                        ? 'Modifier ma recette'
                        : 'Partager une bonne recette'
                }
                path="/proposer"
            />
            <div className="site-container page-heading">
                <div className="eyebrow">UN PEU DE VOUS DANS LE CARNET</div>
                <h1>
                    {recipe
                        ? 'Une recette, encore '
                        : 'Vos bonnes idées méritent '}
                    <em>{recipe ? 'meilleure.' : 'd’être partagées.'}</em>
                </h1>
                <p>
                    {recipe
                        ? 'Affinez votre recette. Les modifications d’une recette publiée seront relues avant leur mise en ligne.'
                        : 'Pas besoin d’être un grand chef, ni d’avoir un compte. Racontez-nous simplement comment vous faites.'}
                </p>
            </div>
            <div className="site-container page-content form-grid">
                <form onSubmit={(e) => submit(e)} encType="multipart/form-data">
                    <section className="form-section">
                        <div className="form-section-title">
                            <span>01</span>
                            <h2>Faisons les présentations.</h2>
                        </div>
                        <div className="fields-grid">
                            <div className="field field-full">
                                <Label htmlFor="title">
                                    Le nom de votre recette *
                                </Label>
                                <input
                                    id="title"
                                    placeholder="Ex. Les lasagnes du dimanche de mamie"
                                    value={data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    maxLength={150}
                                    aria-invalid={Boolean(errors.title)}
                                    required
                                />
                                {error('title')}
                            </div>
                            <div className="field field-full">
                                <Label htmlFor="description">
                                    Ce qui la rend spéciale *
                                </Label>
                                <textarea
                                    id="description"
                                    rows={3}
                                    placeholder="Une petite histoire, une astuce, ce que vous aimez dans cette recette…"
                                    value={data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    maxLength={2000}
                                    aria-invalid={Boolean(errors.description)}
                                    required
                                />
                                {error('description')}
                            </div>
                            <div className="field">
                                <Label htmlFor="category">
                                    Dans quelle famille ? *
                                </Label>
                                <select
                                    id="category"
                                    value={data.category_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'category_id',
                                            e.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(errors.category_id)}
                                    required
                                >
                                    <option value="">
                                        Choisir une catégorie
                                    </option>
                                    {categories.map((c) => (
                                        <option value={c.id} key={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                                {error('category_id')}
                            </div>
                            <div className="field">
                                <Label htmlFor="difficulty">
                                    Niveau de difficulté
                                </Label>
                                <select
                                    id="difficulty"
                                    value={data.difficulty}
                                    onChange={(e) =>
                                        form.setData(
                                            'difficulty',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="facile">
                                        Facile — tout le monde peut le faire
                                    </option>
                                    <option value="moyen">
                                        Intermédiaire — un petit défi
                                    </option>
                                    <option value="avance">
                                        Avancé — pour les passionnés
                                    </option>
                                </select>
                                {error('difficulty')}
                            </div>
                            <div className="field">
                                <Label htmlFor="prep">
                                    Préparation (minutes) *
                                </Label>
                                <input
                                    id="prep"
                                    type="number"
                                    min="1"
                                    max="1440"
                                    value={data.prep_time}
                                    onChange={(e) =>
                                        form.setData(
                                            'prep_time',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {error('prep_time')}
                            </div>
                            <div className="field">
                                <Label htmlFor="cook">Cuisson (minutes)</Label>
                                <input
                                    id="cook"
                                    type="number"
                                    min="0"
                                    max="1440"
                                    value={data.cook_time}
                                    onChange={(e) =>
                                        form.setData(
                                            'cook_time',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {error('cook_time')}
                            </div>
                            <div className="field">
                                <Label htmlFor="servings">
                                    Pour combien de personnes ? *
                                </Label>
                                <input
                                    id="servings"
                                    type="number"
                                    min="1"
                                    max="100"
                                    value={data.servings}
                                    onChange={(e) =>
                                        form.setData('servings', e.target.value)
                                    }
                                    required
                                />
                                {error('servings')}
                            </div>
                            <div className="field">
                                <span className="field-label">
                                    Les petits plus
                                </span>
                                <div className="tag-choices">
                                    {Object.entries(tagLabels).map(
                                        ([key, label]) => (
                                            <button
                                                type="button"
                                                aria-pressed={data.tags.includes(
                                                    key,
                                                )}
                                                className={
                                                    data.tags.includes(key)
                                                        ? 'selected'
                                                        : ''
                                                }
                                                key={key}
                                                onClick={() =>
                                                    form.setData(
                                                        'tags',
                                                        data.tags.includes(key)
                                                            ? data.tags.filter(
                                                                  (t) =>
                                                                      t !== key,
                                                              )
                                                            : [
                                                                  ...data.tags,
                                                                  key,
                                                              ],
                                                    )
                                                }
                                            >
                                                {data.tags.includes(key) && (
                                                    <Check size={12} />
                                                )}{' '}
                                                {label}
                                            </button>
                                        ),
                                    )}
                                </div>
                                {error('tags')}
                            </div>
                            <div className="field field-full">
                                <span className="field-label">
                                    Une photo qui donne faim
                                </span>
                                <label className="image-upload" htmlFor="image">
                                    {preview ? (
                                        <img
                                            src={preview}
                                            alt="Aperçu de votre recette"
                                        />
                                    ) : (
                                        <>
                                            <Camera size={30} />
                                            <strong>Ajouter votre photo</strong>
                                            <span>
                                                JPG, PNG ou WebP · 5 Mo maximum
                                            </span>
                                        </>
                                    )}
                                    <input
                                        id="image"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        className="sr-only"
                                        onChange={(e) =>
                                            form.setData(
                                                'image',
                                                e.target.files?.[0] || null,
                                            )
                                        }
                                    />
                                </label>
                                <small>
                                    {recipe
                                        ? 'Sans nouvelle photo, l’image actuelle sera conservée.'
                                        : 'Facultatif. Utilisez une photo que vous avez prise ou que vous avez le droit de partager.'}
                                </small>
                                {error('image')}
                            </div>
                        </div>
                    </section>
                    <section className="form-section">
                        <div className="form-section-title">
                            <span>02</span>
                            <h2>Ce qu’il nous faut.</h2>
                        </div>
                        <div className="ingredient-column-labels">
                            <span>Quantité</span>
                            <span>Unité</span>
                            <span>Ingrédient</span>
                        </div>
                        {data.ingredients.map((item, i) => (
                            <div key={i}>
                                <div className="ingredient-row">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        placeholder="250"
                                        aria-label={`Quantité de l’ingrédient ${i + 1}`}
                                        value={item.quantity}
                                        onChange={(e) =>
                                            ingredient(
                                                i,
                                                'quantity',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <input
                                        placeholder="g, ml…"
                                        aria-label={`Unité de l’ingrédient ${i + 1}`}
                                        value={item.unit}
                                        onChange={(e) =>
                                            ingredient(
                                                i,
                                                'unit',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <input
                                        placeholder="Farine, tomates, beurre…"
                                        aria-label={`Ingrédient ${i + 1}`}
                                        value={item.name}
                                        onChange={(e) =>
                                            ingredient(
                                                i,
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            errors[`ingredients.${i}.name`],
                                        )}
                                        required
                                    />
                                    <button
                                        type="button"
                                        className="row-remove"
                                        aria-label={`Retirer l’ingrédient ${i + 1}`}
                                        disabled={data.ingredients.length === 1}
                                        onClick={() =>
                                            form.setData(
                                                'ingredients',
                                                data.ingredients.filter(
                                                    (_, index) => i !== index,
                                                ),
                                            )
                                        }
                                    >
                                        <Trash2 size={16} />
                                    </button>
                                </div>
                                {error(`ingredients.${i}.name`)}
                                {error(`ingredients.${i}.quantity`)}
                            </div>
                        ))}
                        {error('ingredients')}
                        <Button
                            type="button"
                            variant="outline"
                            className="pill-button mt-3"
                            disabled={data.ingredients.length >= 50}
                            onClick={() =>
                                form.setData('ingredients', [
                                    ...data.ingredients,
                                    { name: '', quantity: '', unit: '' },
                                ])
                            }
                        >
                            <Plus size={15} />
                            Ajouter un ingrédient
                        </Button>
                    </section>
                    <section className="form-section">
                        <div className="form-section-title">
                            <span>03</span>
                            <h2>Et maintenant, en cuisine.</h2>
                        </div>
                        {data.steps.map((step, i) => (
                            <div className="step-row" key={i}>
                                <span>{String(i + 1).padStart(2, '0')}</span>
                                <div className="field">
                                    <Label htmlFor={`step-${i}`}>
                                        Étape {i + 1}
                                    </Label>
                                    <textarea
                                        id={`step-${i}`}
                                        rows={3}
                                        placeholder={
                                            i === 0
                                                ? 'Commencez par…'
                                                : 'Ensuite…'
                                        }
                                        value={step}
                                        onChange={(e) =>
                                            form.setData(
                                                'steps',
                                                data.steps.map((s, n) =>
                                                    n === i
                                                        ? e.target.value
                                                        : s,
                                                ),
                                            )
                                        }
                                        required
                                        aria-invalid={Boolean(
                                            errors[`steps.${i}`],
                                        )}
                                    />
                                    {error(`steps.${i}`)}
                                </div>
                                <button
                                    type="button"
                                    className="row-remove"
                                    aria-label={`Retirer l’étape ${i + 1}`}
                                    disabled={data.steps.length === 1}
                                    onClick={() =>
                                        form.setData(
                                            'steps',
                                            data.steps.filter(
                                                (_, n) => n !== i,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 size={16} />
                                </button>
                            </div>
                        ))}
                        {error('steps')}
                        <Button
                            type="button"
                            variant="outline"
                            className="pill-button"
                            disabled={data.steps.length >= 30}
                            onClick={() =>
                                form.setData('steps', [...data.steps, ''])
                            }
                        >
                            <Plus size={15} />
                            Ajouter une étape
                        </Button>
                    </section>
                    {!user && (
                        <section className="form-section">
                            <div className="form-section-title">
                                <span>
                                    <ChefHat size={16} />
                                </span>
                                <h2>Et le cuisinier, c’est vous.</h2>
                            </div>
                            <div className="fields-grid">
                                <div className="field">
                                    <Label htmlFor="author-name">
                                        Votre nom ou prénom *
                                    </Label>
                                    <input
                                        id="author-name"
                                        value={data.author_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'author_name',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="name"
                                        required
                                    />
                                    {error('author_name')}
                                </div>
                                <div className="field">
                                    <Label htmlFor="guest-email">
                                        Votre adresse e-mail *
                                    </Label>
                                    <input
                                        id="guest-email"
                                        type="email"
                                        value={data.guest_email}
                                        onChange={(e) =>
                                            form.setData(
                                                'guest_email',
                                                e.target.value,
                                            )
                                        }
                                        autoComplete="email"
                                        required
                                    />
                                    {error('guest_email')}
                                    <small>
                                        Pour vous contacter si nécessaire. Elle
                                        ne sera jamais affichée sur le site.
                                    </small>
                                </div>
                            </div>
                        </section>
                    )}
                    <div className="honeypot" aria-hidden="true">
                        <label htmlFor="website">Votre site internet</label>
                        <input
                            id="website"
                            tabIndex={-1}
                            autoComplete="off"
                            value={data.website}
                            onChange={(e) =>
                                form.setData('website', e.target.value)
                            }
                        />
                    </div>
                    {error('website')}
                    <div className="form-actions">
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="pill-button"
                        >
                            <Send size={16} />
                            {form.processing
                                ? 'Envoi en cours…'
                                : recipe
                                  ? 'Enregistrer les modifications'
                                  : 'Proposer ma recette'}
                        </Button>
                        {user && recipe?.status !== 'published' && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                className="pill-button"
                                onClick={(e) => submit(e, 'draft')}
                            >
                                <Save size={16} />
                                Garder en brouillon
                            </Button>
                        )}
                        <p>
                            En proposant votre recette, vous autorisez sa
                            publication après validation par notre équipe.{' '}
                            <Link href="/confidentialite">En savoir plus</Link>.
                        </p>
                        {form.progress && (
                            <p>Photo : {form.progress.percentage}% envoyés</p>
                        )}
                    </div>
                </form>
                <aside className="form-aside">
                    <div className="form-aside-card">
                        <ChefHat size={32} strokeWidth={1.4} />
                        <h3 className="mt-4">
                            Une recette,
                            <br />
                            une histoire.
                        </h3>
                        <p>
                            Quelques détails font toute la différence. Des
                            quantités précises, des étapes claires, et votre
                            petite touche personnelle.
                        </p>
                        <ul>
                            {[
                                'Une belle présentation',
                                'Les bons ingrédients',
                                'Les étapes de préparation',
                            ].map((label, i) => (
                                <li key={label}>
                                    <Check
                                        size={16}
                                        style={{
                                            opacity: completed[i] ? 1 : 0.3,
                                        }}
                                    />
                                    {label}
                                </li>
                            ))}
                        </ul>
                        <p>
                            Notre équipe relit chaque proposition avant
                            publication. Votre recette reste la vôtre, signée de
                            votre nom.
                        </p>
                    </div>
                    <p className="form-aside-note">
                        {user ? (
                            'Retrouvez vos propositions et leur statut dans votre carnet.'
                        ) : (
                            <>
                                Envie de garder un carnet et de retrouver vos
                                recettes ?{' '}
                                <Link href="/register">
                                    Créez un compte{' '}
                                    <ArrowUpRight
                                        size={12}
                                        className="inline"
                                    />
                                </Link>
                                .
                            </>
                        )}
                    </p>
                </aside>
            </div>
        </>
    );
}
