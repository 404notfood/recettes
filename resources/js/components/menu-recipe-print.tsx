import { useState } from 'react';
import { Printer, Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { courseLabels, type MealPlan, type Course } from '@/types/menus';

export default function MenuRecipePrint({
    plan,
    disabled,
}: {
    plan: MealPlan;
    disabled: boolean;
}) {
    const unique = new Map<
        string,
        { key: string; title: string; course: Course }
    >();
    for (const meal of plan.meals) {
        for (const course of ['starter', 'main', 'dessert'] as Course[]) {
            const recipe = meal.snapshot[course];
            const identity = JSON.stringify(recipe);
            if (!unique.has(identity))
                unique.set(identity, {
                    key: `${meal.id}:${course}`,
                    title: recipe.title,
                    course,
                });
        }
    }
    const recipes = [...unique.values()];
    const [selected, setSelected] = useState<Set<string>>(
        () => new Set(recipes.map((item) => item.key)),
    );
    const count = recipes.filter((item) => selected.has(item.key)).length;
    return (
        <Dialog
            onOpenChange={(open) => {
                if (open) setSelected(new Set(recipes.map((item) => item.key)));
            }}
        >
            <DialogTrigger asChild>
                <Button className="menu-pill" disabled={disabled}>
                    <Printer size={16} /> Imprimer des recettes
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Le carnet à cuisiner</DialogTitle>
                    <DialogDescription>
                        Choisissez vos recettes. Quantités pour {plan.people}{' '}
                        personnes, étapes complètes, sans photo. Les recettes
                        répétées n’apparaissent qu’une fois.
                    </DialogDescription>
                </DialogHeader>
                <form
                    method="get"
                    action={`/menus/${plan.id}/imprimer`}
                    target="_blank"
                    rel="noopener"
                >
                    <div className="mb-4 flex items-center justify-between gap-2">
                        <span className="text-sm">
                            {count} recette(s) choisie(s)
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                                setSelected(
                                    count === recipes.length
                                        ? new Set()
                                        : new Set(
                                              recipes.map((item) => item.key),
                                          ),
                                )
                            }
                        >
                            {count === recipes.length
                                ? 'Tout décocher'
                                : 'Tout sélectionner'}
                        </Button>
                    </div>
                    <div className="max-h-[45vh] space-y-2 overflow-y-auto">
                        {recipes.map((item) => (
                            <label
                                key={item.key}
                                className="flex cursor-pointer items-start gap-3 rounded-xl border p-3"
                            >
                                <input
                                    type="checkbox"
                                    name="recipes[]"
                                    value={item.key}
                                    checked={selected.has(item.key)}
                                    className="mt-1 size-4 shrink-0 accent-[#294b36]"
                                    onChange={() =>
                                        setSelected((current) => {
                                            const next = new Set(current);
                                            if (next.has(item.key))
                                                next.delete(item.key);
                                            else next.add(item.key);
                                            return next;
                                        })
                                    }
                                />
                                <span>
                                    <span className="mb-1 block text-xs text-[#9b704d]">
                                        {courseLabels[item.course]}
                                    </span>
                                    {item.title}
                                </span>
                            </label>
                        ))}
                    </div>
                    <div className="mt-5 flex flex-wrap gap-2">
                        <Button
                            type="submit"
                            name="format"
                            value="html"
                            disabled={count === 0}
                        >
                            <Printer size={16} /> Préparer l’impression
                        </Button>
                        <Button
                            type="submit"
                            name="format"
                            value="pdf"
                            variant="outline"
                            disabled={count === 0}
                        >
                            <Download size={16} /> PDF des recettes choisies
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
