import { useState } from 'react';
import MenuDietaryFields, {
    type MenuDietaryData,
} from '@/components/menu-dietary-fields';
import { Button } from '@/components/ui/button';

export default function RecipeCatalogueDietary({
    initial,
    onApply,
}: {
    initial: MenuDietaryData;
    onApply: (value: MenuDietaryData) => void;
}) {
    const [data, setData] = useState(initial);
    return (
        <details className="my-5 rounded-xl border p-4">
            <summary className="cursor-pointer font-medium">
                Saison, choix alimentaires et allergènes
            </summary>
            <div className="mt-4 space-y-3">
                <MenuDietaryFields
                    data={data}
                    planning={false}
                    onChange={(value) =>
                        setData((previous) => ({ ...previous, ...value }))
                    }
                />
                <div className="flex flex-wrap gap-3">
                    <Button type="button" onClick={() => onApply(data)}>
                        Appliquer ces choix
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            const cleared = {
                                seasonal: false,
                                variety: 'flexible',
                                diets: [],
                                allergens: [],
                            };
                            setData(cleared);
                            onApply(cleared);
                        }}
                    >
                        Effacer ces choix
                    </Button>
                </div>
            </div>
        </details>
    );
}
