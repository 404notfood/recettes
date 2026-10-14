<?php

namespace App\Services\Menus;

use App\Models\MealPlan;
use Illuminate\Support\Str;

class ShoppingList
{
    public function __construct(private ShoppingCategories $categories) {}

    /**
     * @return list<array{name:string,unit:string,quantity:float|null,occurrences:int,category:string}>
     */
    public function forPlan(MealPlan $plan): array
    {
        $items = [];
        foreach ($plan->meals as $meal) {
            foreach ($meal->snapshot as $recipe) {
                $factor = $plan->people / $recipe['servings'];
                foreach ($recipe['ingredients'] as $ingredient) {
                    $unit = $ingredient['unit'] ?? '';
                    $structured = isset($ingredient['quantity']);
                    $key = Str::lower(Str::ascii(trim($ingredient['name']))).'|'.$unit.'|'.($structured ? 'quantity' : 'unknown');
                    $items[$key] ??= ['name' => $ingredient['name'], 'unit' => $unit, 'quantity' => $structured ? 0 : null, 'occurrences' => 0, 'category' => $this->categories->classify($ingredient['name'])];
                    if ($structured) {
                        $items[$key]['quantity'] += (float) $ingredient['quantity'] * $factor;
                    }
                    $items[$key]['occurrences']++;
                }
            }
        }
        foreach ($items as &$item) {
            if ($item['quantity'] !== null) {
                $item['quantity'] = $item['unit'] === 'pièce' ? ceil($item['quantity']) : round($item['quantity'], 2);
            }
        }
        unset($item);
        usort($items, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $items;
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @return list<array{category:string,label:string,items:list<array<string,mixed>>}>
     */
    public function grouped(array $items): array
    {
        return $this->categories->group($items);
    }
}
