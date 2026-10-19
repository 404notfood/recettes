<?php

namespace Tests\Feature;

use App\Jobs\GenerateMealPlan;
use App\Models\Category;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Menus\HybridMenuPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DietaryMenuPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['services.openai.key' => null]);
        Http::preventStrayRequests();
    }

    private function recipe(string $course, array $attributes = [], array $names = []): Recipe
    {
        $category = Category::firstOrCreate(['slug' => ['starter' => 'entrees', 'main' => 'plats', 'dessert' => 'douceurs'][$course]], ['name' => $course]);
        $recipe = new Recipe;
        $recipe->forceFill([
            'category_id' => $category->id, 'title' => $course.' '.Str::random(8), 'slug' => Str::random(12),
            'description' => 'Une recette maison à préparer pour toute la famille.', 'author_name' => 'Cuistot Club',
            'status' => 'published', 'prep_time' => 8, 'cook_time' => 0, 'servings' => 2, 'difficulty' => 'facile',
            'ingredients' => array_map(fn ($name) => ['name' => $name, 'quantity' => 200, 'unit' => 'g'], $names ?: ['starter' => ['Carotte'], 'main' => ['Lentilles', 'Riz', 'Poireau'], 'dessert' => ['Pomme']][$course]),
            'steps' => ['Préparez les ingrédients, faites cuire puis servez chaud.'],
            'course_type' => $course, 'total_minutes' => 8, 'vegetarian' => true, 'tags' => [],
            'main_ingredient' => ['starter' => 'Carotte', 'main' => 'Lentilles', 'dessert' => 'Pomme'][$course],
            'food_groups' => ['starter' => ['vegetables'], 'main' => ['vegetables', 'starch', 'protein'], 'dessert' => ['fruit']][$course],
            'protein_source' => $course === 'main' ? 'legumes' : null, 'dessert_profile' => $course === 'dessert' ? 'fruit' : null,
            'allergens' => [], ...$attributes,
        ])->save();

        return $recipe;
    }

    private function plan(array $criteria = []): MealPlan
    {
        $plan = MealPlan::create(['user_id' => User::factory()->create()->id, 'title' => 'Menu adapté', 'start_date' => '2026-10-19', 'days' => $criteria['days'] ?? 1, 'people' => 2, 'status' => 'queued', 'criteria' => [
            'start_date' => '2026-10-19', 'days' => 1, 'people' => 2, 'meals' => ['lunch'], 'difficulty' => 1, 'quick_minutes' => 30, 'normal_minutes' => 90, 'quick_slots' => [], 'vegetarian' => false, 'excluded' => [], 'preferences' => '', 'seasonal' => true, 'variety' => 'varied', 'diets' => [], 'allergens' => [], ...$criteria,
        ]]);
        (new GenerateMealPlan($plan->id))->handle(app(HybridMenuPlanner::class));

        return $plan->fresh()->load('meals');
    }

    public function test_allergen_constraints_apply_to_every_course_and_keep_accompaniments(): void
    {
        $this->recipe('starter', ['title' => 'Céleri rémoulade'], ['Céleri', 'Mayonnaise']);
        $starter = $this->recipe('starter');
        $this->recipe('main', ['allergens' => null, 'total_minutes' => 1]);
        $main = $this->recipe('main', ['accompaniment' => 'Riz complet et poireaux']);
        $this->recipe('dessert', ['title' => 'Crème aux amandes'], ['Lait', 'Amandes']);
        $dessert = $this->recipe('dessert');
        $plan = $this->plan(['allergens' => ['milk', 'nuts', 'eggs']]);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $meal = $plan->meals->first();
        $this->assertSame($starter->id, $meal->starter_recipe_id);
        $this->assertSame($main->id, $meal->main_recipe_id);
        $this->assertSame($dessert->id, $meal->dessert_recipe_id);
        $this->assertSame('Riz complet et poireaux', $meal->snapshot['main']['accompaniment']);
        Http::assertNothingSent();
    }

    public function test_generated_recipes_cannot_bypass_allergens_or_seasonality(): void
    {
        $this->recipe('starter');
        $this->recipe('main');
        config(['services.openai.key' => 'test-not-a-secret']);
        foreach (['Beurre', 'Fraise fraîche'] as $ingredient) {
            $data = ['title' => 'Dessert pour le menu', 'description' => 'Un dessert maison pour terminer le repas.', 'steps' => ['Préparez et mélangez les ingrédients puis servez frais.'], 'course_type' => 'dessert', 'servings' => 2, 'total_minutes' => 5, 'difficulty' => 1, 'vegetarian' => true, 'main_ingredient' => 'Pomme', 'food_groups' => ['fruit'], 'protein_source' => null, 'dessert_profile' => 'fruit', 'allergens' => [], 'accompaniment' => null, 'ingredients' => [['name' => $ingredient, 'quantity' => 200, 'unit' => 'g']]];
            Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => [$data]])]]]]])]);
            $plan = $this->plan(['allergens' => ['milk']]);
            $this->assertSame('failed', $plan->status, $ingredient);
            $this->assertCount(0, $plan->meals);
            $this->assertSame(2, Recipe::count());
            Http::assertSentCount(1);
            Http::assertSent(fn ($request) => json_decode($request['input'], true)['excluded_allergens'] === ['milk'] && json_decode($request['input'], true)['seasonal'] === true);
        }
    }

    public function test_new_rules_preserve_existing_diversity_in_a_two_week_plan(): void
    {
        $starters = ['Carotte', 'Betterave', 'Radis', 'Endive', 'Céleri', 'Fenouil', 'Chou rouge'];
        $desserts = ['Pomme', 'Poire', 'Banane', 'Figue', 'Raisin', 'Mangue', 'Ananas'];
        $mains = [['Lentilles', 'legumes', 'Poireau'], ['Poulet', 'poultry', 'Brocoli'], ['Saumon', 'fish', 'Potimarron'], ['Œufs', 'eggs', 'Panais'], ['Pois chiches', 'legumes', 'Navet'], ['Tofu', 'plant', 'Chou-fleur'], ['Dinde', 'poultry', 'Mâche']];
        for ($i = 0; $i < 7; $i++) {
            $this->recipe('starter', ['title' => 'Entrée '.$starters[$i], 'main_ingredient' => $starters[$i]], [$starters[$i]]);
            $this->recipe('dessert', ['title' => 'Dessert '.$desserts[$i], 'main_ingredient' => $desserts[$i]], [$desserts[$i]]);
            [$protein, $source, $vegetable] = $mains[$i];
            $this->recipe('main', ['title' => 'Plat '.$protein, 'main_ingredient' => $protein, 'protein_source' => $source, 'vegetarian' => in_array($source, ['eggs', 'legumes', 'plant'])], [$protein, 'Riz', $vegetable]);
        }
        $plan = $this->plan(['days' => 14]);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertCount(14, $plan->meals);
        foreach ($plan->meals->chunk(7) as $week) {
            foreach (['starter', 'main', 'dessert'] as $course) {
                $this->assertCount(7, $week->pluck('snapshot.'.$course.'.title')->unique());
            }
            $counts = $week->pluck('snapshot.main.protein_source')->countBy();
            $this->assertGreaterThanOrEqual(2, $counts['legumes']);
            $this->assertGreaterThanOrEqual(1, $counts['fish']);
        }
        Http::assertNothingSent();
    }

    public function test_public_filters_preserve_pagination_and_hide_unassessed_or_excluded_recipes(): void
    {
        $safe = $this->recipe('main');
        $this->recipe('main', ['title' => 'Plat aux lardons'], ['Lardons']);
        $this->recipe('main', ['allergens' => null]);
        $this->get(route('recipes.index', ['diets' => ['no_pork'], 'allergens' => ['gluten']]))->assertInertia(fn (Assert $page) => $page->has('recipes.data', 1)->where('recipes.total', 1)->where('recipes.data.0.id', $safe->id));
    }

    public function test_menu_request_saves_new_criteria_and_rejects_unknown_codes(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());
        $data = ['start_date' => '2026-10-19', 'days' => 1, 'people' => 2, 'meals' => ['lunch'], 'difficulty' => 1, 'quick_minutes' => 30, 'normal_minutes' => 90, 'quick_slots' => [], 'vegetarian' => false, 'excluded' => [], 'seasonal' => true, 'variety' => 'varied', 'diets' => ['no_pork'], 'allergens' => ['unknown']];
        $this->post(route('menus.store'), $data)->assertSessionHasErrors('allergens.0');
        $this->assertSame(0, MealPlan::count());
        $this->post(route('menus.store'), [...$data, 'allergens' => ['gluten']])->assertSessionHasNoErrors()->assertRedirect();
        $criteria = MealPlan::firstOrFail()->criteria;
        $this->assertTrue($criteria['seasonal']);
        $this->assertSame('varied', $criteria['variety']);
        $this->assertSame(['no_pork'], $criteria['diets']);
        $this->assertSame(['gluten'], $criteria['allergens']);
    }

    public function test_empty_reviewed_allergen_declaration_survives_form_data_submission(): void
    {
        $category = Category::create(['name' => 'Entrées', 'slug' => 'entrees']);
        $this->actingAs(User::factory()->create())->post(route('recipes.store'), ['title' => 'Carottes râpées maison', 'description' => 'Une entrée fraîche de carottes râpées au citron.', 'category_id' => $category->id, 'prep_time' => 10, 'cook_time' => 0, 'servings' => 4, 'difficulty' => 'facile', 'ingredients' => [['name' => 'Carotte', 'quantity' => 500, 'unit' => 'g']], 'steps' => ['Lavez puis râpez les carottes et servez avec le citron.'], 'intent' => 'submit', 'allergens_assessed' => '1'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame([], Recipe::firstOrFail()->allergens);
    }

    public function test_composition_edits_require_a_fresh_allergen_assessment(): void
    {
        $recipe = $this->recipe('main');
        $recipe->update(['ingredients' => [['name' => 'Poireau', 'quantity' => 200, 'unit' => 'g']]]);
        $this->assertNull($recipe->fresh()->allergens);
        $recipe->update(['allergens' => []]);
        $recipe->update(['ingredients' => [['name' => 'Riz', 'quantity' => 200, 'unit' => 'g']], 'allergens' => []]);
        $this->assertSame([], $recipe->fresh()->allergens);
    }
}
