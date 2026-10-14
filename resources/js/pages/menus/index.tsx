import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import {
    mealLabels,
    statusLabels,
    type MealPlanSummary,
    type MealType,
} from '@/types/menus';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    ChefHat,
    Clock,
    Sparkles,
    Users,
    Zap,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';

const selectClass =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm';
const meals: MealType[] = ['lunch', 'dinner'];

export default function Menus({
    plans,
    aiConfigured,
    catalogueCount,
    today,
}: {
    plans: MealPlanSummary[];
    aiConfigured: boolean;
    catalogueCount: number;
    today: string;
}) {
    const [excludedText, setExcludedText] = useState('');
    const form = useForm({
        start_date: today,
        days: 7,
        people: 2,
        meals: ['lunch', 'dinner'] as MealType[],
        difficulty: 1,
        quick_minutes: 30,
        normal_minutes: 90,
        quick_slots: [] as string[],
        vegetarian: false as boolean,
        excluded: [] as string[],
        preferences: '',
    });
    const dates = Array.from({ length: form.data.days }, (_, index) => {
        const date = new Date(`${form.data.start_date || today}T12:00:00`);
        date.setDate(date.getDate() + index);
        return date.toLocaleDateString('fr-FR', {
            weekday: 'long',
            day: 'numeric',
            month: 'short',
        });
    });
    function toggleSlot(slot: string) {
        form.setData(
            'quick_slots',
            form.data.quick_slots.includes(slot)
                ? form.data.quick_slots.filter((item) => item !== slot)
                : [...form.data.quick_slots, slot],
        );
    }
    function toggleMeal(meal: MealType) {
        const selected = form.data.meals.includes(meal)
            ? form.data.meals.filter((item) => item !== meal)
            : [...form.data.meals, meal];
        form.setData({
            ...form.data,
            meals: selected,
            quick_slots: form.data.quick_slots.filter((slot) =>
                selected.includes(slot.split(':')[1] as MealType),
            ),
        });
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            excluded: [
                ...new Set(
                    excludedText
                        .split(',')
                        .map((item) => item.trim())
                        .filter(Boolean),
                ),
            ],
        }));
        form.post('/menus');
    }

    return (
        <div className="site-container menu-page">
            <Head title="Mes menus">
                <meta name="robots" content="noindex,nofollow" />
            </Head>
            <div className="menu-heading">
                <div>
                    <div className="menu-eyebrow">
                        <Sparkles size={16} /> Votre cuisine, organisée
                    </div>
                    <h1>
                        À table, <em>toute la semaine.</em>
                    </h1>
                    <p className="mt-3 max-w-2xl">
                        Des menus complets pour votre foyer, composés d’abord
                        avec les recettes du site. Les recettes manquantes sont
                        créées et ajoutées automatiquement.
                    </p>
                    <div className="menu-tags">
                        <span className="flex items-center gap-2">
                            <ChefHat size={16} /> {catalogueCount} recettes
                            publiées
                        </span>
                        <span className="flex items-center gap-2">
                            <Clock size={16} /> Entrée, plat et dessert
                        </span>
                    </div>
                </div>
            </div>
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
                <Card>
                    <CardContent className="pt-6">
                        <form onSubmit={submit} className="space-y-7">
                            <div>
                                <h2 className="text-xl font-semibold">
                                    Composer mon planning
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Des repas variés avec légumes, féculents et
                                    protéines, et des desserts surtout fruités
                                    ou laitiers.
                                </p>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="start_date">
                                        Premier jour
                                    </Label>
                                    <Input
                                        id="start_date"
                                        required
                                        type="date"
                                        value={form.data.start_date}
                                        onChange={(event) =>
                                            form.setData(
                                                'start_date',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="days">Durée</Label>
                                    <select
                                        id="days"
                                        className={selectClass}
                                        value={form.data.days}
                                        onChange={(event) => {
                                            const days = Number(
                                                event.target.value,
                                            );
                                            form.setData({
                                                ...form.data,
                                                days,
                                                quick_slots:
                                                    form.data.quick_slots.filter(
                                                        (slot) =>
                                                            Number(
                                                                slot.split(
                                                                    ':',
                                                                )[0],
                                                            ) < days,
                                                    ),
                                            });
                                        }}
                                    >
                                        <option value={1}>1 jour</option>
                                        <option value={7}>7 jours</option>
                                        <option value={14}>14 jours</option>
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="people">
                                        Personnes du foyer
                                    </Label>
                                    <Input
                                        id="people"
                                        type="number"
                                        required
                                        min={1}
                                        max={20}
                                        value={form.data.people}
                                        onChange={(event) =>
                                            form.setData(
                                                'people',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <fieldset>
                                <legend className="mb-2 text-sm font-medium">
                                    Repas à prévoir
                                </legend>
                                <div className="flex flex-wrap gap-5">
                                    {meals.map((meal) => (
                                        <label
                                            key={meal}
                                            className="flex items-center gap-2"
                                        >
                                            <input
                                                type="checkbox"
                                                className="size-4 accent-emerald-700"
                                                checked={form.data.meals.includes(
                                                    meal,
                                                )}
                                                onChange={() =>
                                                    toggleMeal(meal)
                                                }
                                            />
                                            {mealLabels[meal]}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="difficulty">
                                        Difficulté maximale
                                    </Label>
                                    <select
                                        id="difficulty"
                                        className={selectClass}
                                        value={form.data.difficulty}
                                        onChange={(event) =>
                                            form.setData(
                                                'difficulty',
                                                Number(event.target.value),
                                            )
                                        }
                                    >
                                        <option value={1}>Facile</option>
                                        <option value={2}>Intermédiaire</option>
                                        <option value={3}>Avancée</option>
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="quick_minutes">
                                        Repas rapide prêt en
                                    </Label>
                                    <select
                                        id="quick_minutes"
                                        className={selectClass}
                                        value={form.data.quick_minutes}
                                        onChange={(event) => {
                                            const quick_minutes = Number(
                                                event.target.value,
                                            );
                                            form.setData({
                                                ...form.data,
                                                quick_minutes,
                                                normal_minutes: Math.max(
                                                    quick_minutes,
                                                    form.data.normal_minutes,
                                                ),
                                            });
                                        }}
                                    >
                                        {[15, 30, 45].map((minutes) => (
                                            <option
                                                key={minutes}
                                                value={minutes}
                                            >
                                                {minutes} minutes
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="normal_minutes">
                                        Autres repas : maximum
                                    </Label>
                                    <Input
                                        id="normal_minutes"
                                        type="number"
                                        min={form.data.quick_minutes}
                                        max={240}
                                        required
                                        value={form.data.normal_minutes}
                                        onChange={(event) =>
                                            form.setData(
                                                'normal_minutes',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <fieldset className="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
                                <legend className="px-2 font-medium">
                                    <span className="flex items-center gap-2">
                                        <Zap
                                            size={17}
                                            className="text-emerald-700"
                                        />{' '}
                                        Mes créneaux rapides
                                    </span>
                                </legend>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    La limite s’applique au repas complet,
                                    cuisson et repos compris.
                                </p>
                                <div className="mb-4 flex flex-wrap gap-2">
                                    {form.data.meals.map((meal) => (
                                        <Button
                                            key={meal}
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                const slots = dates.map(
                                                    (_, index) =>
                                                        `${index}:${meal}`,
                                                );
                                                const all = slots.every(
                                                    (slot) =>
                                                        form.data.quick_slots.includes(
                                                            slot,
                                                        ),
                                                );
                                                form.setData(
                                                    'quick_slots',
                                                    all
                                                        ? form.data.quick_slots.filter(
                                                              (slot) =>
                                                                  !slots.includes(
                                                                      slot,
                                                                  ),
                                                          )
                                                        : [
                                                              ...new Set([
                                                                  ...form.data
                                                                      .quick_slots,
                                                                  ...slots,
                                                              ]),
                                                          ],
                                                );
                                            }}
                                        >
                                            Tous les{' '}
                                            {meal === 'lunch'
                                                ? 'midis'
                                                : 'soirs'}
                                        </Button>
                                    ))}
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr>
                                                <th className="py-2 text-left">
                                                    Jour
                                                </th>
                                                {meals.map((meal) => (
                                                    <th
                                                        key={meal}
                                                        className="px-4"
                                                    >
                                                        {mealLabels[meal]}{' '}
                                                        rapide
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {dates.map((date, index) => (
                                                <tr
                                                    key={index}
                                                    className="border-t border-emerald-100"
                                                >
                                                    <th
                                                        scope="row"
                                                        className="py-3 text-left font-normal capitalize"
                                                    >
                                                        {date}
                                                    </th>
                                                    {meals.map((meal) => (
                                                        <td
                                                            key={meal}
                                                            className="text-center"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                aria-label={`${date} ${mealLabels[meal]} rapide`}
                                                                className="size-4 accent-emerald-700"
                                                                disabled={
                                                                    !form.data.meals.includes(
                                                                        meal,
                                                                    )
                                                                }
                                                                checked={form.data.quick_slots.includes(
                                                                    `${index}:${meal}`,
                                                                )}
                                                                onChange={() =>
                                                                    toggleSlot(
                                                                        `${index}:${meal}`,
                                                                    )
                                                                }
                                                            />
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </fieldset>
                            <div className="space-y-4">
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        className="size-4 accent-emerald-700"
                                        checked={form.data.vegetarian}
                                        onChange={(event) =>
                                            form.setData(
                                                'vegetarian',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Menus végétariens
                                </label>
                                <div className="space-y-2">
                                    <Label htmlFor="excluded">
                                        Ingrédients à éviter
                                    </Label>
                                    <Input
                                        id="excluded"
                                        value={excludedText}
                                        onChange={(event) =>
                                            setExcludedText(event.target.value)
                                        }
                                        placeholder="Champignon, coriandre… séparés par des virgules"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Filtre sur les ingrédients déclarés.
                                        Vérifiez les fiches et les étiquettes en
                                        cas d’allergie.
                                    </p>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="preferences">
                                        Envies pour les nouvelles recettes
                                    </Label>
                                    <textarea
                                        className="min-h-24 w-full rounded-xl border p-3 text-sm"
                                        id="preferences"
                                        maxLength={1000}
                                        value={form.data.preferences}
                                        onChange={(event) =>
                                            form.setData(
                                                'preferences',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Cuisine méditerranéenne, légumes de saison, desserts fruités…"
                                    />
                                </div>
                            </div>
                            {Object.keys(form.errors).length > 0 && (
                                <div
                                    role="alert"
                                    className="rounded-lg bg-red-50 p-3 text-sm text-red-700"
                                >
                                    {Object.entries(form.errors).map(
                                        ([key, message]) => (
                                            <p key={key}>{message}</p>
                                        ),
                                    )}
                                </div>
                            )}
                            <Button
                                className="w-full bg-emerald-700 hover:bg-emerald-800"
                                disabled={
                                    form.processing ||
                                    form.data.meals.length === 0
                                }
                            >
                                <Sparkles className="mr-2 size-4" />
                                {form.processing
                                    ? 'Préparation du planning…'
                                    : 'Générer mes menus'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                <aside className="space-y-5">
                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            <h2 className="font-semibold">Votre planning</h2>
                            <p className="flex items-center gap-2 text-sm">
                                <CalendarDays size={17} />
                                {form.data.days * form.data.meals.length} repas
                                complets
                            </p>
                            <p className="flex items-center gap-2 text-sm">
                                <Users size={17} />
                                Pour {form.data.people} personnes
                            </p>
                            <p className="flex items-center gap-2 text-sm">
                                <Zap size={17} />
                                {form.data.quick_slots.length} créneaux rapides
                            </p>
                            <p className="border-t pt-3 text-xs text-muted-foreground">
                                Les temps sont des estimations prudentes : les
                                durées des trois recettes sont additionnées. Les
                                quantités de la liste de courses suivent la
                                taille du foyer.
                            </p>
                        </CardContent>
                    </Card>
                    {!aiConfigured && (
                        <div
                            role="status"
                            className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                        >
                            La création automatique attend la configuration du
                            serveur. Les menus peuvent déjà utiliser les
                            recettes suffisamment renseignées.
                        </div>
                    )}
                    <Card>
                        <CardContent className="pt-6">
                            <h2 className="mb-4 font-semibold">
                                Mes derniers plannings
                            </h2>
                            {plans.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Votre premier planning apparaîtra ici.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {plans.map((plan) => (
                                        <Link
                                            key={plan.id}
                                            href={`/menus/${plan.id}`}
                                            className="block rounded-lg border p-3 hover:border-emerald-500"
                                        >
                                            <p className="text-sm font-medium">
                                                {plan.title}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {plan.days} jours ·{' '}
                                                {plan.people} personnes ·{' '}
                                                {statusLabels[plan.status]}
                                            </p>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </aside>
            </div>
        </div>
    );
}
