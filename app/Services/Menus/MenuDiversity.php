<?php

namespace App\Services\Menus;

use Illuminate\Support\Str;

class MenuDiversity
{
    /** @var \WeakMap<PlanningRecipe,array{title:string,family:string,ingredients:list<string>}> */
    private \WeakMap $profiles;

    public function __construct(private RecipeCriteria $rules)
    {
        $this->profiles = new \WeakMap;
    }

    /** @return array{title:string,family:string,ingredients:list<string>} */
    public function profile(PlanningRecipe $recipe): array
    {
        if (isset($this->profiles[$recipe])) {
            return $this->profiles[$recipe];
        }
        // Seasonings and staples do not make two dishes alike. Look at named produce
        // and substantial quantities, even when the declared main ingredient is meat.
        $text = $this->normalize($recipe->title.' '.$recipe->main_ingredient);
        foreach ($recipe->ingredients as $ingredient) {
            $grams = match ($ingredient->unit) {
                'kg' => (float) $ingredient->quantity * 1000,
                'g', 'ml' => (float) $ingredient->quantity,
                'pièce', 'piece', '' => (float) $ingredient->quantity * 100,
                default => 0,
            };
            if ($grams / max(1, $recipe->servings) >= 40) {
                $text .= ' '.$this->normalize($ingredient->name);
            }
        }
        $ingredients = [];
        foreach ([
            'poireau' => 'poireau', 'carotte' => 'carotte', 'courgette' => 'courgette',
            'concombre' => 'concombre', 'tomate' => 'tomate', 'épinard' => 'epinard',
            'brocoli' => 'brocoli', 'chou-fleur' => 'choux?[ -]fleur', 'chou' => 'chou(?!x?[ -]fleur)',
            'endive' => 'endive', 'betterave' => 'betterave', 'radis' => 'radis',
            'petits pois' => 'petit[s]? pois', 'haricot vert' => 'haricot[s]? vert',
            'courge' => 'courge|potiron|potimarron|butternut', 'panais' => 'panais',
            'aubergine' => 'aubergine', 'poivron' => 'poivron', 'fenouil' => 'fenouil',
            'champignon' => 'champignon', 'asperge' => 'asperge', 'céleri' => 'celeri',
            'artichaut' => 'artichaut', 'navet' => 'navet',
            'olive' => 'olive',
            'pomme' => 'pomme(?!s? de terre)', 'poire' => 'poire(?!au)',
            'banane' => 'banane', 'kiwi' => 'kiwi', 'orange' => 'orange',
            'clémentine' => 'clementine', 'fraise' => 'fraise', 'pêche' => 'peche',
            'abricot' => 'abricot', 'prune' => 'prune', 'raisin' => 'raisin',
        ] as $name => $pattern) {
            if (preg_match('/\b(?:'.$pattern.')[sx]?\b/u', $text)) {
                $ingredients[] = $name;
            }
        }
        if ($recipe->course_type === 'main') {
            foreach ([
                'poulet' => 'poulet', 'dinde' => 'dinde', 'bœuf' => 'boeuf',
                'porc' => 'porc', 'agneau' => 'agneau', 'saumon' => 'saumon',
                'cabillaud' => 'cabillaud', 'thon' => 'thon', 'sardine' => 'sardine',
                'lentille' => 'lentille', 'pois chiche' => 'pois chiche',
                'haricot blanc' => 'haricot[s]? blanc', 'tofu' => 'tofu',
            ] as $name => $pattern) {
                if (preg_match('/\b(?:'.$pattern.')s?\b/u', $text)) {
                    $ingredients[] = $name;
                }
            }
        }

        return $this->profiles[$recipe] = ['title' => $this->rules->fingerprint($recipe), 'family' => $this->rules->family($recipe), 'ingredients' => $ingredients];
    }

    /**
     * @param  array{title:string,family:string,ingredients:list<string>}  $profile
     * @param  array<string,list<array{title:string,family:string,ingredients:list<string>}>>  $recent
     */
    public function allowed(string $course, array $profile, array $recent): bool
    {
        foreach ($recent[$course] ?? [] as $other) {
            if ($profile['title'] === $other['title']
                || ($course === 'main' && $profile['family'] === $other['family'])
                || array_intersect($profile['ingredients'], $other['ingredients'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{title:string,family:string,ingredients:list<string>}  $profile
     * @param  array<string,int>  $counts
     */
    public function allowedByWeek(array $profile, array $counts, int $weekMeals): bool
    {
        foreach ($profile['ingredients'] as $ingredient) {
            if (($counts['ingredient:'.$ingredient] ?? 0) >= max(1, (int) ceil($weekMeals / 7))) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,PlanningRecipe> $recipes */
    public function compatible(array $recipes): bool
    {
        if (! isset($recipes['starter'], $recipes['main'])) {
            return true;
        }

        return ! array_intersect($this->profile($recipes['starter'])['ingredients'], $this->profile($recipes['main'])['ingredients']);
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii($value));
    }
}
