<?php

namespace App\Services\Menus;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class SeasonalCuisine
{
    /** @return list<string> */
    public function preferredStyles(string $date): array
    {
        return match (CarbonImmutable::parse($date)->month) {
            10, 11, 12, 1, 2 => ['gratin', 'stew', 'roast'],
            3, 4, 5 => ['roast', 'saute', 'vegetable_tart'],
            6, 7, 8 => ['grill', 'salad', 'stuffed'],
            default => ['roast', 'stuffed', 'stew'],
        };
    }

    public function guidance(string $date): string
    {
        return match (CarbonImmutable::parse($date)->month) {
            10, 11, 12, 1, 2 => 'Cuisine d’automne et d’hiver : varier tartiflette ou gratin avec salade verte, blanquette, bourguignon, daube, pot-au-feu, plats mijotés ou en sauce et rôtis. Adapter aux interdits : tartiflette sans porc si demandé, gratin végétal sans lait si nécessaire, mijoté de légumes secs en végétalien, sauce sans alcool si exclu. Ne pas réduire la saison à des légumes vapeur et des fruits. Inclure réellement les légumes, féculents et protéines avec leurs quantités, même pour un classique accompagné.',
            3, 4, 5 => 'Cuisine de printemps : rôtis, poêlées printanières, tartes salées avec légumes et accompagnements, navarin ou blanquette printanière selon le temps disponible. Varier les textures et les cuissons.',
            6, 7, 8 => 'Cuisine d’été : grillades ou alternatives végétales, salades composées complètes, légumes farcis, ratatouille accompagnée d’une protéine et d’un féculent. Respecter la saison des produits et varier les préparations.',
            default => 'Cuisine de fin d’été : légumes farcis, rôtis, mijotés et poêlées de transition, avec des accompagnements complets.',
        };
    }

    public function style(PlanningRecipe $recipe): ?string
    {
        if ($recipe->course_type !== 'main') {
            return null;
        }
        $text = Str::lower(Str::ascii($recipe->title));
        foreach ([
            'gratin' => 'tartiflette|croziflette|raclette|gratin|hachis parmentier|lasagnes?',
            'stew' => 'blanquette|bourguignon|daube|pot.au.feu|ragout|mijote|civet|carbonade|osso.buco|sauce|curry|tajine|chili|goulash',
            'roast' => 'roti|rotie|rotis|roties|au four',
            'grill' => 'grille|grillee|grilles|grillees|barbecue|brochettes?',
            'salad' => 'salade', 'stuffed' => 'farci|farcis|farcie|farcies',
            'vegetable_tart' => 'quiche|tarte', 'saute' => 'poelee|saute|sautee',
        ] as $style => $pattern) {
            if (preg_match('/\b(?:'.$pattern.')\b/', $text)) {
                return $style;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $criteria
     * @param  array<string,int>  $counts
     * @return list<string>
     */
    public function weeklyNeeds(array $criteria, string $date, array $counts, int $weekMeals): array
    {
        if (empty($criteria['seasonal']) || ($criteria['variety'] ?? 'flexible') !== 'varied' || $weekMeals < 7
            || ! in_array('main', MenuOptions::courses($criteria), true)
            || ! in_array($criteria['occasion'] ?? 'everyday', ['everyday', 'family', 'friends'], true)
            || trim($criteria['preferences'] ?? '') !== '' || ! in_array('gratin', $this->preferredStyles($date), true)) {
            return [];
        }

        return array_values(array_filter(['gratin', 'stew'], fn ($style) => ($counts['style:'.$style] ?? 0) === 0));
    }

    /** @param array<string,mixed> $criteria
     * @param  array<string,int>  $counts
     */
    public function bonus(PlanningRecipe $recipe, array $criteria, string $date, array $counts): int
    {
        if (empty($criteria['seasonal']) || MenuOptions::celebration($criteria)) {
            return 0;
        }
        $style = $this->style($recipe);

        return $style !== null && in_array($style, $this->preferredStyles($date), true)
            ? (int) (3000 / (1 + ($counts['style:'.$style] ?? 0))) : 0;
    }

    public function minimumMinutes(string $style): int
    {
        return $style === 'gratin' ? 35 : 45;
    }
}
