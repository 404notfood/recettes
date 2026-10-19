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

    public const REJECTION_LABELS = [
        'recipe_metadata_missing' => 'informations de recette incomplètes',
        'difficulty_exceeded' => 'difficulté trop élevée',
        'food_groups_invalid' => 'composition de la recette incomplète',
        'vegetarian_incompatible' => 'recette incompatible avec le choix végétarien',
        'excluded_ingredient' => 'ingrédient à éviter présent',
        'allergens_unknown' => 'allergènes non renseignés',
        'allergen_conflict' => 'allergène exclu identifié',
        'diet_pork' => 'présence de porc ou de charcuterie incompatible',
        'diet_meat' => 'présence de viande incompatible',
        'diet_alcohol' => 'présence d’alcool incompatible',
        'diet_beef' => 'présence de bœuf ou de veau incompatible',
        'vegan_incompatible' => 'recette incompatible avec le choix végétalien',
        'out_of_season' => 'produit frais nettement hors saison',
        'weekly_rotation' => 'rotation hebdomadaire non respectée',
        'recent_repetition' => 'recette ou ingrédient dominant trop récent',
        'weekly_ingredients' => 'ingrédient dominant trop répété dans la semaine',
        'duplicate_title' => 'recette déjà utilisée ou existante',
        'same_meal_vegetables' => 'même légume dominant dans l’entrée et le plat',
        'same_meal_ingredients' => 'ingrédient marquant répété entre plusieurs éléments du repas',
        'celebration_dessert' => 'dessert de fête insuffisamment festif : prévoir un véritable dessert de célébration adapté à l’occasion',
        'seasonal_dish_style' => 'type de plat de saison demandé non respecté : prévoir le gratin ou le mijoté demandé',
        'meal_balance' => 'repas incomplet en légumes, féculents ou protéines',
        'meal_time' => 'temps disponible dépassé',
    ];

    /** @param array<string,mixed> $criteria */
    public function eligible(Recipe $recipe, array $criteria, ?string $date = null): bool
    {
        return $this->violations($recipe, $criteria, $date) === [];
    }

    /** @param array<string,mixed> $criteria
     * @return list<string> Fixed reason codes only; safe for diagnostics and correction feedback.
     */
    public function violations(Recipe $recipe, array $criteria, ?string $date = null): array
    {
        $reasons = [];
        if (! in_array($recipe->course_type, ['starter', 'main', 'dessert'], true)
            || ! $recipe->servings || ! $recipe->total_minutes || ! $recipe->difficulty || $recipe->ingredients->isEmpty()) {
            $reasons[] = 'recipe_metadata_missing';
        }
        if ($recipe->difficulty > $criteria['difficulty']) {
            $reasons[] = 'difficulty_exceeded';
        }
        if (! $this->classified($recipe)) {
            $reasons[] = 'food_groups_invalid';
        }
        if (($criteria['vegetarian'] ?? false) && ($recipe->vegetarian !== true
            || in_array($recipe->protein_source, ['fish', 'poultry', 'red_meat'], true))) {
            $reasons[] = 'vegetarian_incompatible';
        }
        if ($this->containsExcluded($recipe, $criteria['excluded'] ?? [])) {
            $reasons[] = 'excluded_ingredient';
        }
        $reasons = [...$reasons, ...$this->dietary->violations($recipe, $criteria)];
        if (! MenuOptions::suitableDessert($recipe, $criteria)) {
            $reasons[] = 'celebration_dessert';
        }
        if (($criteria['seasonal'] ?? false) && ! $this->seasonal->menuEligible($recipe, $date ?? $criteria['start_date'])) {
            $reasons[] = 'out_of_season';
        }

        return array_values(array_unique($reasons));
    }

    /** @param array<string,mixed> $criteria */
    public function seasonalPenalty(Recipe $recipe, array $criteria, ?string $date = null): int
    {
        return ($criteria['seasonal'] ?? false) && ! $this->seasonal->eligible($recipe, $date ?? $criteria['start_date']) ? 5000 : 0;
    }

    /** @param list<string> $reasons */
    public function rejectionMessage(array $reasons): string
    {
        $labels = array_values(array_intersect_key(self::REJECTION_LABELS, array_flip($reasons)));

        return 'Les recettes proposées ne respectent pas les critères : '.implode(' ; ', $labels ?: ['proposition incompatible']).'. Les restrictions ont été conservées. Vous pouvez relancer la génération.';
    }

    /**
     * @param  array<string,Recipe>  $selected
     * @param  array<string,mixed>  $criteria
     */
    public function balanced(array $selected, array $criteria = []): bool
    {
        if (isset($selected['main']) && (($criteria['variety'] ?? 'flexible') === 'varied' || ! in_array('starter', MenuOptions::courses($criteria), true)) && array_diff(['vegetables', 'starch', 'protein'], $selected['main']->food_groups ?? [])) {
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
            && ! MenuOptions::celebration($criteria) && ($counts['treat'] ?? 0) >= ($weekMeals < 3 ? 1 : 2)) {
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
        $text = Str::lower(Str::ascii($this->dietary->declaredText($recipe)));
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
