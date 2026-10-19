<?php

namespace Tests\Unit;

use App\Services\Menus\MenuOptions;
use App\Services\Menus\PlanningRecipe;
use App\Services\Menus\SeasonalCuisine;
use PHPUnit\Framework\TestCase;

class SeasonalCuisineTest extends TestCase
{
    public function test_winter_cooking_preferences_change_with_the_meal_date(): void
    {
        $cuisine = new SeasonalCuisine;
        $tartiflette = new PlanningRecipe(['title' => 'Tartiflette et salade verte', 'course_type' => 'main']);
        $this->assertGreaterThan(0, $cuisine->bonus($tartiflette, ['seasonal' => true], '2026-12-25', []));
        $this->assertSame(0, $cuisine->bonus($tartiflette, ['seasonal' => true], '2026-07-25', []));
        $this->assertSame(0, $cuisine->bonus($tartiflette, ['seasonal' => false], '2026-12-25', []));
        $this->assertSame(0, $cuisine->bonus($tartiflette, ['seasonal' => true, 'occasion' => 'christmas'], '2026-12-25', []));
        $this->assertGreaterThan(0, $cuisine->bonus(new PlanningRecipe(['title' => 'Salade complète de pois chiches', 'course_type' => 'main']), ['seasonal' => true], '2026-07-25', []));
    }

    public function test_weekly_comfort_food_targets_respect_explicit_preferences_and_meal_choices(): void
    {
        $cuisine = new SeasonalCuisine;
        $criteria = ['seasonal' => true, 'variety' => 'varied', 'courses' => ['main']];
        $this->assertSame(['gratin', 'stew'], $cuisine->weeklyNeeds($criteria, '2026-10-19', [], 7));
        $this->assertSame(['stew'], $cuisine->weeklyNeeds($criteria, '2026-10-19', ['style:gratin' => 1], 7));
        $this->assertSame([], $cuisine->weeklyNeeds($criteria, '2026-10-19', ['style:gratin' => 1, 'style:stew' => 1], 7));
        $this->assertSame([], $cuisine->weeklyNeeds([...$criteria, 'preferences' => 'Pas de plats en sauce'], '2026-10-19', [], 7));
        $this->assertSame([], $cuisine->weeklyNeeds([...$criteria, 'courses' => ['dessert']], '2026-10-19', [], 7));
        $this->assertSame([], $cuisine->weeklyNeeds([...$criteria, 'occasion' => 'picnic'], '2026-10-19', [], 7));
        $this->assertSame([], $cuisine->weeklyNeeds($criteria, '2026-07-19', [], 7));
    }

    public function test_all_celebrations_require_a_constructed_dessert_without_changing_everyday_menus(): void
    {
        $ordinary = new PlanningRecipe(['title' => 'Poires rôties de fête', 'course_type' => 'dessert']);
        foreach (['christmas', 'new_year', 'easter', 'birthday', 'adult_birthday', 'child_birthday'] as $occasion) {
            $this->assertFalse(MenuOptions::suitableDessert($ordinary, ['occasion' => $occasion]));
            $this->assertTrue(MenuOptions::suitableDessert(new PlanningRecipe(['title' => 'Entremets végétal chocolat poire sans gluten', 'course_type' => 'dessert']), ['occasion' => $occasion]));
        }
        $this->assertTrue(MenuOptions::suitableDessert($ordinary, ['occasion' => 'everyday']));
        $this->assertTrue(MenuOptions::suitableDessert(new PlanningRecipe(['title' => 'Gâteau d’anniversaire enfant', 'course_type' => 'dessert']), ['occasion' => 'child_birthday']));
    }
}
