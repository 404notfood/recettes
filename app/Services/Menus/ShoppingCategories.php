<?php

namespace App\Services\Menus;

use Illuminate\Support\Str;

class ShoppingCategories
{
    public const LABELS = [
        'vegetables' => 'Légumes', 'fruit' => 'Fruits', 'dairy' => 'Laitages',
        'meat' => 'Viandes et volailles', 'fish' => 'Poissons et fruits de mer', 'eggs' => 'Œufs',
        'starch' => 'Céréales et féculents', 'legumes' => 'Légumineuses',
        'pantry' => 'Épicerie', 'herbs' => 'Herbes et épices', 'other' => 'Autres',
    ];

    public function classify(string $name): string
    {
        $name = Str::lower(Str::ascii($name));
        // Preparation notes do not change the aisle of the actual ingredient.
        $name = explode(' pour ', $name, 2)[0];
        if (preg_match('/\b(?:glace|chocolat|sucre)\b/u', $name)) {
            return 'pantry';
        }
        // Compound names take precedence over isolated words (e.g. potato vs apple).
        foreach ([
            'pantry' => 'lait\s+(?:de\s+)?(?:coco|amande|avoine|soja)|beurre\s+(?:de\s+)?(?:cacahuete|arachide)|creme\s+(?:de\s+)?coco|sauce\s+(?:de\s+)?soja|pesto|dashi|miso|mirin|vin\s+(?:blanc|rouge)',
            'vegetables' => 'pommes?\s+de\s+terre|haricots?\s+verts?|petits?\s+pois',
        ] as $category => $pattern) {
            if (preg_match('/\b(?:'.$pattern.')\b/u', $name)) {
                return $category;
            }
        }
        foreach ([
            'vegetables' => 'panais|wakame', 'dairy' => 'reblochon',
            'starch' => 'crozets?|brioche\w*|chataigne\w*', 'herbs' => 'sauge',
        ] as $category => $pattern) {
            if (preg_match('/\b(?:'.$pattern.')\b/u', $name)) {
                return $category;
            }
        }
        $patterns = [
            'dairy' => 'fromage\w*|lait|yaourt\w*|yogourt\w*|beurre|creme|mozzarella|feta|parmesan|ricotta|mascarpone|chevre|brie|camembert|emmental|comte|cheddar',
            'vegetables' => 'betterave\w*|carotte\w*|courgette\w*|tomate\w*|concombre\w*|brocoli\w*|chou\w*|salade\w*|laitue\w*|roquette|cornichon\w*|radis|epinard\w*|aubergine\w*|poivron\w*|oignon\w*|ail|echalote\w*|poireau\w*|celeri\w*|fenouil\w*|potiron\w*|potimarron\w*|courge\w*|champignon\w*|asperge\w*|artichaut\w*|patate\w*|avocat\w*|endive\w*|navet\w*',
            'fruit' => 'pomme\w*|poire\w*|banane\w*|citron\w*|orange\w*|clementine\w*|mandarine\w*|fraise\w*|framboise\w*|myrtille\w*|mure\w*|kiwi\w*|raisin\w*|peche\w*|abricot\w*|prune\w*|cerise\w*|mangue\w*|ananas|melon\w*|pasteque\w*|pamplemousse\w*|fruits?',
            'eggs' => 'oeuf\w*',
            'meat' => 'poulet\w*|dinde\w*|volaille\w*|boeuf|veau|porc|agneau|canard\w*|lapin\w*|jambon\w*|lardon\w*|bacon|saucisse\w*|viande\w*',
            'fish' => 'poisson\w*|saumon\w*|thon|cabillaud\w*|colin\w*|sardine\w*|maquereau\w*|truite\w*|hareng\w*|crevette\w*|moule\w*|huitre\w*|coquille\w*|calamar\w*|anchois',
            'legumes' => 'lentille\w*|pois\s+chiche\w*|haricot\w*|feve\w*|pois\s+casse\w*|tofu|tempeh|soja',
            'starch' => 'riz|pates?|spaghetti\w*|penne|macaroni\w*|boulgour|semoule\w*|couscous|quinoa|avoine|pain\w*|farine\w*|polenta|nouille\w*|sarrasin|ble',
            'herbs' => 'herbe\w*|persil|coriandre|basilic|aneth|origan|menthe|ciboulette|thym|romarin|laurier|epice\w*|cannelle|cumin|curcuma|paprika|poivre|sel|muscade|gingembre|vanille',
            'pantry' => 'huile\w*|vinaigre\w*|moutarde|miel|sucre|sirop|chocolat|cacao|noix|noisette\w*|amande\w*|cacahuete\w*|pistache\w*|graine\w*|bouillon\w*|levure|confiture\w*|biscuit\w*|tahini|mayonnaise|capre\w*|olives?|sauce\w*',
        ];
        foreach ($patterns as $category => $pattern) {
            if (preg_match('/\b(?:'.$pattern.')\b/u', $name)) {
                return $category;
            }
        }

        return 'other';
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @return list<array{category:string,label:string,items:list<array<string,mixed>>}>
     */
    public function group(array $items): array
    {
        $groups = [];
        foreach (self::LABELS as $category => $label) {
            $members = array_values(array_filter($items, fn ($item) => ($item['category'] ?? $this->classify($item['name'])) === $category));
            if ($members) {
                $groups[] = ['category' => $category, 'label' => $label, 'items' => $members];
            }
        }

        return $groups;
    }
}
