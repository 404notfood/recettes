<?php

namespace App\Services\Menus;

use App\Services\Menus\PlanningRecipe as Recipe;
use Illuminate\Support\Str;

class RecipeCriteria
{
    public function __construct(private DietaryRules $dietary = new DietaryRules, private SeasonalProduce $seasonal = new SeasonalProduce) {}

    public const FOOD_GROUPS = ['vegetables', 'starch', 'protein', 'fruit', 'dairy'];

    public const PROTEINS = ['fish', 'poultry', 'eggs', 'legumes', 'red_meat', 'plant', 'dairy'];

    public function classified(Recipe $recipe): bool
    {
        if (! is_array($recipe->food_groups) || ! $recipe->food_groups
            || array_diff($recipe->food_groups, self::FOOD_GROUPS)) {
            return false;
        }
        if ($recipe->course_type === 'main' && (! in_array($recipe->protein_source, self::PROTEINS, true)
            || ! in_array('protein', $recipe->food_groups, true))) {
            return false;
        }
        if ($recipe->course_type === 'starter' && ! in_array('vegetables', $recipe->food_groups, true)) {
            return false;
        }
        if ($recipe->course_type === 'dessert') {
            if (! in_array($recipe->dessert_profile, ['fruit', 'dairy', 'treat'], true)) {
                return false;
            }
            if ($recipe->dessert_profile !== 'treat' && ! in_array($recipe->dessert_profile, $recipe->food_groups, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string,mixed>  $criteria
     */
    public function eligible(Recipe $recipe, array $criteria, ?string $date = null): bool
    {
        if (! in_array($recipe->course_type, ['starter', 'main', 'dessert'], true)
            || ! $recipe->servings || ! $recipe->total_minutes || ! $recipe->difficulty
            || $recipe->difficulty > $criteria['difficulty'] || $recipe->ingredients->isEmpty()) {
            return false;
        }
        if (! $this->classified($recipe)) {
            return false;
        }
        if (($criteria['vegetarian'] ?? false) && $recipe->vegetarian !== true) {
            return false;
        }
        if (($criteria['vegetarian'] ?? false) && in_array($recipe->protein_source, ['fish', 'poultry', 'red_meat'], true)) {
            return false;
        }

        return ! $this->containsExcluded($recipe, $criteria['excluded'] ?? [])
            && $this->dietary->eligible($recipe, $criteria)
            && (! ($criteria['seasonal'] ?? false) || $this->seasonal->eligible($recipe, $date ?? $criteria['start_date']));
    }

    /**
     * @param  array<string,Recipe>  $selected
     * @param  array<string,mixed>  $criteria
     */
    public function balanced(array $selected, array $criteria = []): bool
    {
        if (isset($selected['main']) && ($criteria['variety'] ?? 'flexible') === 'varied' && array_diff(['vegetables', 'starch', 'protein'], $selected['main']->food_groups ?? [])) {
            return false;
        }
        if (! isset($selected['starter'], $selected['main'])) {
            return true;
        }
        $groups = array_unique([...($selected['starter']->food_groups ?? []), ...($selected['main']->food_groups ?? [])]);

        return ! array_diff(['vegetables', 'starch', 'protein'], $groups);
    }

    /**
     * @param  array<string,int>  $counts
     * @param  array<string,mixed>  $criteria
     */
    public function allowedByWeek(Recipe $recipe, array $counts, int $weekMeals, array $criteria = []): bool
    {
        if (($criteria['variety'] ?? 'flexible') === 'varied' && filled($recipe->title) && ($counts['recipe:'.$this->fingerprint($recipe)] ?? 0) >= 1) {
            return false;
        }
        if ($recipe->course_type === 'dessert' && $recipe->dessert_profile === 'treat'
            && ($counts['treat'] ?? 0) >= ($weekMeals < 3 ? 1 : 2)) {
            return false;
        }
        if ($recipe->course_type === 'main') {
            $sources = $this->dietary->proteinSources($criteria);
            if (! in_array($recipe->protein_source, $sources, true)) {
                return false;
            }
            $needs = $this->weeklyNeeds($counts, $weekMeals, $criteria);
            if ($needs && array_sum($needs) >= $weekMeals - ($counts['meals'] ?? 0) && ($needs[$recipe->protein_source] ?? 0) === 0) {
                return false;
            }
            if (filled($recipe->title)) {
                if (($counts['recipe:'.$this->fingerprint($recipe)] ?? 0) >= 1) {
                    return false;
                }
                $family = $this->family($recipe);
                if (($counts['family:'.$family] ?? 0) >= max(1, (int) ceil($weekMeals / 7))) {
                    return false;
                }
            }
            $used = $counts[$recipe->protein_source] ?? 0;
            if ($recipe->protein_source === 'red_meat' && $used >= ($weekMeals < 3 ? 1 : 2)) {
                return false;
            }
            if ($weekMeals >= 7 && $used >= (int) ceil($weekMeals / min(3, max(1, count($sources))))) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,int> $counts
     * @param  array<string,mixed>  $criteria
     * @return array<string,int>
     */
    public function weeklyNeeds(array $counts, int $weekMeals, array $criteria): array
    {
        if (($criteria['variety'] ?? 'flexible') !== 'varied' || $weekMeals < 7) {
            return [];
        }
        $sources = $this->dietary->proteinSources($criteria);
        $targets = ['legumes' => 2];
        if (in_array('fish', $sources, true)) {
            $targets['fish'] = $weekMeals >= 10 ? 2 : 1;
        }

        foreach ($targets as $source => $target) {
            $targets[$source] = max(0, $target - ($counts[$source] ?? 0));
        }

        return $targets;
    }

    /**
     * @param  list<string>  $excluded
     */
    public function containsExcluded(Recipe $recipe, array $excluded): bool
    {
        $text = Str::lower(Str::ascii($recipe->title.' '.$recipe->description.' '.$recipe->accompaniment.' '.$recipe->ingredients->pluck('name')->implode(' ')));
        foreach ($excluded as $ingredient) {
            if (str_contains($text, Str::lower(Str::ascii(trim($ingredient))))) {
                return true;
            }
        }

        return false;
    }

    public function fingerprint(Recipe $recipe): string
    {
        return Str::lower(Str::ascii(trim($recipe->title)));
    }

    public function family(Recipe $recipe): string
    {
        $title = Str::lower(Str::ascii($recipe->title));
        foreach ([
            'gratin savoyard' => 'croziflette|tartiflette|reblochonnade',
            'couscous' => 'couscous|semoule',
            'pâtes' => 'pates|spaghetti|penne|macaroni|tagliatelle|coquillette|crozets',
            'risotto' => 'risotto', 'soupe' => 'soupe|potage|veloute|bouillon',
            'salade' => 'salade', 'gratin' => 'gratin|tartiflette',
            'omelette' => 'omelette|frittata', 'tarte salée' => 'quiche|tarte',
            'curry' => 'curry|dahl|dal', 'galette' => 'galette|crepe',
            'sandwich' => 'sandwich|croque|burger|wrap', 'pizza' => 'pizza',
        ] as $family => $pattern) {
            if (preg_match('/\b(?:'.$pattern.')\w*\b/u', $title)) {
                return $family;
            }
        }

        return $this->fingerprint($recipe);
    }
}
