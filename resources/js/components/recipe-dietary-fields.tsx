import { allergenLabels } from '@/types/dietary';

export default function RecipeDietaryFields({
    allergens,
    accompaniment,
    onChange,
}: {
    allergens: string[] | null;
    accompaniment: string;
    onChange: (value: {
        allergens?: string[] | null;
        accompaniment?: string;
    }) => void;
}) {
    return (
        <fieldset className="space-y-4 rounded-xl border p-4">
            <legend className="px-2 font-medium">
                Accompagnement et allergènes
            </legend>
            <label className="block space-y-2 text-sm">
                <span>Accompagnement du plat (facultatif)</span>
                <input
                    className="h-10 w-full rounded-md border bg-background px-3"
                    maxLength={255}
                    value={accompaniment}
                    onChange={(event) =>
                        onChange({ accompaniment: event.target.value })
                    }
                    placeholder="Riz complet et carottes rôties"
                />
            </label>
            <p className="text-xs text-muted-foreground">
                Ses ingrédients et quantités doivent figurer dans la recette,
                ses étapes dans la préparation.
            </p>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={allergens !== null}
                    onChange={(event) =>
                        onChange({
                            allergens: event.target.checked ? [] : null,
                        })
                    }
                />
                J’ai renseigné les allergènes de tous les ingrédients et sauces
            </label>
            {allergens !== null && (
                <div className="grid gap-3 sm:grid-cols-2">
                    {Object.entries(allergenLabels).map(([key, label]) => (
                        <label
                            key={key}
                            className="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                checked={allergens.includes(key)}
                                onChange={(event) =>
                                    onChange({
                                        allergens: event.target.checked
                                            ? [...allergens, key]
                                            : allergens.filter(
                                                  (item) => item !== key,
                                              ),
                                    })
                                }
                            />
                            {label}
                        </label>
                    ))}
                </div>
            )}
            <p className="text-xs text-muted-foreground">
                Sans déclaration, la recette est écartée des menus avec
                exclusion d’allergènes. Cette déclaration ne garantit pas
                l’absence de traces. Elle est à vérifier après toute
                modification des ingrédients.
            </p>
        </fieldset>
    );
}
