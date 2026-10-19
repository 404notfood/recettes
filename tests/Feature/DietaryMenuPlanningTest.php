<?php

namespace Tests\Feature;

use App\Jobs\GenerateMealPlan;
use App\Models\Category;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Menus\HybridMenuPlanner;
use App\Services\Menus\MenuOptions;
use App\Services\Menus\MenuPdfExporter;
use App\Services\Menus\PlanningRecipe;
use App\Services\Menus\RecipeCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $plan = MealPlan::create(['user_id' => User::factory()->create()->id, 'title' => 'Menu adapté', 'start_date' => $criteria['start_date'] ?? '2026-10-19', 'days' => $criteria['days'] ?? 1, 'people' => 2, 'status' => 'queued', 'criteria' => [
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

    public function test_three_allergen_rejections_are_logged_without_private_recipe_data(): void
    {
        $this->recipe('starter');
        $this->recipe('main');
        config(['services.openai.key' => 'test-not-a-secret']);
        Log::spy();
        $data = $this->generatedDessert('Beurre', 'Dessert pour le menu');
        Http::fake(['api.openai.com/*' => Http::response($this->apiResponse($data))]);
        $plan = $this->plan(['allergens' => ['milk']]);
        $this->assertSame('failed', $plan->status);
        $this->assertCount(0, $plan->meals);
        $this->assertSame(2, Recipe::count());
        Http::assertSentCount(3);
        Log::shouldHaveReceived('error')->withArgs(function (string $message, array $context) use ($plan) {
            if ($context['plan_id'] !== $plan->id) {
                return false;
            }
            $this->assertSame('menus.generation_failed', $message);
            $this->assertSame('eligibility', $context['diagnostics']['stage']);
            $this->assertSame(3, $context['diagnostics']['attempts']);
            $this->assertContains('allergen_conflict', $context['diagnostics']['rejections'][0]['reason_codes']);
            $this->assertStringNotContainsString('Dessert pour le menu', json_encode($context));
            $this->assertStringNotContainsString('test-not-a-secret', json_encode($context));

            return true;
        })->once();
        Http::assertSent(fn ($request) => json_decode($request['input'], true)['excluded_allergens'] === ['milk'] && json_decode($request['input'], true)['seasonal'] === true);
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

    private function generatedDessert(string $ingredient, string $title): array
    {
        return ['title' => $title, 'description' => 'Un dessert maison pour terminer le repas.', 'steps' => ['Préparez les ingrédients puis servez immédiatement.'], 'course_type' => 'dessert', 'servings' => 2, 'total_minutes' => 5, 'difficulty' => 1, 'vegetarian' => true, 'main_ingredient' => $ingredient, 'food_groups' => ['fruit'], 'protein_source' => null, 'dessert_profile' => 'fruit', 'allergens' => [], 'accompaniment' => null, 'ingredients' => [['name' => $ingredient, 'quantity' => 200, 'unit' => 'g']]];
    }

    private function apiResponse(array $recipe): array
    {
        return ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => [$recipe]])]]]]];
    }

    public function test_rejected_proposal_is_corrected_and_only_the_valid_recipe_is_saved(): void
    {
        $this->recipe('starter');
        $this->recipe('main');
        config(['services.openai.key' => 'test-not-a-secret']);
        $bad = $this->generatedDessert('Beurre', 'Proposition à refuser');
        $good = $this->generatedDessert('Poire', 'Poire fraîche pour le menu');
        Http::fake(['api.openai.com/*' => Http::sequence()->push($this->apiResponse($bad))->push($this->apiResponse($good))]);
        $plan = $this->plan(['allergens' => ['milk']]);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame(3, Recipe::count());
        $this->assertFalse(Recipe::where('title', $bad['title'])->exists());
        $this->assertSame($good['title'], $plan->meals->first()->snapshot['dessert']['title']);
        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            $input = json_decode($request['input'], true);

            return isset($input['validation_feedback'])
                && in_array('allergen_conflict', $input['validation_feedback']['rejections'][0]['reason_codes'], true)
                && $input['excluded_allergens'] === ['milk'];
        });
    }

    public function test_absence_labels_do_not_abort_a_generated_recipe(): void
    {
        $this->recipe('starter');
        $this->recipe('main');
        config(['services.openai.key' => 'test-not-a-secret']);
        Http::fake(['api.openai.com/*' => Http::response($this->apiResponse($this->generatedDessert('Poire', 'Poire sans porc ni alcool')))]);
        $plan = $this->plan(['diets' => ['no_pork', 'no_alcohol']]);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        Http::assertSentCount(1);
    }

    public function test_three_rejected_proposals_leave_an_existing_menu_intact(): void
    {
        foreach (['starter', 'main', 'dessert'] as $course) {
            $this->recipe($course);
        }
        $plan = $this->plan(['allergens' => ['milk']]);
        $this->assertSame('ready', $plan->status);
        $before = $plan->meals->toArray();
        config(['services.openai.key' => 'test-not-a-secret']);
        Http::fake(['api.openai.com/*' => Http::response($this->apiResponse($this->generatedDessert('Beurre', 'Dessert refusé')))]);
        $plan->update(['status' => 'queued']);
        (new GenerateMealPlan($plan->id, $plan->meals->first()->id, 'dessert'))->handle(app(HybridMenuPlanner::class));
        $this->assertSame('failed', $plan->fresh()->status);
        $this->assertStringContainsString('allergène exclu identifié', $plan->fresh()->error_message);
        $this->assertSame($before, $plan->fresh()->meals->toArray());
        $this->assertSame(3, Recipe::count());
        Http::assertSentCount(3);
    }

    public function test_seasonal_no_repeat_generation_corrects_an_out_of_season_dessert_without_diet_exclusions(): void
    {
        $this->recipe('starter');
        $this->recipe('main');
        config(['services.openai.key' => 'test-not-a-secret']);
        $bad = $this->generatedDessert('Fraise fraîche', 'Fraises pour le menu');
        $good = $this->generatedDessert('Poire', 'Poire de saison pour le menu');
        Http::fake(['api.openai.com/*' => Http::sequence()->push($this->apiResponse($bad))->push($this->apiResponse($good))]);
        $plan = $this->plan(['seasonal' => true, 'variety' => 'varied', 'allergens' => [], 'diets' => [], 'excluded' => []]);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame($good['title'], $plan->meals->first()->snapshot['dessert']['title']);
        $this->assertFalse(Recipe::where('title', $bad['title'])->exists());
        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            $input = json_decode($request['input'], true);

            return isset($input['validation_feedback'])
                && $input['variety'] === 'varied' && $input['seasonal'] === true
                && $input['diets'] === [] && $input['excluded_allergens'] === []
                && in_array('poire', $input['seasonal_produce'], true)
                && ! in_array('fraise', $input['seasonal_produce'], true)
                && in_array('out_of_season', $input['validation_feedback']['rejections'][0]['reason_codes'], true);
        });
    }

    public function test_seasonal_catalogue_priority_is_applied_before_the_twenty_candidate_limit(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->recipe('starter', ['title' => 'Courgette '.$i, 'main_ingredient' => 'Courgette'], ['Courgette']);
        }
        $seasonal = $this->recipe('starter', ['title' => 'Carottes râpées', 'total_minutes' => 20]);
        $this->recipe('main');
        $this->recipe('dessert');
        $plan = $this->plan();
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame($seasonal->id, $plan->meals->first()->starter_recipe_id);
        Http::assertNothingSent();
    }

    public function test_seasonal_priority_can_fall_back_to_another_vegetable_to_complete_a_menu(): void
    {
        $fallback = $this->recipe('starter', ['title' => 'Courgettes râpées', 'main_ingredient' => 'Courgette'], ['Courgette']);
        $this->recipe('starter', ['title' => 'Carottes trop longues', 'total_minutes' => 80]);
        $this->recipe('main');
        $this->recipe('dessert');
        $plan = $this->plan();
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame($fallback->id, $plan->meals->first()->starter_recipe_id);
        Http::assertNothingSent();
    }

    public function test_fresh_strawberries_are_skipped_in_the_october_catalogue_but_frozen_are_allowed(): void
    {
        $this->recipe('starter');
        $this->recipe('main');
        $this->recipe('dessert', ['title' => 'Fraises fraîches rapides', 'main_ingredient' => 'Fraise', 'total_minutes' => 1], ['Fraises fraîches']);
        $frozen = $this->recipe('dessert', ['title' => 'Fraises surgelées', 'main_ingredient' => 'Fraise'], ['Fraises surgelées']);
        $plan = $this->plan();
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame($frozen->id, $plan->meals->first()->dessert_recipe_id);
        Http::assertNothingSent();
    }

    public function test_generated_recipe_just_outside_the_calendar_can_be_used_with_seasonal_priority(): void
    {
        $this->recipe('starter');
        $this->recipe('main');
        config(['services.openai.key' => 'test-not-a-secret']);
        $dessert = $this->generatedDessert('Kiwi', 'Kiwi pour compléter le menu');
        Http::fake(['api.openai.com/*' => Http::response($this->apiResponse($dessert))]);
        $plan = $this->plan();
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame($dessert['title'], $plan->meals->first()->snapshot['dessert']['title']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => in_array('fraise', json_decode($request['input'], true)['excluded_fresh_produce'], true));
    }

    public function test_varied_generation_explicitly_requests_a_complete_main_and_corrects_an_incomplete_proposal(): void
    {
        $this->recipe('starter');
        $this->recipe('dessert');
        config(['services.openai.key' => 'test-not-a-secret']);
        $bad = [...$this->generatedDessert('Poulet', 'Poulet et riz pour le menu'), 'course_type' => 'main', 'vegetarian' => false, 'protein_source' => 'poultry', 'dessert_profile' => null, 'food_groups' => ['starch', 'protein'], 'ingredients' => [['name' => 'Poulet', 'quantity' => 300, 'unit' => 'g'], ['name' => 'Riz', 'quantity' => 200, 'unit' => 'g']]];
        $good = [...$bad, 'title' => 'Poulet au brocoli et riz', 'food_groups' => ['vegetables', 'starch', 'protein'], 'ingredients' => [...$bad['ingredients'], ['name' => 'Brocoli', 'quantity' => 300, 'unit' => 'g']]];
        Http::fake(['api.openai.com/*' => Http::sequence()->push($this->apiResponse($bad))->push($this->apiResponse($good))]);
        $plan = $this->plan();
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame($good['title'], $plan->meals->first()->snapshot['main']['title']);
        $this->assertFalse(Recipe::where('title', $bad['title'])->exists());
        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            $input = json_decode($request['input'], true);

            return $input['required_food_groups'] === ['vegetables', 'starch', 'protein']
                && isset($input['validation_feedback'])
                && in_array('meal_balance', $input['validation_feedback']['rejections'][0]['reason_codes'], true);
        });
    }

    public function test_every_nonempty_course_combination_uses_only_the_selected_recipes(): void
    {
        foreach (['starter', 'main', 'dessert'] as $course) {
            $this->recipe($course);
        }
        foreach ([['starter'], ['main'], ['dessert'], ['starter', 'main'], ['main', 'dessert'], ['starter', 'dessert'], ['starter', 'main', 'dessert']] as $courses) {
            $plan = $this->plan(['courses' => $courses]);
            $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
            $meal = $plan->meals->first();
            $this->assertEqualsCanonicalizing($courses, array_keys($meal->snapshot));
            $this->assertSame(8 * count($courses), $meal->total_minutes);
            foreach (array_keys(MenuOptions::COURSES) as $course) {
                $this->assertSame(in_array($course, $courses, true), $meal->{$course.'_recipe_id'} !== null);
            }
        }
        Http::assertNothingSent();
    }

    public function test_a_main_without_a_starter_is_complete_even_in_flexible_mode(): void
    {
        $this->recipe('main', ['title' => 'Riz aux lentilles incomplet', 'food_groups' => ['starch', 'protein'], 'total_minutes' => 1], ['Riz', 'Lentilles']);
        $complete = $this->recipe('main');
        $plan = $this->plan(['courses' => ['main'], 'variety' => 'flexible']);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame($complete->id, $plan->meals->first()->main_recipe_id);
    }

    public function test_course_and_occasion_inputs_are_validated_and_saved_with_optional_child_age(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Queue::fake();
        $this->actingAs(User::factory()->create());
        $data = ['start_date' => '2026-10-19', 'days' => 1, 'people' => 6, 'meals' => ['lunch'], 'difficulty' => 1, 'quick_minutes' => 30, 'normal_minutes' => 90, 'quick_slots' => [], 'vegetarian' => false, 'excluded' => []];
        $this->post(route('menus.store'), [...$data, 'courses' => []])->assertSessionHasErrors('courses');
        $this->post(route('menus.store'), [...$data, 'courses' => ['snack'], 'occasion' => 'unknown'])->assertSessionHasErrors(['courses.0', 'occasion']);
        $this->post(route('menus.store'), [...$data, 'occasion' => 'child_birthday', 'child_age' => 18])->assertSessionHasErrors('child_age');
        $this->assertSame(0, MealPlan::count());
        $this->post(route('menus.store'), [...$data, 'courses' => ['dessert', 'main'], 'occasion' => 'child_birthday', 'child_age' => 8])->assertSessionHasNoErrors()->assertRedirect();
        $plan = MealPlan::firstOrFail();
        $this->assertSame(['main', 'dessert'], $plan->criteria['courses']);
        $this->assertSame('child_birthday', $plan->criteria['occasion']);
        $this->assertSame(8, $plan->criteria['child_age']);
        $this->assertStringContainsString('8 ans', $plan->title);
        $this->post(route('menus.store'), [...$data, 'occasion' => 'family', 'child_age' => 8])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('child_age', MealPlan::latest('id')->firstOrFail()->criteria);
        $this->post(route('menus.store'), [...$data, 'occasion' => 'child_birthday', 'child_age' => null])->assertSessionHasNoErrors();
    }

    public function test_child_birthday_generates_only_the_selected_dessert_with_age_and_no_alcohol(): void
    {
        // Unrequested catalogue courses must not trigger classification or enrichment.
        $this->recipe('starter');
        $this->recipe('main');
        config(['services.openai.key' => 'test-not-a-secret']);
        $bad = $this->generatedDessert('Rhum', 'Dessert avec alcool à refuser');
        $good = [...$this->generatedDessert('Poire', 'Gâteau ludique aux poires'), 'dessert_profile' => 'treat'];
        $classified = ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipe_ids' => [0]])]]]]];
        Http::fake(['api.openai.com/*' => Http::sequence()->push($this->apiResponse($bad))->push($this->apiResponse($good))->push($classified)]);
        $plan = $this->plan(['courses' => ['dessert'], 'occasion' => 'child_birthday', 'child_age' => 8]);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame(['dessert'], array_keys($plan->meals->first()->snapshot));
        $this->assertSame($good['title'], $plan->meals->first()->snapshot['dessert']['title']);
        $this->assertFalse(Recipe::where('title', $bad['title'])->exists());
        Http::assertSentCount(3);
        Http::assertSent(function ($request) {
            $input = json_decode($request['input'], true);

            return ($input['missing_courses'] ?? []) === ['dessert']
                && $input['courses'] === ['dessert'] && $input['occasion'] === 'child_birthday'
                && $input['child_age'] === 8 && in_array('no_alcohol', $input['diets'], true)
                && $input['required_food_groups'] === [] && $input['weekly_protein_needs'] === []
                && str_contains($input['occasion_guidance'], '8 ans');
        });
    }

    public function test_occasion_is_used_to_select_catalogue_recipes(): void
    {
        $this->recipe('main', ['title' => 'Plat du quotidien']);
        $christmas = $this->recipe('main', ['title' => 'Plat de Noël']);
        config(['services.openai.key' => 'test-not-a-secret']);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipe_ids' => [1]])]]]]])]);
        $plan = $this->plan(['courses' => ['main'], 'occasion' => 'christmas']);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame($christmas->id, $plan->meals->first()->main_recipe_id);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains(json_decode($request['input'], true)['preferences'], 'Noël'));
    }

    public function test_partial_menu_views_exports_and_actions_do_not_expose_unselected_courses(): void
    {
        $this->recipe('main');
        $plan = $this->plan(['courses' => ['main']]);
        $meal = $plan->meals->first();
        $this->actingAs(User::findOrFail($plan->user_id))->get(route('menus.show', $plan))->assertInertia(fn (Assert $page) => $page->where('plan.meals.0.snapshot.main.title', $meal->snapshot['main']['title'])->missing('plan.meals.0.snapshot.starter')->missing('plan.meals.0.recipe_links.dessert'));
        $this->get(route('menus.recipe', ['mealPlan' => $plan, 'meal' => $meal, 'course' => 'starter']))->assertNotFound();
        $this->post(route('menus.replace', ['mealPlan' => $plan, 'meal' => $meal]), ['course' => 'dessert'])->assertNotFound();
        $html = app(MenuPdfExporter::class)->printable($plan, 'menus');
        $this->assertStringContainsString($meal->snapshot['main']['title'], $html);
        $this->assertStringNotContainsString('<span>Entrée</span>', $html);
        $this->assertStringNotContainsString('<span>Dessert</span>', $html);
        $this->get(route('menus.pdf', $plan))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_replacing_a_course_in_a_partial_menu_keeps_the_other_selected_course(): void
    {
        $this->recipe('main');
        $alternative = $this->recipe('main', ['title' => 'Poulet au brocoli et riz', 'protein_source' => 'poultry', 'main_ingredient' => 'Poulet', 'vegetarian' => false], ['Poulet', 'Brocoli', 'Riz']);
        $this->recipe('dessert');
        $plan = $this->plan(['courses' => ['main', 'dessert']]);
        $dessert = $plan->meals->first()->snapshot['dessert'];
        $plan->update(['status' => 'queued']);
        (new GenerateMealPlan($plan->id, $plan->meals->first()->id, 'main'))->handle(app(HybridMenuPlanner::class));
        $this->assertSame('ready', $plan->fresh()->status, $plan->fresh()->error_message ?? '');
        $meal = $plan->fresh()->meals->first();
        $this->assertSame($alternative->id, $meal->main_recipe_id);
        $this->assertSame($dessert, $meal->snapshot['dessert']);
        $this->assertNull($meal->starter_recipe_id);
        $this->assertArrayNotHasKey('starter', $meal->snapshot);
        Http::assertNothingSent();
    }

    public function test_christmas_catalogue_uses_a_celebration_dessert_instead_of_faster_roasted_pears(): void
    {
        $this->recipe('starter', ['title' => 'Potage de panais, poire et noisettes', 'main_ingredient' => 'Panais'], ['Panais', 'Poire', 'Noisettes']);
        $this->recipe('main', ['title' => 'Dinde aux châtaignes et chou rouge', 'main_ingredient' => 'Dinde', 'protein_source' => 'poultry', 'vegetarian' => false], ['Dinde', 'Châtaignes', 'Polenta', 'Chou rouge']);
        $ordinary = $this->recipe('dessert', ['title' => 'Poires rôties de Noël aux noisettes', 'main_ingredient' => 'Poire', 'total_minutes' => 1], ['Poire', 'Noisettes']);
        $festive = $this->recipe('dessert', ['title' => 'Bûche chocolat clémentine', 'main_ingredient' => 'Chocolat', 'dessert_profile' => 'treat', 'total_minutes' => 40], ['Chocolat noir', 'Clémentine']);
        config(['services.openai.key' => 'test-not-a-secret']);
        // Even an overly broad thematic classifier cannot pass the ordinary dessert.
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipe_ids' => [0, 1, 2, 3]])]]]]])]);
        $plan = $this->plan(['occasion' => 'christmas', 'start_date' => '2026-12-25']);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame($festive->id, $plan->meals->first()->dessert_recipe_id);
        $this->assertNotSame($ordinary->id, $plan->meals->first()->dessert_recipe_id);
        Http::assertSentCount(1);
    }

    public function test_christmas_generation_corrects_an_ordinary_dessert_and_keeps_dietary_exclusions(): void
    {
        $this->recipe('starter');
        $this->recipe('main');
        $bad = $this->generatedDessert('Poire', 'Poires rôties de Noël');
        $good = [...$this->generatedDessert('Clémentine', 'Bûche végétale chocolat clémentine sans gluten'), 'dessert_profile' => 'treat'];
        $match = fn ($ids) => ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipe_ids' => $ids])]]]]];
        config(['services.openai.key' => 'test-not-a-secret']);
        Http::fake(['api.openai.com/*' => Http::sequence()->push($match([0, 1]))->push($this->apiResponse($bad))->push($this->apiResponse($good))->push($match([0]))]);
        $plan = $this->plan(['occasion' => 'christmas', 'start_date' => '2026-12-25', 'diets' => ['vegan', 'gluten_free'], 'allergens' => ['milk', 'eggs', 'nuts']]);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame($good['title'], $plan->meals->first()->snapshot['dessert']['title']);
        $this->assertFalse(Recipe::where('title', $bad['title'])->exists());
        Http::assertSentCount(4);
        Http::assertSent(function ($request) {
            $input = json_decode($request['input'], true);

            return isset($input['validation_feedback']) && $input['celebration_dessert'] === true
                && $input['allow_treat_dessert'] === true
                && $input['diets'] === ['vegan', 'gluten_free']
                && $input['excluded_allergens'] === ['milk', 'eggs', 'nuts']
                && in_array('celebration_dessert', $input['validation_feedback']['rejections'][0]['reason_codes'], true);
        });
    }

    public function test_celebration_desserts_keep_diversity_without_the_everyday_treat_quota(): void
    {
        $rules = app(RecipeCriteria::class);
        $dessert = new PlanningRecipe(['title' => 'Bûche aux clémentines', 'course_type' => 'dessert', 'dessert_profile' => 'treat']);
        $this->assertFalse($rules->allowedByWeek($dessert, ['treat' => 2], 7));
        $this->assertTrue($rules->allowedByWeek($dessert, ['treat' => 2], 7, ['occasion' => 'christmas', 'variety' => 'varied']));
        $this->assertFalse($rules->allowedByWeek($dessert, ['treat' => 2, 'recipe:buche aux clementines' => 1], 7, ['occasion' => 'christmas', 'variety' => 'varied']));
    }

    public function test_replacing_a_dessert_compares_it_to_both_retained_courses(): void
    {
        $this->recipe('starter', ['title' => 'Velouté de panais et poire', 'main_ingredient' => 'Panais'], ['Panais', 'Poire']);
        $this->recipe('main');
        $this->recipe('dessert');
        $plan = $this->plan();
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $before = $plan->meals->first()->snapshot;
        config(['services.openai.key' => 'test-not-a-secret']);
        $bad = $this->generatedDessert('Poire', 'Poires rôties qui répètent l’entrée');
        $good = $this->generatedDessert('Clémentine', 'Clémentines pour terminer le repas');
        Http::fake(['api.openai.com/*' => Http::sequence()->push($this->apiResponse($bad))->push($this->apiResponse($good))]);
        $plan->update(['status' => 'queued']);
        (new GenerateMealPlan($plan->id, $plan->meals->first()->id, 'dessert'))->handle(app(HybridMenuPlanner::class));
        $this->assertSame('ready', $plan->fresh()->status, $plan->fresh()->error_message ?? '');
        $after = $plan->fresh()->meals->first()->snapshot;
        $this->assertSame($before['starter'], $after['starter']);
        $this->assertSame($before['main'], $after['main']);
        $this->assertSame($good['title'], $after['dessert']['title']);
        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            $input = json_decode($request['input'], true);

            return isset($input['validation_feedback'])
                && in_array('same_meal_ingredients', $input['validation_feedback']['rejections'][0]['reason_codes'], true)
                && in_array('poire', $input['existing_courses']['starter']['distinctive_ingredients'], true);
        });
    }

    public function test_an_autumn_classic_with_a_real_vegetable_side_is_preferred_to_a_faster_plain_main(): void
    {
        $this->recipe('starter');
        $this->recipe('dessert');
        $this->recipe('main', ['title' => 'Lentilles et riz aux poireaux']);
        $tartiflette = $this->recipe('main', ['title' => 'Tartiflette et salade verte', 'main_ingredient' => 'Pomme de terre', 'protein_source' => 'red_meat', 'vegetarian' => false, 'total_minutes' => 50, 'accompaniment' => 'Salade verte'], ['Pomme de terre', 'Lardons', 'Reblochon', 'Salade verte']);
        $plan = $this->plan();
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame($tartiflette->id, $plan->meals->first()->main_recipe_id);
        $this->assertSame('Salade verte', $plan->meals->first()->snapshot['main']['accompaniment']);
        Http::assertNothingSent();
    }

    private function winterMainCatalogue(): void
    {
        foreach ([['Lentilles', 'legumes', 'Poireau'], ['Poulet', 'poultry', 'Brocoli'], ['Saumon', 'fish', 'Potimarron'], ['Œufs', 'eggs', 'Panais'], ['Pois chiches', 'legumes', 'Navet'], ['Tofu', 'plant', 'Chou-fleur'], ['Dinde', 'poultry', 'Mâche']] as [$protein, $source, $vegetable]) {
            $this->recipe('main', ['title' => 'Plat '.$protein, 'main_ingredient' => $protein, 'protein_source' => $source, 'vegetarian' => in_array($source, ['eggs', 'legumes', 'plant'])], [$protein, 'Riz', $vegetable]);
        }
    }

    public function test_a_winter_week_adds_a_gratin_and_a_stew_when_the_catalogue_only_has_plain_mains(): void
    {
        $this->winterMainCatalogue();
        $gratin = [...$this->generatedDessert('Pois chiches', 'Gratin de pois chiches, épinards et riz'), 'course_type' => 'main', 'food_groups' => ['vegetables', 'starch', 'protein'], 'protein_source' => 'legumes', 'dessert_profile' => null, 'total_minutes' => 40, 'ingredients' => [['name' => 'Pois chiches', 'quantity' => 200, 'unit' => 'g'], ['name' => 'Épinards', 'quantity' => 200, 'unit' => 'g'], ['name' => 'Riz', 'quantity' => 150, 'unit' => 'g']]];
        $stew = [...$gratin, 'title' => 'Blanquette de poulet aux champignons et riz', 'main_ingredient' => 'Poulet', 'protein_source' => 'poultry', 'vegetarian' => false, 'total_minutes' => 55, 'ingredients' => [['name' => 'Poulet', 'quantity' => 200, 'unit' => 'g'], ['name' => 'Champignons', 'quantity' => 200, 'unit' => 'g'], ['name' => 'Riz', 'quantity' => 150, 'unit' => 'g']]];
        $plain = [...$gratin, 'title' => 'Pois chiches, épinards et riz'];
        config(['services.openai.key' => 'test-not-a-secret']);
        Http::fake(['api.openai.com/*' => Http::sequence()->push($this->apiResponse($plain))->push($this->apiResponse($gratin))->push($this->apiResponse($stew))]);
        $plan = $this->plan(['courses' => ['main'], 'days' => 7]);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertCount(7, $plan->meals);
        $titles = $plan->meals->pluck('snapshot.main.title')->all();
        $this->assertContains($gratin['title'], $titles);
        $this->assertContains($stew['title'], $titles);
        $this->assertCount(7, array_unique($titles));
        $this->assertFalse(Recipe::where('title', $plain['title'])->exists());
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => json_decode($request['input'], true)['requested_dish_style'] === 'gratin');
        Http::assertSent(fn ($request) => json_decode($request['input'], true)['requested_dish_style'] === 'stew');
        Http::assertSent(function ($request) {
            $input = json_decode($request['input'], true);

            return isset($input['validation_feedback'])
                && in_array('seasonal_dish_style', $input['validation_feedback']['rejections'][0]['reason_codes'], true);
        });
    }

    public function test_quick_winter_slots_do_not_force_long_gratins_or_stews(): void
    {
        $this->winterMainCatalogue();
        config(['services.openai.key' => 'test-not-a-secret']);
        $plan = $this->plan(['courses' => ['main'], 'days' => 7, 'quick_minutes' => 15, 'quick_slots' => array_map(fn ($day) => $day.':lunch', range(0, 6))]);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertCount(7, $plan->meals);
        $this->assertLessThanOrEqual(15, $plan->meals->max('total_minutes'));
        Http::assertNothingSent();
    }

    public function test_winter_classics_never_override_pork_or_milk_exclusions(): void
    {
        $this->recipe('main', ['title' => 'Tartiflette et salade verte', 'protein_source' => 'red_meat', 'vegetarian' => false, 'main_ingredient' => 'Pomme de terre'], ['Pomme de terre', 'Lardons', 'Reblochon', 'Salade verte']);
        $this->recipe('main', ['title' => 'Lentilles, riz et poireau']);
        $gratin = $this->recipe('main', ['title' => 'Gratin de lentilles, riz et potimarron sans lait', 'total_minutes' => 40], ['Lentilles', 'Riz', 'Potimarron']);
        $plan = $this->plan(['courses' => ['main'], 'diets' => ['no_pork'], 'allergens' => ['milk']]);
        $this->assertSame('ready', $plan->status, $plan->error_message ?? '');
        $this->assertSame($gratin->id, $plan->meals->first()->main_recipe_id);
        Http::assertNothingSent();
    }
}
