<?php

namespace Tests\Unit;

use App\Services\Menus\DietaryRules;
use App\Services\Menus\PlanningIngredient as Ingredient;
use App\Services\Menus\PlanningRecipe as Recipe;
use App\Services\Menus\SeasonalProduce;
use Tests\TestCase;

class DietaryRulesTest extends TestCase
{
    private function recipe(array $ingredients, array $attributes = []): Recipe
    {
        $recipe = new Recipe(['title' => 'Préparation maison', 'description' => 'Cuire et servir.', 'allergens' => [], 'vegetarian' => true, ...$attributes]);
        $recipe->setRelation('ingredients', collect($ingredients)->map(fn ($name) => new Ingredient(['name' => $name])));

        return $recipe;
    }

    public function test_all_fourteen_allergen_families_and_derivatives_are_detected(): void
    {
        $rules = new DietaryRules;
        $cases = ['gluten' => 'Chapelure', 'crustaceans' => 'Crevettes', 'eggs' => 'Mayonnaise', 'fish' => 'Anchois',
            'peanuts' => 'Huile d’arachide', 'soy' => 'Tofu', 'milk' => 'Lactosérum', 'nuts' => 'Noix de cajou',
            'celery' => 'Céleri rave', 'mustard' => 'Moutarde', 'sesame' => 'Tahini', 'sulphites' => 'E223', 'lupin' => 'Farine de lupin', 'molluscs' => 'Moules'];
        foreach ($cases as $allergen => $ingredient) {
            $recipe = $this->recipe([$ingredient]);
            $this->assertContains($allergen, $rules->detectedAllergens($recipe), $ingredient);
            $this->assertFalse($rules->eligible($recipe, ['allergens' => [$allergen]]), $ingredient);
        }
    }

    public function test_unknown_assessment_is_not_treated_as_an_empty_declaration(): void
    {
        $rules = new DietaryRules;
        $this->assertFalse($rules->eligible($this->recipe(['Riz'], ['allergens' => null]), ['allergens' => ['gluten']]));
        $this->assertTrue($rules->eligible($this->recipe(['Riz']), ['allergens' => ['gluten']]));
        $this->assertFalse($rules->eligible($this->recipe(['Riz'], ['allergens' => ['gluten']]), ['allergens' => ['gluten']]));
    }

    public function test_explicit_substitutes_do_not_hide_other_allergens(): void
    {
        $rules = new DietaryRules;
        $this->assertSame(['nuts'], $rules->detectedAllergens($this->recipe(['Lait d’amande', 'Farine de riz'])));
        $this->assertSame([], $rules->detectedAllergens($this->recipe(['Lait de coco', 'Laitue', 'Vinaigre', 'Rhubarbe'])));
        $this->assertContains('milk', $rules->detectedAllergens($this->recipe(['Lait sans lactose'])));
        $this->assertContains('milk', $rules->detectedAllergens($this->recipe(['Crème fraîche et huile végétale'])));
        $this->assertContains('gluten', $rules->detectedAllergens($this->recipe(['Sauce soja'])));
        $this->assertSame(['peanuts'], $rules->detectedAllergens($this->recipe(['Beurre de cacahuète'])));
        $this->assertSame([], $rules->detectedAllergens($this->recipe(['Crème de marrons', 'Beurre de cacao'])));
    }

    public function test_religious_exclusions_cover_sauces_charcuterie_and_cooked_alcohol(): void
    {
        $rules = new DietaryRules;
        foreach (['Lardons', 'Saindoux', 'Gélatine', 'Jambon', 'Saucisson'] as $ingredient) {
            $this->assertFalse($rules->eligible($this->recipe([$ingredient]), ['diets' => ['no_pork']]), $ingredient);
        }
        $this->assertFalse($rules->eligible($this->recipe(['Carotte'], ['description' => 'Déglacer au vin blanc puis cuire.']), ['diets' => ['no_alcohol']]));
        $this->assertTrue($rules->eligible($this->recipe(['Vinaigre de vin']), ['diets' => []]));
        $this->assertFalse($rules->eligible($this->recipe(['Fond de veau']), ['diets' => ['no_beef']]));
    }

    public function test_vegan_and_pescetarian_rules_apply_to_all_courses(): void
    {
        $rules = new DietaryRules;
        foreach (['Miel', 'Beurre', 'Œufs', 'Gélatine', 'Anchois', 'Fond de veau'] as $ingredient) {
            $this->assertFalse($rules->eligible($this->recipe([$ingredient]), ['diets' => ['vegan']]), $ingredient);
        }
        $this->assertTrue($rules->eligible($this->recipe(['Saumon']), ['diets' => ['pescetarian']]));
        $this->assertFalse($rules->eligible($this->recipe(['Lardons']), ['diets' => ['pescetarian']]));
        $this->assertFalse($rules->eligible($this->recipe(['Anchois']), ['vegetarian' => true]));
        $this->assertSame(['legumes', 'plant'], $rules->proteinSources(['diets' => ['vegan']]));
    }

    public function test_seasonality_uses_each_date_and_handles_preserved_food_and_names(): void
    {
        $rules = new SeasonalProduce;
        $this->assertTrue($rules->eligible($this->recipe(['Potimarron', 'Poireau', 'Pomme de terre']), '2026-10-19'));
        $this->assertFalse($rules->eligible($this->recipe(['Fraise fraîche']), '2026-10-19'));
        $this->assertTrue($rules->eligible($this->recipe(['Fraises surgelées']), '2026-10-19'));
        $this->assertTrue($rules->eligible($this->recipe(['Raisin']), '2026-10-31'));
        $this->assertFalse($rules->eligible($this->recipe(['Raisin']), '2026-11-01'));
        $this->assertTrue($rules->eligible($this->recipe(['Pomme de terre']), '2026-07-01'));
        $this->assertTrue($rules->eligible($this->recipe(['Chou-fleur']), '2026-10-19'));
        $this->assertTrue($rules->eligible($this->recipe(['Tomates en conserve']), '2026-12-01'));
        $this->assertFalse($rules->eligible($this->recipe(['Tomate fraîche']), '2026-12-01'));
        $this->assertFalse($rules->eligible($this->recipe(['Haricots verts frais']), '2026-12-01'));
    }
}
