<?php

namespace App\Services\Menus;

use App\Services\Menus\PlanningRecipe as Recipe;
use Illuminate\Support\Str;

class RecipeCriteria
{
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
    public function eligible(Recipe $recipe, array $criteria): bool
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

        return ! $this->containsExcluded($recipe, $criteria['excluded'] ?? []);
    }

    /**
     * @param  array<string,Recipe>  $selected
     */
    public function balanced(array $selected): bool
    {
        if (! isset($selected['starter'], $selected['main'])) {
            return true;
        }
        $groups = array_unique([...($selected['starter']->food_groups ?? []), ...($selected['main']->food_groups ?? [])]);

        return ! array_diff(['vegetables', 'starch', 'protein'], $groups);
    }

    /**
     * @param  array<string,int>  $counts
     */
    public function allowedByWeek(Recipe $recipe, array $counts, int $weekMeals): bool
    {
        if ($recipe->course_type === 'dessert' && $recipe->dessert_profile === 'treat'
            && ($counts['treat'] ?? 0) >= ($weekMeals < 3 ? 1 : 2)) {
            return false;
        }
        if ($recipe->course_type === 'main') {
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
            if ($weekMeals >= 7 && $used >= (int) ceil($weekMeals / 3)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $excluded
     */
    public function containsExcluded(Recipe $recipe, array $excluded): bool
    {
        $text = Str::lower(Str::ascii($recipe->title.' '.$recipe->description.' '.$recipe->ingredients->pluck('name')->implode(' ')));
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
