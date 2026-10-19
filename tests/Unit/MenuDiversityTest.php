<?php

namespace Tests\Unit;

use App\Services\Menus\MenuDiversity;
use App\Services\Menus\PlanningIngredient;
use App\Services\Menus\PlanningRecipe;
use App\Services\Menus\RecipeCriteria;
use PHPUnit\Framework\TestCase;

class MenuDiversityTest extends TestCase
{
    private function recipe(string $title, string $main, array $ingredients = []): PlanningRecipe
    {
        $recipe = new PlanningRecipe(['title' => $title, 'main_ingredient' => $main, 'servings' => 4]);
        $recipe->setRelation('ingredients', collect($ingredients)->map(fn ($data) => new PlanningIngredient($data)));

        return $recipe;
    }

    public function test_savoyard_variations_and_leek_accompaniments_are_recognized(): void
    {
        $diversity = new MenuDiversity(new RecipeCriteria);
        $croziflette = $diversity->profile($this->recipe('Croziflette savoyarde', 'crozets'));
        $this->assertFalse($diversity->allowed('main', $diversity->profile($this->recipe('Tartiflette au reblochon', 'pomme de terre')), ['main' => [$croziflette]]));
        $cod = $diversity->profile($this->recipe('Cabillaud poêlé', 'cabillaud', [['name' => 'Poireaux', 'quantity' => 600, 'unit' => 'g']]));
        $this->assertContains('poireau', $cod['ingredients']);
        $this->assertTrue($diversity->allowedByWeek($cod, ['ingredient:poireau' => 1], 14));
        $this->assertFalse($diversity->allowedByWeek($cod, ['ingredient:poireau' => 2], 14));
        $this->assertFalse($diversity->allowed('main', $diversity->profile($this->recipe('Poulet à la fondue de poireaux', 'poulet')), ['main' => [$cod]]));
        $this->assertTrue($diversity->allowed('main', $diversity->profile($this->recipe('Poulet aux champignons', 'poulet')), ['main' => [$cod]]));
    }

    public function test_vegetable_variants_in_the_same_meal_conflict_but_seasoning_does_not(): void
    {
        $diversity = new MenuDiversity(new RecipeCriteria);
        $starter = $this->recipe('Carottes râpées au citron', 'carotte');
        $this->assertFalse($diversity->compatible(['starter' => $starter, 'main' => $this->recipe('Poulet aux carottes', 'poulet')]));
        $main = $this->recipe('Poulet au riz', 'poulet', [['name' => 'Carotte pour le bouillon', 'quantity' => 20, 'unit' => 'g']]);
        $this->assertTrue($diversity->compatible(['starter' => $starter, 'main' => $main]));
        $this->assertSame([], $diversity->profile($this->recipe('Pommes de terre vapeur', 'pomme de terre'))['ingredients']);
    }

    public function test_starter_and_dessert_cannot_repeat_pears_or_featured_nuts(): void
    {
        $diversity = new MenuDiversity(new RecipeCriteria);
        $starter = $this->recipe('Potage de panais, poire et noisettes', 'panais');
        $this->assertFalse($diversity->compatible(['starter' => $starter, 'dessert' => $this->recipe('Poires rôties', 'poire')]));
        $this->assertFalse($diversity->compatible(['starter' => $starter, 'dessert' => $this->recipe('Bûche chocolat noisettes', 'chocolat')]));
        $this->assertTrue($diversity->compatible(['starter' => $starter, 'dessert' => $this->recipe('Bûche chocolat clémentine', 'chocolat')]));
        $this->assertTrue($diversity->compatible(['main' => $this->recipe('Dinde rôtie', 'dinde', [['name' => 'Noisettes concassées', 'quantity' => 10, 'unit' => 'g']]), 'dessert' => $this->recipe('Bûche noisettes', 'noisette')]));
    }

    public function test_retained_courses_do_not_hide_conflicts_with_a_replacement_dessert(): void
    {
        $diversity = new MenuDiversity(new RecipeCriteria);
        $starter = $this->recipe('Carottes et poires en salade', 'carotte');
        $main = $this->recipe('Poulet aux carottes', 'poulet');
        $fixed = ['starter' => $starter, 'main' => $main];
        $this->assertTrue($diversity->compatible([...$fixed, 'dessert' => $this->recipe('Bûche clémentine', 'clementine')], $fixed));
        $this->assertFalse($diversity->compatible([...$fixed, 'dessert' => $this->recipe('Bûche poire', 'poire')], $fixed));
    }
}
