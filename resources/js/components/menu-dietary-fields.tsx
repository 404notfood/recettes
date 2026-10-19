import { allergenLabels, dietLabels } from '@/types/dietary';

export interface MenuDietaryData {
    seasonal: boolean;
    variety: string;
    diets: string[];
    allergens: string[];
}

export default function MenuDietaryFields({
    data,
    onChange,
    planning = true,
}: {
    data: MenuDietaryData;
    onChange: (value: Partial<MenuDietaryData>) => void;
    planning?: boolean;
}) {
    return (
        <div className="space-y-5 rounded-xl border p-4">
            <fieldset className="space-y-3">
                <legend className="mb-2 font-medium">
                    Saison{planning ? ' et diversité' : ''}
                </legend>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={data.seasonal}
                        onChange={(event) =>
                            onChange({ seasonal: event.target.checked })
                        }
                    />
                    Fruits et légumes de saison
                </label>
                <p className="text-xs text-muted-foreground">
                    France métropolitaine, selon la date{' '}
                    {planning ? 'de chaque repas' : 'du jour'}. Conserves et
                    surgelés explicitement nommés autorisés.
                </p>
                {planning && (
                    <label className="block space-y-2 text-sm">
                        <span>Diversité du planning</span>
                        <select
                            className="h-10 w-full rounded-md border bg-background px-3"
                            value={data.variety}
                            onChange={(event) =>
                                onChange({ variety: event.target.value })
                            }
                        >
                            <option value="varied">
                                Varié : aucune recette répétée dans la semaine
                            </option>
                            <option value="flexible">
                                Souple : les entrées et desserts peuvent revenir
                            </option>
                        </select>
                    </label>
                )}
                {planning && (
                    <p className="text-xs text-muted-foreground">
                        Mode varié : légumes, féculents et protéines dans le
                        plat, accompagnement compris. Deux plats de légumes secs
                        sur une semaine d’au moins 7 repas, et du poisson si
                        votre régime l’autorise.
                    </p>
                )}
            </fieldset>
            {(['diets', 'allergens'] as const).map((field) => (
                <fieldset key={field} className="border-t pt-3">
                    <legend className="font-medium">
                        {field === 'diets'
                            ? 'Interdits et choix alimentaires'
                            : 'Allergènes à exclure'}
                    </legend>
                    <div className="mt-2 grid gap-3 sm:grid-cols-2">
                        {Object.entries(
                            field === 'diets' ? dietLabels : allergenLabels,
                        ).map(([key, label]) => (
                            <label
                                key={key}
                                className="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    checked={data[field].includes(key)}
                                    onChange={(event) =>
                                        onChange({
                                            [field]: event.target.checked
                                                ? [...data[field], key]
                                                : data[field].filter(
                                                      (item) => item !== key,
                                                  ),
                                        })
                                    }
                                />
                                {label}
                            </label>
                        ))}
                    </div>
                    <p className="mt-3 text-xs text-muted-foreground">
                        {field === 'diets'
                            ? 'Les exclusions couvrent aussi les sauces et garnitures. Sans lactose : alternatives végétales sans lait. Sans porc et sans alcool ne certifient pas un repas halal ou casher : achats et préparation à vérifier.'
                            : 'Les recettes non analysées sont écartées. Le filtre utilise les ingrédients déclarés et leurs dérivés connus ; vérifiez toujours les étiquettes et les risques de traces ou de contamination croisée.'}
                    </p>
                </fieldset>
            ))}
        </div>
    );
}
