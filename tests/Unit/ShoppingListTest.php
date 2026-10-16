<?php

namespace Tests\Unit;

use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Services\Menus\ShoppingCategories;
use App\Services\Menus\ShoppingIngredient;
use App\Services\Menus\ShoppingList;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    public function test_shopping_merges_plural_names_and_compatible_units_without_mixing_preparations(): void
    {
        $plan = new MealPlan(['people' => 4]);
        $plan->setRelation('meals', collect([new PlannedMeal(['snapshot' => ['main' => [
            'servings' => 2, 'ingredients' => [
                ['name' => 'œuf', 'quantity' => 2, 'unit' => ''],
                ['name' => 'œufs', 'quantity' => 4, 'unit' => 'pièces'],
                ['name' => 'banane', 'quantity' => 1, 'unit' => ''],
                ['name' => 'bananes', 'quantity' => 2, 'unit' => 'pièce'],
                ['name' => 'pois chiches cuits', 'quantity' => 100, 'unit' => 'g'],
                ['name' => 'pois chiches cuits égouttés', 'quantity' => 0.2, 'unit' => 'kg'],
                ['name' => 'pois chiches secs', 'quantity' => 100, 'unit' => 'g'],
                ['name' => 'huile', 'quantity' => 20, 'unit' => 'ml'],
                ['name' => 'huile', 'quantity' => 0.01, 'unit' => 'l'],
                ['name' => 'eau', 'quantity' => 800, 'unit' => 'ml'],
                ['name' => 'eau pour le riz', 'quantity' => 400, 'unit' => 'ml'],
                ['name' => 'Sel', 'quantity' => null, 'unit' => ''],
            ],
        ]]])]));
        $items = (new ShoppingList(new ShoppingCategories, new ShoppingIngredient))->forPlan($plan);
        $byName = array_column($items, null, 'name');
        $this->assertCount(6, $items);
        $this->assertSame(12.0, $byName['Œuf']['quantity']);
        $this->assertSame('pièce', $byName['Œuf']['unit']);
        $this->assertSame(6.0, $byName['Banane']['quantity']);
        $this->assertSame(600.0, $byName['Pois chiches cuits']['quantity']);
        $this->assertSame(200.0, $byName['pois chiches secs']['quantity']);
        $this->assertSame(60.0, $byName['huile']['quantity']);
        $this->assertNull($byName['Sel']['quantity']);
    }

    public function test_mass_count_and_unknown_quantities_are_not_combined(): void
    {
        $normalizer = new ShoppingIngredient;
        $this->assertNotSame($normalizer->normalize('pomme', 'g')['unit'], $normalizer->normalize('pommes', 'pièce')['unit']);
        $this->assertNotSame($normalizer->normalize('lentilles sèches', 'g')['key'], $normalizer->normalize('lentilles cuites', 'g')['key']);
        $this->assertNotNull($normalizer->normalize('eau de coco', 'ml'));
        $this->assertNotNull($normalizer->normalize('eau minérale', 'l'));
    }
}
