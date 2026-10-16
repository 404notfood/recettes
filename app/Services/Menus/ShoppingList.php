<?php

namespace App\Services\Menus;

use App\Models\MealPlan;

class ShoppingList
{
    public function __construct(private ShoppingCategories $categories, private ShoppingIngredient $ingredients) {}

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
                    $normalized = $this->ingredients->normalize($ingredient['name'], $ingredient['unit'] ?? '');
                    if ($normalized === null) {
                        continue;
                    }
                    $unit = $normalized['unit'];
                    $structured = isset($ingredient['quantity']);
                    $key = $normalized['key'].'|'.$unit.'|'.($structured ? 'quantity' : 'unknown');
                    $items[$key] ??= ['name' => $normalized['name'], 'unit' => $unit, 'quantity' => $structured ? 0 : null, 'occurrences' => 0, 'category' => $this->categories->classify($normalized['name'])];
                    if ($structured) {
                        $items[$key]['quantity'] += (float) $ingredient['quantity'] * $factor * $normalized['factor'];
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
