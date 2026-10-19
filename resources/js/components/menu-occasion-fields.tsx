import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { courseLabels, type Course } from '@/types/menus';

type Choices = { courses: Course[]; occasion: string; child_age: string };

export default function MenuOccasionFields({
    data,
    occasions,
    onChange,
}: {
    data: Choices;
    occasions: Record<string, string>;
    onChange: (value: Partial<Choices>) => void;
}) {
    return (
        <div className="space-y-5 rounded-xl border p-4">
            <fieldset>
                <legend className="mb-3 font-medium">
                    Que souhaitez-vous préparer ?
                </legend>
                <div className="flex flex-wrap gap-5">
                    {(Object.keys(courseLabels) as Course[]).map((course) => (
                        <label
                            key={course}
                            className="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                className="size-4 accent-emerald-700"
                                checked={data.courses.includes(course)}
                                onChange={(event) =>
                                    onChange({
                                        courses: event.target.checked
                                            ? [...data.courses, course]
                                            : data.courses.filter(
                                                  (item) => item !== course,
                                              ),
                                    })
                                }
                            />
                            {courseLabels[course]}
                        </label>
                    ))}
                </div>
                <p className="mt-3 text-xs text-muted-foreground">
                    Plat seul, entrée + plat, plat + dessert ou les trois :
                    choisissez au moins un élément. Un plat sans entrée comprend
                    aussi ses légumes et son accompagnement.
                </p>
            </fieldset>
            <div className="space-y-2 border-t pt-4">
                <Label htmlFor="occasion">Pour quelle occasion ?</Label>
                <select
                    id="occasion"
                    className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                    value={data.occasion}
                    onChange={(event) =>
                        onChange({
                            occasion: event.target.value,
                            child_age: '',
                        })
                    }
                >
                    {Object.entries(occasions).map(([key, label]) => (
                        <option key={key} value={key}>
                            {label}
                        </option>
                    ))}
                </select>
                <p className="text-xs text-muted-foreground">
                    L’occasion s’applique à tous les repas du planning. Pour un
                    événement ponctuel, choisissez 1 jour. Vos allergies,
                    interdits et temps disponibles restent respectés.
                </p>
            </div>
            {data.occasion === 'child_birthday' && (
                <div className="space-y-2">
                    <Label htmlFor="child_age">
                        Âge de l’enfant (facultatif, de 3 à 17 ans)
                    </Label>
                    <Input
                        id="child_age"
                        type="number"
                        min={3}
                        max={17}
                        step={1}
                        placeholder="Ex. : 8"
                        value={data.child_age}
                        onChange={(event) =>
                            onChange({ child_age: event.target.value })
                        }
                    />
                    <p className="text-xs text-muted-foreground">
                        Saveurs et présentation adaptées à l’âge indiqué. Les
                        recettes sont sans alcool, même cuit. Sélectionnez
                        Dessert si vous souhaitez un gâteau ou un dessert de
                        fête.
                    </p>
                </div>
            )}
        </div>
    );
}
