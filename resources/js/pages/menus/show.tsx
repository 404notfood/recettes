import { Head, Link, router, usePoll } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowUpRight,
    CalendarDays,
    Clock,
    Leaf,
    LoaderCircle,
    Lock,
    RefreshCw,
    ShoppingBasket,
    Sun,
    Moon,
    Unlock,
    Users,
    Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import MenuPdfExport from '@/components/menu-pdf-export';
import MenuRecipePrint from '@/components/menu-recipe-print';
import { Button } from '@/components/ui/button';
import { courseLabels, type Course, type MealPlan } from '@/types/menus';

type ShoppingCategory = {
    category: string;
    label: string;
    items: {
        name: string;
        quantity: number | null;
        unit: string;
        occurrences: number;
    }[];
};
const courses: Course[] = ['starter', 'main', 'dessert'];
function date(value: string, options: Intl.DateTimeFormatOptions) {
    return new Date(`${value.slice(0, 10)}T12:00:00`).toLocaleDateString(
        'fr-FR',
        options,
    );
}

export default function MenuShow({
    plan,
    shoppingCategories,
    errors = {},
}: {
    plan: MealPlan;
    shoppingCategories: ShoppingCategory[];
    errors?: Record<string, string>;
}) {
    const busy = ['queued', 'generating'].includes(plan.status);
    const { start, stop } = usePoll(
        4000,
        { only: ['plan', 'shoppingCategories'] },
        { autoStart: false },
    );
    useEffect(() => {
        if (busy) start();
        else stop();
        return stop;
    }, [busy, start, stop]);
    const [week, setWeek] = useState(0);
    const [pending, setPending] = useState(false);
    const [checked, setChecked] = useState<Set<string>>(() => new Set());
    const days = Array.from({ length: plan.days }, (_, index) => {
        const value = new Date(`${plan.start_date.slice(0, 10)}T12:00:00`);
        value.setDate(value.getDate() + index);
        return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;
    });
    const visible = days.slice(week * 7, week * 7 + 7);
    const meals = new Map(
        plan.meals.map((meal) => [
            `${meal.date.slice(0, 10)}:${meal.meal_type}`,
            meal,
        ]),
    );
    function action(
        url: string,
        data: Record<string, string | boolean>,
        method: 'post' | 'patch' = 'post',
    ) {
        setPending(true);
        router[method](url, data, {
            preserveScroll: true,
            onFinish: () => setPending(false),
        });
    }
    return (
        <div className="site-container menu-page">
            <Head title="Mon menu de la semaine">
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            <Link href="/menus" className="menu-back">
                <ArrowLeft size={15} /> Mes menus
            </Link>
            <div className="menu-heading">
                <div>
                    <span className="menu-eyebrow">
                        UN PEU D’ORGANISATION, BEAUCOUP DE GOÛT
                    </span>
                    <h1>
                        On passe <em>à table.</em>
                    </h1>
                    <p>
                        {date(days[0], { day: 'numeric', month: 'long' })} —{' '}
                        {date(days[days.length - 1], {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                        })}
                    </p>
                    <div className="menu-tags">
                        <span>
                            <Users size={14} /> {plan.people} personnes
                        </span>
                        <span>
                            <CalendarDays size={14} /> {plan.days} jours
                        </span>
                        <span>
                            <Leaf size={14} /> Entrée, plat & dessert
                        </span>
                    </div>
                </div>
                <div className="menu-tools">
                    <MenuPdfExport
                        planId={plan.id}
                        disabled={busy || !plan.meals.length}
                    />
                    <MenuRecipePrint
                        plan={plan}
                        disabled={busy || !plan.meals.length}
                    />
                    <Button
                        variant="ghost"
                        className="menu-regenerate"
                        disabled={busy || pending}
                        onClick={() =>
                            action(`/menus/${plan.id}/regenerate`, {})
                        }
                    >
                        <RefreshCw size={14} />{' '}
                        {plan.status === 'failed'
                            ? 'Relancer la préparation'
                            : 'Changer les repas libres'}
                    </Button>
                </div>
            </div>
            {busy && (
                <div role="status" aria-live="polite" className="menu-notice">
                    <LoaderCircle className="size-5 animate-spin" />
                    <span>
                        Vos bonnes idées mijotent… Les recettes du site passent
                        en premier, puis les recettes manquantes sont créées.
                        Vous pouvez revenir plus tard.
                    </span>
                </div>
            )}
            {plan.error_message && (
                <p role="alert" className="menu-error">
                    {plan.error_message}
                </p>
            )}
            {Object.entries(errors).map(([key, message]) => (
                <p role="alert" className="menu-error" key={key}>
                    {message}
                </p>
            ))}
            {plan.meals.length > 0 && (
                <>
                    <div className="menu-weekbar">
                        <div>
                            <span className="menu-eyebrow">
                                LE CARNET DE LA SEMAINE
                            </span>
                            <h2>Sept jours, de bonnes idées.</h2>
                        </div>
                        {plan.days > 7 && (
                            <div
                                className="menu-weeks"
                                aria-label="Choisir la semaine"
                            >
                                {[0, 1].map((value) => (
                                    <button
                                        type="button"
                                        aria-pressed={week === value}
                                        className={
                                            week === value ? 'active' : ''
                                        }
                                        onClick={() => setWeek(value)}
                                        key={value}
                                    >
                                        Semaine {value + 1}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="menu-grid-labels">
                        <span>Au fil des jours</span>
                        <span>
                            <Sun size={15} /> Le midi
                        </span>
                        <span>
                            <Moon size={15} /> Le soir
                        </span>
                    </div>
                    <div className="menu-week-grid">
                        {visible.map((value) => (
                            <section
                                className="menu-day"
                                key={value}
                                aria-label={date(value, {
                                    weekday: 'long',
                                    day: 'numeric',
                                    month: 'long',
                                })}
                            >
                                <div className="menu-day-date">
                                    <span>
                                        {date(value, { weekday: 'long' })}
                                    </span>
                                    <strong>
                                        {date(value, { day: '2-digit' })}
                                    </strong>
                                    <small>
                                        {date(value, { month: 'long' })}
                                    </small>
                                </div>
                                {(['lunch', 'dinner'] as const).map((type) => {
                                    const meal = meals.get(`${value}:${type}`);
                                    return (
                                        <article
                                            key={type}
                                            className={`menu-meal ${meal?.locked ? 'is-locked' : ''}`}
                                        >
                                            <div className="menu-meal-top">
                                                <span className="menu-meal-label">
                                                    {type === 'lunch' ? (
                                                        <Sun size={14} />
                                                    ) : (
                                                        <Moon size={14} />
                                                    )}
                                                    {type === 'lunch'
                                                        ? 'Le midi'
                                                        : 'Le soir'}
                                                </span>
                                                {meal && (
                                                    <>
                                                        <span className="menu-time">
                                                            <Clock size={12} />{' '}
                                                            {meal.total_minutes}{' '}
                                                            min{' '}
                                                            {meal.quick && (
                                                                <Zap
                                                                    size={12}
                                                                    aria-label="Repas rapide"
                                                                />
                                                            )}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            disabled={
                                                                busy || pending
                                                            }
                                                            className="menu-icon-button"
                                                            title={
                                                                meal.locked
                                                                    ? 'Déverrouiller ce repas'
                                                                    : 'Garder ce repas'
                                                            }
                                                            aria-label={
                                                                meal.locked
                                                                    ? 'Déverrouiller ce repas'
                                                                    : 'Garder ce repas'
                                                            }
                                                            onClick={() =>
                                                                action(
                                                                    `/menus/${plan.id}/meals/${meal.id}/lock`,
                                                                    {
                                                                        locked: !meal.locked,
                                                                    },
                                                                    'patch',
                                                                )
                                                            }
                                                        >
                                                            {meal.locked ? (
                                                                <Lock
                                                                    size={14}
                                                                />
                                                            ) : (
                                                                <Unlock
                                                                    size={14}
                                                                />
                                                            )}
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                            {meal ? (
                                                <div className="menu-courses">
                                                    {courses.map((course) => (
                                                        <div
                                                            className="menu-course"
                                                            key={course}
                                                        >
                                                            <div>
                                                                <span className="menu-course-label">
                                                                    {
                                                                        courseLabels[
                                                                            course
                                                                        ]
                                                                    }
                                                                </span>
                                                                <Link
                                                                    href={
                                                                        meal
                                                                            .recipe_links[
                                                                            course
                                                                        ]
                                                                    }
                                                                    className="menu-recipe-link"
                                                                >
                                                                    {
                                                                        meal
                                                                            .snapshot[
                                                                            course
                                                                        ].title
                                                                    }
                                                                    <ArrowUpRight
                                                                        size={
                                                                            13
                                                                        }
                                                                    />
                                                                </Link>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                className="menu-icon-button"
                                                                title={`Changer ${meal.snapshot[course].title}`}
                                                                aria-label={`Changer ${courseLabels[course].toLowerCase()}`}
                                                                disabled={
                                                                    busy ||
                                                                    pending ||
                                                                    meal.locked
                                                                }
                                                                onClick={() =>
                                                                    action(
                                                                        `/menus/${plan.id}/meals/${meal.id}/replace`,
                                                                        {
                                                                            course,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <RefreshCw
                                                                    size={12}
                                                                />
                                                            </button>
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="menu-empty">
                                                    À votre guise.
                                                    <br />
                                                    Ce repas reste libre.
                                                </p>
                                            )}
                                        </article>
                                    );
                                })}
                            </section>
                        ))}
                    </div>
                    <p className="menu-footnote">
                        Cliquez sur une recette pour la cuisiner. Le cadenas
                        garde un repas lors d’une régénération. Repères de
                        composition et temps estimés, sans calcul calorique
                        individuel.
                    </p>
                    <section
                        className="menu-shopping"
                        aria-label="Liste de courses"
                    >
                        <div className="menu-weekbar">
                            <div>
                                <span className="menu-eyebrow">
                                    IL NE MANQUE PLUS QUE LE PANIER
                                </span>
                                <h2>
                                    Les courses, <em>sans les oublis.</em>
                                </h2>
                                <p>
                                    Quantités additionnées pour {plan.people}{' '}
                                    personnes sur tout le planning.
                                </p>
                            </div>
                            <ShoppingBasket size={32} />
                        </div>
                        <div className="menu-shopping-grid">
                            {shoppingCategories.map((group) => (
                                <section
                                    key={group.category}
                                    className="menu-shopping-category"
                                >
                                    <h3>
                                        {group.label}
                                        <span>{group.items.length}</span>
                                    </h3>
                                    <ul>
                                        {group.items.map((item) => {
                                            const key = `${item.name}|${item.unit}|${item.quantity === null ? 'unknown' : 'quantity'}`;
                                            return (
                                                <li key={key}>
                                                    <label
                                                        className={
                                                            checked.has(key)
                                                                ? 'is-checked'
                                                                : ''
                                                        }
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={checked.has(
                                                                key,
                                                            )}
                                                            onChange={() =>
                                                                setChecked(
                                                                    (
                                                                        current,
                                                                    ) => {
                                                                        const next =
                                                                            new Set(
                                                                                current,
                                                                            );
                                                                        if (
                                                                            next.has(
                                                                                key,
                                                                            )
                                                                        )
                                                                            next.delete(
                                                                                key,
                                                                            );
                                                                        else
                                                                            next.add(
                                                                                key,
                                                                            );
                                                                        return next;
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <span>{item.name}</span>
                                                        <small>
                                                            {item.quantity ===
                                                            null
                                                                ? 'À vérifier'
                                                                : `${item.quantity.toLocaleString('fr-FR')} ${item.unit}`}
                                                        </small>
                                                    </label>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </section>
                            ))}
                        </div>
                    </section>
                </>
            )}
        </div>
    );
}
