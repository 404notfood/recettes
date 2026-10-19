<?php

namespace App\Services\Menus;

use App\Services\Menus\PlanningRecipe as Recipe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class SeasonalProduce
{
    // Editorial calendar for mainland France, including common storage periods.
    // Calendar source and scope are documented in docs/menus.md.
    private const MONTHS = [
        'asperge' => [4, 5, 6], 'artichaut' => [3, 4, 5, 6, 7, 8, 9],
        'aubergine' => [6, 7, 8, 9], 'betterave' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
        'brocoli' => [6, 7, 8, 9, 10, 11], 'carotte' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
        'celeri' => [1, 2, 3, 9, 10, 11, 12], 'chou fleur' => [1, 2, 3, 4, 9, 10, 11, 12],
        'chou' => [1, 2, 3, 4, 9, 10, 11, 12], 'concombre' => [5, 6, 7, 8, 9, 10],
        'courgette' => [5, 6, 7, 8, 9], 'courge' => [1, 2, 3, 9, 10, 11, 12],
        'potiron' => [1, 2, 3, 9, 10, 11, 12], 'potimarron' => [1, 2, 3, 9, 10, 11, 12],
        'butternut' => [1, 2, 3, 9, 10, 11, 12], 'endive' => [1, 2, 3, 4, 10, 11, 12],
        'epinard' => [1, 2, 3, 4, 5, 9, 10, 11, 12], 'fenouil' => [5, 6, 7, 8, 9, 10, 11],
        'haricot vert' => [6, 7, 8, 9], 'petit pois' => [5, 6, 7], 'feve' => [4, 5, 6, 7],
        'navet' => [1, 2, 3, 4, 5, 6, 9, 10, 11, 12], 'panais' => [1, 2, 3, 10, 11, 12],
        'poireau' => [1, 2, 3, 4, 9, 10, 11, 12], 'poivron' => [6, 7, 8, 9, 10],
        'radis' => [3, 4, 5, 6, 7, 8, 9, 10, 11], 'tomate' => [6, 7, 8, 9, 10],
        'topinambour' => [1, 2, 3, 10, 11, 12], 'mache' => [1, 2, 3, 10, 11, 12],
        'abricot' => [6, 7, 8], 'cerise' => [5, 6, 7], 'fraise' => [4, 5, 6, 7, 8, 9],
        'framboise' => [6, 7, 8, 9], 'figue' => [8, 9, 10], 'kiwi' => [1, 2, 3, 4, 5, 11, 12],
        'melon' => [6, 7, 8, 9], 'pasteque' => [6, 7, 8, 9], 'peche' => [6, 7, 8, 9],
        'nectarine' => [6, 7, 8, 9], 'brugnon' => [6, 7, 8, 9],
        'poire' => [1, 2, 3, 8, 9, 10, 11, 12], 'pomme' => [1, 2, 3, 4, 5, 8, 9, 10, 11, 12],
        'prune' => [7, 8, 9, 10], 'mirabelle' => [8, 9], 'raisin' => [8, 9, 10],
        'rhubarbe' => [4, 5, 6], 'clementine' => [11, 12, 1, 2], 'mandarine' => [11, 12, 1, 2],
        'orange' => [1, 2, 3, 4, 12], 'chataigne' => [9, 10, 11],
    ];

    // Keep obvious fresh summer-fruit mismatches out of seasonal menus.
    // Other produce may extend one month beyond its calendar, allowing some flexibility.
    private const SUMMER_FRUITS = ['abricot', 'cerise', 'fraise', 'framboise', 'melon', 'pasteque', 'peche', 'nectarine', 'brugnon'];

    public function eligible(Recipe $recipe, string $date): bool
    {
        return $this->outOfSeason($recipe, $date) === [];
    }

    public function menuEligible(Recipe $recipe, string $date): bool
    {
        return ! array_intersect($this->outOfSeason($recipe, $date), $this->excludedFreshProduce($date));
    }

    /** @return list<string> */
    public function excludedFreshProduce(string $date): array
    {
        $month = CarbonImmutable::parse($date)->month;

        return array_keys(array_filter(self::MONTHS, function ($months, $produce) use ($month) {
            $tolerance = in_array($produce, self::SUMMER_FRUITS, true) ? 0 : 1;
            foreach ($months as $seasonMonth) {
                $distance = abs($month - $seasonMonth);
                if (min($distance, 12 - $distance) <= $tolerance) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH));
    }

    /** @return list<string> */
    private function outOfSeason(Recipe $recipe, string $date): array
    {
        $month = CarbonImmutable::parse($date)->month;
        $outside = [];
        foreach ($recipe->ingredients as $ingredient) {
            $name = Str::lower(Str::ascii(str_replace('-', ' ', $ingredient->name)));
            if (preg_match('/conserve|surgele|congele|seche|sec\b|compote|confiture|coulis|concentre|farine|huile|jus|vinaigre/', $name)) {
                continue;
            }
            // Long names first, so chou-fleur does not inherit the generic chou calendar.
            foreach (self::MONTHS as $produce => $months) {
                if ($produce === 'chou' && str_contains($name, 'chou fleur')) {
                    continue;
                }
                if ($produce === 'pomme' && preg_match('/pomme[s]? (de terre|de pin)/', $name)) {
                    continue;
                }
                if ($produce === 'poire' && str_contains($name, 'poireau')) {
                    continue;
                }
                $pattern = implode(' ', array_map(fn ($word) => preg_quote($word, '/').'s?', explode(' ', $produce)));
                if (preg_match('/(?<![a-z])'.$pattern.'(?![a-z])/', $name)
                    && ! in_array($month, $months, true)) {
                    $outside[] = $produce;
                }
            }
        }

        return array_values(array_unique($outside));
    }

    /** @return list<string> */
    public function suggestions(string $date): array
    {
        $month = CarbonImmutable::parse($date)->month;

        return array_keys(array_filter(self::MONTHS, fn ($months) => in_array($month, $months, true)));
    }
}
