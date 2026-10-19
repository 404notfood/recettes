<?php

namespace App\Services\Menus;

use App\Services\Menus\PlanningRecipe as Recipe;
use Illuminate\Support\Str;

class DietaryRules
{
    public const ALLERGENS = [
        'gluten' => 'Gluten', 'crustaceans' => 'Crustacés', 'eggs' => 'Œufs', 'fish' => 'Poisson',
        'peanuts' => 'Arachides', 'soy' => 'Soja', 'milk' => 'Lait', 'nuts' => 'Fruits à coque',
        'celery' => 'Céleri', 'mustard' => 'Moutarde', 'sesame' => 'Sésame', 'sulphites' => 'Sulfites',
        'lupin' => 'Lupin', 'molluscs' => 'Mollusques',
    ];

    public const DIETS = [
        'no_pork' => 'Sans porc', 'no_alcohol' => 'Sans alcool', 'no_beef' => 'Sans bœuf',
        'pescetarian' => 'Sans viande (poisson autorisé)', 'vegan' => 'Végétalien',
        'gluten_free' => 'Sans gluten', 'lactose_free' => 'Sans lactose',
    ];

    private const ALIASES = [
        'gluten' => ['gluten', 'ble', 'froment', 'orge', 'seigle', 'avoine', 'epeautre', 'kamut', 'triticale', 'boulgour', 'couscous', 'semoule', 'pate', 'spaghetti', 'lasagne', 'ravioli', 'raviole', 'tortellini', 'nouille', 'pain', 'biscotte', 'crouton', 'chapelure', 'farine', 'feuillete', 'biscuit', 'speculoos', 'seitan', 'sauce soja', 'malt'],
        'crustaceans' => ['crustace', 'crevette', 'gambas', 'crabe', 'homard', 'langouste', 'langoustine', 'ecrevisse', 'surimi'],
        'eggs' => ['oeuf', 'egg', 'omelette', 'mayonnaise', 'meringue', 'surimi'],
        'fish' => ['poisson', 'saumon', 'thon', 'cabillaud', 'colin', 'lieu', 'merlu', 'sardine', 'anchois', 'maquereau', 'truite', 'hareng', 'dorade', 'bar', 'sole', 'surimi', 'nuoc mam'],
        'peanuts' => ['arachide', 'cacahuete', 'peanut'],
        'soy' => ['soja', 'soy', 'tofu', 'tempeh', 'edamame', 'miso', 'tamari'],
        'milk' => ['lait', 'lactose', 'lactoserum', 'caseine', 'beurre', 'creme', 'fromage', 'yaourt', 'yogourt', 'emmental', 'comte', 'parmesan', 'mozzarella', 'feta', 'cheddar', 'ricotta', 'mascarpone', 'burrata', 'roquefort', 'chevre', 'ghee', 'skyr', 'babeurre'],
        'nuts' => ['amande', 'noisette', 'noix', 'cajou', 'pecan', 'pistache', 'macadamia', 'praline', 'pesto'],
        'celery' => ['celeri'], 'mustard' => ['moutarde', 'mustard'], 'sesame' => ['sesame', 'tahini', 'tahin'],
        'sulphites' => ['sulfite', 'sulphite', 'e220', 'e221', 'e222', 'e223', 'e224', 'e225', 'e226', 'e227', 'e228', 'vin', 'cidre', 'biere'],
        'lupin' => ['lupin'], 'molluscs' => ['mollusque', 'moule', 'huitre', 'palourde', 'coque', 'coquille saint jacques', 'calamar', 'encornet', 'seiche', 'poulpe', 'bulot', 'bigorneau', 'escargot'],
    ];

    private const PORK = ['porc', 'cochon', 'jambon', 'lard', 'lardon', 'bacon', 'pancetta', 'guanciale', 'saindoux', 'chorizo', 'saucisse', 'saucisson', 'chipolata', 'cervelas', 'andouille', 'andouillette', 'boudin', 'rillette', 'pate de campagne', 'terrine de campagne', 'gelatine', 'coppa', 'mortadelle', 'prosciutto', 'salami', 'pluma'];

    private const MEAT = ['boeuf', 'veau', 'agneau', 'mouton', 'poulet', 'dinde', 'canard', 'oie', 'volaille', 'lapin', 'gibier', 'viande', 'gesier', 'magret', 'foie', 'bouillon de poule', 'fond de veau'];

    private const ALCOHOL = ['alcool', 'vin', 'biere', 'cidre', 'porto', 'rhum', 'cognac', 'armagnac', 'calvados', 'marsala', 'kirsch', 'liqueur', 'champagne', 'saké', 'sake', 'bourguignon'];

    /** @param list<string> $terms */
    public function matches(string $text, array $terms): bool
    {
        $text = Str::lower(Str::ascii($text));
        foreach ($terms as $term) {
            // Match words and common inflections, never lait in laitue or vin in vinaigre.
            if (preg_match('/(?<![a-z])'.preg_quote(Str::ascii($term), '/').'(?:s|es|e|ee|ees)?(?![a-z])/u', $text)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function detectedAllergens(Recipe $recipe): array
    {
        $found = [];
        foreach ($recipe->ingredients as $ingredient) {
            $text = Str::lower(Str::ascii($ingredient->name));
            foreach (self::ALIASES as $key => $terms) {
                // Explicit, unambiguous substitutes are accepted; labels still need checking.
                if ($key === 'gluten' && (str_contains($text, 'sans gluten')
                    || preg_match('/^(farine|pates|nouilles?|semoule) (de |d[\x27 ])?(riz|mais|sarrasin|pois chiche|lentille|quinoa|chataigne|coco)/', $text))) {
                    continue;
                }
                if ($key === 'milk' && preg_match('/^(lait|creme|beurre|yaourt|fromage) ((de |d[\x27 ])?(coco|soja|amande|avoine|riz|cajou|cacahuete|arachide|cacao|noisette|marron|chataigne|sesame)|vegetal)/', $text)) {
                    continue;
                }
                if ($key === 'nuts' && str_contains($text, 'noix de coco')) {
                    $text = str_replace('noix de coco', 'coco', $text);
                }
                if ($this->matches($text, $terms)) {
                    $found[] = $key;
                }
            }
        }

        return array_values(array_unique([...($recipe->allergens ?? []), ...$found]));
    }

    /** @param array<string,mixed> $criteria */
    public function eligible(Recipe $recipe, array $criteria): bool
    {
        return $this->violations($recipe, $criteria) === [];
    }

    /**
     * Keep declared ingredients intact. Only remove explicit absence claims from
     * descriptive text, so "sans porc" is not mistaken for an ingredient.
     */
    public function declaredText(Recipe $recipe): string
    {
        $descriptions = [$recipe->title, $recipe->description, $recipe->accompaniment ?? '', ...($recipe->steps ?? [])];
        $claim = '(?:porc|alcool|viandes?|boeuf|veau|poissons?|gluten|lactose|lait|oeufs?|produits? animaux)';
        $descriptions = array_map(function (string $text) use ($claim) {
            $text = Str::lower(Str::ascii($text));

            return preg_replace('/\bsans\s+(?:de\s+)?'.$claim.'(?:\s*(?:,|ni|et)\s*(?:sans\s+)?(?:de\s+)?'.$claim.')*\b/u', ' ', $text) ?? $text;
        }, $descriptions);

        return implode(' ', $descriptions).' '.$recipe->ingredients->pluck('name')->implode(' ');
    }

    /** @param array<string,mixed> $criteria
     * @return list<string> Fixed reason codes, never recipe text or private preferences.
     */
    public function violations(Recipe $recipe, array $criteria): array
    {
        $reasons = [];
        $diets = $criteria['diets'] ?? [];
        $excluded = $criteria['allergens'] ?? [];
        if (in_array('gluten_free', $diets, true)) {
            $excluded[] = 'gluten';
        }
        // A lactose-free dairy product still contains milk allergens.
        if (in_array('lactose_free', $diets, true)) {
            $excluded[] = 'milk';
        }
        if ($excluded && $recipe->allergens === null) {
            $reasons[] = 'allergens_unknown';
        }
        $detected = $this->detectedAllergens($recipe);
        if (array_intersect($excluded, $detected)) {
            $reasons[] = 'allergen_conflict';
        }
        $text = $this->declaredText($recipe);
        $noMeat = ($criteria['vegetarian'] ?? false) || in_array('vegan', $diets, true) || in_array('pescetarian', $diets, true);
        if ((in_array('no_pork', $diets, true) || $noMeat) && $this->matches($text, self::PORK)) {
            $reasons[] = 'diet_pork';
        }
        if ($noMeat && ($this->matches($text, self::MEAT) || in_array($recipe->protein_source, ['poultry', 'red_meat'], true))) {
            $reasons[] = 'diet_meat';
        }
        if (in_array('no_alcohol', $diets, true) && $this->matches($text, self::ALCOHOL)) {
            $reasons[] = 'diet_alcohol';
        }
        if (in_array('no_beef', $diets, true) && $this->matches($text, ['boeuf', 'veau', 'fond de veau', 'graisse bovine'])) {
            $reasons[] = 'diet_beef';
        }
        if (($criteria['vegetarian'] ?? false) && array_intersect(['fish', 'crustaceans', 'molluscs'], $detected)) {
            $reasons[] = 'vegetarian_incompatible';
        }
        if (in_array('vegan', $diets, true) && ($recipe->vegetarian !== true
            || array_intersect(['milk', 'eggs', 'fish', 'crustaceans', 'molluscs'], $detected)
            || $this->matches($text, ['miel', 'gelatine', 'graisse animale'])
            || in_array($recipe->protein_source, ['fish', 'poultry', 'eggs', 'red_meat', 'dairy'], true))) {
            $reasons[] = 'vegan_incompatible';
        }

        return $reasons;
    }

    /** @param array<string,mixed> $criteria
     * @return list<string>
     */
    public function proteinSources(array $criteria): array
    {
        $sources = RecipeCriteria::PROTEINS;
        $diets = $criteria['diets'] ?? [];
        if (($criteria['vegetarian'] ?? false) || in_array('vegan', $diets, true)) {
            $sources = array_diff($sources, ['fish', 'poultry', 'red_meat']);
        } elseif (in_array('pescetarian', $diets, true)) {
            $sources = array_diff($sources, ['poultry', 'red_meat']);
        }
        foreach (['fish' => 'fish', 'eggs' => 'eggs', 'milk' => 'dairy'] as $allergen => $source) {
            if (in_array($allergen, $criteria['allergens'] ?? [], true)) {
                $sources = array_diff($sources, [$source]);
            }
        }
        if (in_array('vegan', $diets, true)) {
            $sources = array_diff($sources, ['eggs', 'dairy']);
        } elseif (in_array('lactose_free', $diets, true)) {
            $sources = array_diff($sources, ['dairy']);
        }

        return array_values($sources);
    }
}
