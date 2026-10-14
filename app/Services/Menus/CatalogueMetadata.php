<?php

namespace App\Services\Menus;

use App\Models\Recipe;
use Illuminate\Support\Str;

class CatalogueMetadata
{
    public const FIELDS = ['course_type', 'total_minutes', 'vegetarian', 'main_ingredient', 'food_groups', 'protein_source', 'dessert_profile', 'planning_estimated'];

    public function __construct(private ShoppingCategories $categories) {}

    /** @return array<string, mixed> */
    public function forRecipe(Recipe $recipe): array
    {
        $data = $recipe->only(self::FIELDS);
        $data['course_type'] ??= match ($recipe->category->slug) {
            'entrees', 'potages' => 'starter', 'douceurs' => 'dessert',
            'plats', 'vegetal', 'terroir', 'japon', 'salades' => 'main', default => null,
        };
        $groups = [];
        $sources = [];
        $meat = false;
        $dominant = null;
        $largest = 0;
        foreach ($recipe->ingredients as $ingredient) {
            $name = Str::lower(Str::ascii($ingredient['name']));
            $category = $this->categories->classify($ingredient['name']);
            $meat = $meat || in_array($category, ['meat', 'fish'], true);
            $quantity = $ingredient['quantity'] ?? null;
            $unit = $ingredient['unit'];
            $grams = match ($unit) {
                'g', 'ml' => $quantity, 'kg', 'l' => $quantity === null ? null : $quantity * 1000, default => null
            };
            $perPerson = $grams === null ? null : $grams / max(1, $recipe->servings);
            // Ignore condiments and small amounts rather than claiming a food group from traces.
            $significant = ($perPerson !== null && $perPerson >= 30) || ($unit === 'pièce' && $quantity !== null && $quantity / max(1, $recipe->servings) >= 0.5);
            if (! $significant) {
                continue;
            }
            $group = match ($category) {
                'vegetables' => preg_match('/pomme.*terre|patate/', $name) ? 'starch' : 'vegetables',
                'fruit' => 'fruit', 'starch' => 'starch', 'dairy' => 'dairy',
                'meat', 'fish', 'eggs', 'legumes' => 'protein', default => null,
            };
            if ($group) {
                $groups[] = $group;
            }
            $source = match ($category) {
                'fish' => 'fish', 'meat' => preg_match('/poulet|dinde|volaille/', $name) ? 'poultry' : 'red_meat',
                'eggs' => 'eggs', 'legumes' => str_contains($name, 'tofu') || str_contains($name, 'tempeh') ? 'plant' : 'legumes',
                'dairy' => preg_match('/fromage|feta|ricotta|mozzarella|parmesan|comte|cheddar/', $name) ? 'dairy' : null,
                default => null,
            };
            if ($source) {
                $sources[] = $source;
                $groups[] = 'protein';
            }
            if (($grams ?? 0) > $largest) {
                $largest = $grams;
                $dominant = $ingredient['name'];
            }
        }
        $data['food_groups'] = $data['food_groups'] ?: array_values(array_unique($groups));
        $data['main_ingredient'] ??= $dominant;
        $data['vegetarian'] ??= ! $meat && (in_array('vegetarien', $recipe->tags ?? [], true) || in_array('vegan', $recipe->tags ?? [], true)) ? true : ($meat ? false : null);
        if ($data['course_type'] === 'main') {
            $data['protein_source'] ??= $sources[0] ?? null;
        }
        if ($data['course_type'] === 'dessert' && ! $data['dessert_profile']) {
            $title = Str::lower(Str::ascii($recipe->title));
            $sweet = collect($recipe->ingredients)->contains(fn ($item) => preg_match('/sucre|miel|sirop|chocolat|caramel|confiture/', Str::lower(Str::ascii($item['name']))) === 1);
            $data['dessert_profile'] = $sweet || preg_match('/tarte|gateau|cake|creme|glace|mousse|crumble|cookie|brownie|biscuit|crepe|gaufre|clafoutis|panna/', $title)
                ? 'treat' : (in_array('fruit', $data['food_groups'], true) ? 'fruit' : (in_array('dairy', $data['food_groups'], true) ? 'dairy' : null));
        }

        return $data;
    }
}
