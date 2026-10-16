<?php

namespace Tests\Feature;

use App\Jobs\GenerateMealPlan;
use App\Models\Category;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Menus\CatalogueMetadata;
use App\Services\Menus\HybridMenuPlanner;
use App\Services\Menus\MenuDiversity;
use App\Services\Menus\OpenAiRecipeService;
use App\Services\Menus\PlanningException;
use App\Services\Menus\PlanningRecipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MealPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['services.openai.key' => null]);
        Http::preventStrayRequests();
    }

    private function criteria(int $days = 1): array
    {
        return ['start_date' => '2026-10-19', 'days' => $days, 'people' => 4, 'meals' => ['lunch', 'dinner'], 'difficulty' => 1, 'quick_minutes' => 30, 'normal_minutes' => 90, 'quick_slots' => [], 'vegetarian' => false, 'excluded' => [], 'preferences' => ''];
    }

    private function recipe(string $course, int $number, ?User $owner = null, string $status = 'published'): Recipe
    {
        $category = Category::firstOrCreate(['slug' => ['starter' => 'entrees', 'main' => 'plats', 'dessert' => 'douceurs'][$course]], ['name' => $course]);
        $ingredients = [
            'starter' => ['Concombre', 'Carotte', 'Betterave', 'Radis', 'Endive', 'Courgette', 'Poivron', 'Aubergine', 'Panais', 'Céleri', 'Brocoli', 'Chou-fleur', 'Navet', 'Fenouil', 'Asperge', 'Artichaut', 'Tomate'],
            'main' => ['Tofu', 'Poulet', 'Lentilles', 'Dinde', 'Cabillaud', 'Pois chiches', 'Œufs', 'Sardine', 'Haricots blancs', 'Poulet', 'Truite', 'Lentilles', 'Dinde', 'Maquereau', 'Pois chiches', 'Œufs', 'Saumon'],
            'dessert' => ['Poire', 'Pomme', 'Banane', 'Kiwi', 'Orange', 'Clémentine', 'Fraise', 'Pêche', 'Abricot', 'Prune', 'Raisin', 'Mangue', 'Ananas', 'Framboise', 'Myrtille', 'Cerise', 'Melon'],
        ];
        $ingredient = $ingredients[$course][$number % count($ingredients[$course])];
        $recipe = new Recipe;
        $recipe->forceFill([
            'user_id' => $owner?->id, 'title' => "$ingredient recette $number", 'slug' => "$course-$number", 'description' => 'Une recette simple à cuisiner pour toute la famille.',
            'category_id' => $category->id, 'status' => $status, 'author_name' => 'Cuistot Club', 'servings' => 2,
            'prep_time' => 10, 'cook_time' => 0, 'difficulty' => 'facile', 'tags' => [],
            'ingredients' => [['name' => $ingredient, 'quantity' => 200, 'unit' => 'g']],
            'steps' => ['Préparez les ingrédients et faites cuire selon les indications.'],
            'course_type' => $course, 'total_minutes' => 10, 'vegetarian' => $course !== 'main', 'main_ingredient' => $ingredient,
            'food_groups' => ['starter' => ['vegetables'], 'main' => ['starch', 'protein'], 'dessert' => ['fruit']][$course],
            'protein_source' => $course === 'main' ? ['poultry', 'fish', 'legumes'][$number % 3] : null,
            'dessert_profile' => $course === 'dessert' ? 'fruit' : null,
        ])->save();

        return $recipe;
    }

    private function savedPlan(User $owner): MealPlan
    {
        $plan = MealPlan::create(['user_id' => $owner->id, 'title' => 'Le menu', 'start_date' => '2026-10-19', 'days' => 1, 'people' => 4, 'criteria' => $this->criteria(), 'status' => 'generating']);
        foreach (['starter', 'main', 'dessert'] as $course) {
            $this->recipe($course, 1);
        }
        $this->recipe('main', 2);
        $this->recipe('starter', 2);
        $this->recipe('dessert', 2);
        (new GenerateMealPlan($plan->id))->handle(app(HybridMenuPlanner::class));
        // The worker claims queued plans; set explicitly for this fixture.
        if (! $plan->fresh()->meals()->exists()) {
            app(HybridMenuPlanner::class)->generate($plan);
        }

        return $plan->fresh()->load('meals');
    }

    public function test_catalogue_generates_one_seven_and_fourteen_day_plans_without_api(): void
    {
        foreach (['starter', 'main', 'dessert'] as $course) {
            for ($i = 0; $i < 14; $i++) {
                $this->recipe($course, $i);
            }
        }
        $owner = User::factory()->create();
        foreach ([1, 7, 14] as $days) {
            $plan = MealPlan::create(['user_id' => $owner->id, 'title' => 'Menu', 'start_date' => '2026-10-19', 'days' => $days, 'people' => 4, 'status' => 'queued', 'criteria' => $this->criteria($days)]);
            (new GenerateMealPlan($plan->id))->handle(app(HybridMenuPlanner::class));
            $this->assertSame('ready', $plan->fresh()->status, $plan->fresh()->error_message ?? '');
            $this->assertSame($days * 2, $plan->meals()->count());
            $this->assertIsArray($plan->meals()->first()->snapshot['main']['steps']);
        }
        Http::assertNothingSent();
    }

    public function test_page_links_to_slug_and_groups_scaled_groceries(): void
    {
        $owner = User::factory()->create();
        $plan = $this->savedPlan($owner);
        $this->actingAs($owner)->get(route('menus.show', $plan))->assertInertia(fn (Assert $page) => $page->component('menus/show')->where('plan.meals.0.recipe_links.main', route('recipes.show', ['recipe' => 'main-1']))->where('shoppingCategories.0.label', 'Légumes')->where('shoppingCategories.0.items.0.quantity', 400));
    }

    public function test_print_contains_only_selected_unique_recipes_with_scaled_amounts_and_no_images(): void
    {
        $owner = User::factory()->create();
        $plan = $this->savedPlan($owner);
        $first = $plan->meals[0];
        $second = $plan->meals[1];
        // A retained meal may share a recipe: the print export still deduplicates it.
        $second->update(['starter_recipe_id' => $first->starter_recipe_id, 'snapshot' => ['starter' => $first->snapshot['starter'], ...array_diff_key($second->snapshot, ['starter' => true])]]);
        $response = $this->actingAs($owner)->get(route('menus.print', ['mealPlan' => $plan->id, 'recipes' => [$first->id.':starter', $second->id.':starter']]));
        $response->assertOk()->assertSee('400 g')->assertSee('Préparez les ingrédients')->assertDontSee('Poulet recette')->assertDontSee('<img', false);
        $this->assertSame(1, substr_count($response->getContent(), '<article class="recipe">'));
        $this->actingAs($owner)->get(route('menus.print', ['mealPlan' => $plan->id, 'recipes' => [$first->id.':main'], 'format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_print_and_snapshot_views_require_ownership_and_selectors_from_the_plan(): void
    {
        $owner = User::factory()->create();
        $plan = $this->savedPlan($owner);
        $meal = $plan->meals[0];
        $url = route('menus.print', ['mealPlan' => $plan->id, 'recipes' => [$meal->id.':starter']]);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($owner)->get(route('menus.print', ['mealPlan' => $plan->id, 'recipes' => ['999999:main']]))->assertNotFound();
        $this->get(route('menus.print', ['mealPlan' => $plan->id]))->assertSessionHasErrors('recipes');
        Recipe::where('slug', 'main-1')->first()->delete();
        $this->get(route('menus.show', $plan))->assertInertia(fn (Assert $page) => $page->where('plan.meals.0.recipe_links.main', route('menus.recipe', ['mealPlan' => $plan->id, 'meal' => $meal->id, 'course' => 'main'])));
        $this->get(route('menus.recipe', ['mealPlan' => $plan->id, 'meal' => $meal->id, 'course' => 'main']))->assertOk();
    }

    public function test_existing_recipe_metadata_uses_declared_food_without_inventing_accompaniments(): void
    {
        $recipe = $this->recipe('main', 1);
        $recipe->forceFill(['food_groups' => null, 'protein_source' => null, 'ingredients' => [['name' => 'Poulet', 'quantity' => 400, 'unit' => 'g'], ['name' => 'Persil', 'quantity' => 5, 'unit' => 'g']]])->save();
        $metadata = app(CatalogueMetadata::class)->forRecipe($recipe->fresh());
        $this->assertSame(['protein'], $metadata['food_groups']);
        $this->assertSame('poultry', $metadata['protein_source']);
        $this->assertSame(1, PlanningRecipe::fromRecord($recipe->fresh())->difficulty);
    }

    public function test_generated_missing_recipes_are_saved_with_steps_and_private_slug_links(): void
    {
        $owner = User::factory()->create();
        $this->recipe('starter', 1);
        $this->recipe('main', 1);
        config(['services.openai.key' => 'test-key']);
        $data = ['title' => 'Pommes au four pour le menu', 'description' => 'Des pommes tendres au four pour terminer le repas.', 'steps' => ['Préchauffez le four à 180 °C et enfournez les pommes pendant vingt minutes.'], 'course_type' => 'dessert', 'servings' => 4, 'total_minutes' => 25, 'difficulty' => 1, 'vegetarian' => true, 'main_ingredient' => 'pomme', 'food_groups' => ['fruit'], 'protein_source' => null, 'dessert_profile' => 'fruit', 'ingredients' => [['name' => 'Pomme', 'quantity' => 4, 'unit' => 'pièce']]];
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => [$data]])]]]]])]);
        $plan = MealPlan::create(['user_id' => $owner->id, 'title' => 'Menu', 'start_date' => '2026-10-19', 'days' => 1, 'people' => 4, 'status' => 'queued', 'criteria' => [...$this->criteria(), 'meals' => ['lunch']]]);
        (new GenerateMealPlan($plan->id))->handle(app(HybridMenuPlanner::class));
        $this->assertSame('ready', $plan->fresh()->status, $plan->fresh()->error_message ?? '');
        $created = Recipe::where('is_ai_generated', true)->firstOrFail();
        $this->assertSame('pending', $created->status);
        $this->assertSame($data['steps'], $created->steps);
        $this->actingAs($owner)->get(route('recipes.show', ['recipe' => $created->slug]))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('recipes.show', ['recipe' => $created->slug]))->assertNotFound();
        Http::assertSentCount(1);
    }

    public function test_empty_steps_in_generated_recipes_do_not_abort_a_complete_menu(): void
    {
        $owner = User::factory()->create();
        $this->recipe('starter', 1);
        config(['services.openai.key' => 'test-key']);
        $mainSteps = ['Faites cuire le riz dans de l’eau frémissante pendant quinze minutes.', 'Découpez le poulet puis faites-le cuire à cœur dans une poêle.', 'Réunissez le riz et le poulet, puis servez bien chaud.'];
        $dessertSteps = ['Épluchez les pommes puis découpez-les en petits morceaux.', 'Répartissez les morceaux de pommes dans quatre bols et servez.'];
        $main = ['title' => 'Riz au poulet pour le menu', 'description' => 'Un plat simple de riz et de poulet pour toute la famille.', 'steps' => [...$mainSteps, ''], 'course_type' => 'main', 'servings' => 4, 'total_minutes' => 25, 'difficulty' => 1, 'vegetarian' => false, 'main_ingredient' => 'poulet', 'food_groups' => ['starch', 'protein'], 'protein_source' => 'poultry', 'dessert_profile' => null, 'ingredients' => [['name' => 'Poulet', 'quantity' => 500, 'unit' => 'g'], ['name' => 'Riz', 'quantity' => 300, 'unit' => 'g']]];
        $dessert = ['title' => 'Pommes croquantes pour le menu', 'description' => 'Des pommes fraîches à partager pour terminer le repas.', 'steps' => ['  '.$dessertSteps[0].'  ', $dessertSteps[1], " \n\t "], 'course_type' => 'dessert', 'servings' => 4, 'total_minutes' => 5, 'difficulty' => 1, 'vegetarian' => true, 'main_ingredient' => 'pomme', 'food_groups' => ['fruit'], 'protein_source' => null, 'dessert_profile' => 'fruit', 'ingredients' => [['name' => 'Pomme', 'quantity' => 4, 'unit' => 'pièce']]];
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => [$main, $dessert]])]]]]])]);
        $plan = MealPlan::create(['user_id' => $owner->id, 'title' => 'Menu', 'start_date' => '2026-10-19', 'days' => 1, 'people' => 4, 'status' => 'queued', 'criteria' => [...$this->criteria(), 'meals' => ['lunch']]]);

        (new GenerateMealPlan($plan->id))->handle(app(HybridMenuPlanner::class));

        $this->assertSame('ready', $plan->fresh()->status, $plan->fresh()->error_message ?? '');
        $meal = $plan->meals()->firstOrFail();
        $this->assertSame($mainSteps, $meal->snapshot['main']['steps']);
        $this->assertSame($dessertSteps, $meal->snapshot['dessert']['steps']);
        $this->assertSame($mainSteps, Recipe::where('title', $main['title'])->firstOrFail()->steps);
        $this->assertSame($dessertSteps, Recipe::where('title', $dessert['title'])->firstOrFail()->steps);
        Http::assertSentCount(1);
    }

    public function test_step_cleanup_still_rejects_empty_short_or_non_text_instructions(): void
    {
        config(['services.openai.key' => 'test-key']);
        $recipe = ['title' => 'Pommes pour le menu', 'description' => 'Des pommes fraîches à partager pour terminer le repas.', 'course_type' => 'dessert', 'servings' => 4, 'total_minutes' => 5, 'difficulty' => 1, 'vegetarian' => true, 'main_ingredient' => 'pomme', 'food_groups' => ['fruit'], 'protein_source' => null, 'dessert_profile' => 'fruit', 'ingredients' => [['name' => 'Pomme', 'quantity' => 4, 'unit' => 'pièce']]];
        $validStep = 'Épluchez les pommes puis découpez-les en petits morceaux.';
        $cases = [
            [['', " \t\n "], 'recipes.0.steps', 'Required'],
            [[$validStep, '', 'Cuire'], 'recipes.0.steps.1', 'Min'],
            [[$validStep, null], 'recipes.0.steps.1', 'Required'],
            [[$validStep, 123], 'recipes.0.steps.1', 'String'],
        ];
        $responses = Http::sequence();
        foreach ($cases as [$steps]) {
            $responses->push(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => [[...$recipe, 'steps' => $steps]]])]]]]]);
        }
        Http::fake(['api.openai.com/*' => $responses]);

        foreach ($cases as [, $field, $rule]) {
            try {
                app(OpenAiRecipeService::class)->generate(['missing_courses' => ['dessert'], 'remaining_minutes' => 20]);
                $this->fail('Une recette sans instructions valables ne doit pas être acceptée.');
            } catch (PlanningException $error) {
                $this->assertContains($rule, $error->diagnostics['validation_rules'][$field]);
            }
        }
        Http::assertSentCount(count($cases));
    }

    public function test_invalid_generated_recipe_is_diagnosed_without_logging_private_values(): void
    {
        Log::spy();
        $owner = User::factory()->create();
        $this->recipe('starter', 1);
        $this->recipe('main', 1);
        config(['services.openai.key' => 'private-test-key']);
        $data = ['title' => 'Dessert privé', 'description' => 'secret-prompt', 'steps' => ['Préparez les pommes et servez-les en petits morceaux.'], 'course_type' => 'dessert', 'servings' => 4, 'total_minutes' => 10, 'difficulty' => 1, 'vegetarian' => true, 'main_ingredient' => 'pomme', 'food_groups' => ['fruit'], 'protein_source' => null, 'dessert_profile' => 'fruit', 'ingredients' => [['name' => 'Pomme', 'quantity' => 4, 'unit' => 'pièce']]];
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipe_ids' => [0, 1]])]]]]])
            ->push(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => [$data]])]]]]])]);
        $plan = MealPlan::create(['user_id' => $owner->id, 'title' => 'Menu', 'start_date' => '2026-10-19', 'days' => 1, 'people' => 4, 'status' => 'queued', 'criteria' => [...$this->criteria(), 'meals' => ['lunch'], 'preferences' => 'private-preferences']]);

        (new GenerateMealPlan($plan->id))->handle(app(HybridMenuPlanner::class));

        $this->assertSame('failed', $plan->fresh()->status);
        $this->assertStringContainsString('recettes proposées par le service', $plan->fresh()->error_message);
        $this->assertSame(0, $plan->meals()->count());
        $this->assertFalse(Recipe::where('is_ai_generated', true)->exists());
        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($plan) {
            $this->assertSame('menus.generation_failed', $message);
            $this->assertSame($plan->id, $context['plan_id']);
            $this->assertSame('generate', $context['diagnostics']['operation']);
            $this->assertSame(['Min'], $context['diagnostics']['validation_rules']['recipes.0.description']);
            foreach (['private-test-key', 'secret-prompt', 'private-preferences', 'Dessert privé'] as $private) {
                $this->assertStringNotContainsString($private, json_encode($context, JSON_UNESCAPED_UNICODE));
            }

            return true;
        });
        Http::assertSentCount(2);
    }

    public function test_invalid_enrichment_reports_field_rules_without_response_values(): void
    {
        config(['services.openai.key' => 'private-test-key']);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => [['id' => 1, 'main_ingredient' => 'private-ingredient']]])]]]]])]);

        try {
            app(OpenAiRecipeService::class)->enrich([['id' => 1, 'title' => 'Recette privée']]);
            $this->fail('Une réponse incomplète ne doit pas être acceptée.');
        } catch (PlanningException $error) {
            $this->assertSame('enrich', $error->diagnostics['operation']);
            $this->assertSame(['Required'], $error->diagnostics['validation_rules']['recipes.0.course_type']);
            $this->assertStringNotContainsString('private-ingredient', json_encode($error->diagnostics));
        }
        Http::assertSentCount(1);
    }

    public function test_unexpected_generation_error_keeps_existing_meals_and_logs_only_its_location(): void
    {
        $owner = User::factory()->create();
        $plan = $this->savedPlan($owner);
        $before = $plan->meals->toArray();
        $plan->update(['status' => 'queued']);
        Log::spy();
        $planner = $this->mock(HybridMenuPlanner::class);
        $planner->shouldReceive('generate')->once()->andThrow(new \RuntimeException('private-test-key private-prompt'));

        (new GenerateMealPlan($plan->id))->handle($planner);

        $this->assertSame('failed', $plan->fresh()->status);
        $this->assertSame('Un problème technique a interrompu la génération. Réessayez plus tard.', $plan->fresh()->error_message);
        $this->assertSame($before, $plan->fresh()->meals->toArray());
        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) {
            $this->assertSame('menus.generation_failed', $message);
            $this->assertSame(\RuntimeException::class, $context['exception_class']);
            $this->assertNotEmpty($context['file']);
            $this->assertGreaterThan(0, $context['line']);
            $this->assertArrayNotHasKey('exception', $context);
            $this->assertStringNotContainsString('private-test-key', json_encode($context));
            $this->assertStringNotContainsString('private-prompt', json_encode($context));

            return true;
        });
        Http::assertNothingSent();
    }

    public function test_api_failure_diagnostics_distinguish_quota_and_rate_limits_and_filter_unknown_codes(): void
    {
        config(['services.openai.key' => 'private-test-key']);
        $codes = ['insufficient_quota', 'rate_limit_exceeded', 'private-upstream-code'];
        $responses = Http::sequence();
        foreach ($codes as $code) {
            $responses->push(['error' => ['code' => $code, 'message' => 'private-response-body']], 429);
        }
        Http::fake(['api.openai.com/*' => $responses]);
        foreach ($codes as $code) {
            try {
                app(OpenAiRecipeService::class)->generate(['missing_courses' => ['dessert']]);
                $this->fail('Une erreur API ne doit pas être acceptée.');
            } catch (PlanningException $error) {
                $this->assertSame(429, $error->diagnostics['http_status']);
                $this->assertSame($code === 'private-upstream-code' ? 'unknown' : $code, $error->diagnostics['api_code']);
                $this->assertStringNotContainsString('private-', json_encode($error->diagnostics));
                $this->assertStringNotContainsString('private-', $error->getMessage());
            }
        }
        Http::assertSentCount(3);
    }

    public function test_preferences_filter_catalogue_recipes_before_a_faster_unwanted_dish_can_be_selected(): void
    {
        $owner = User::factory()->create();
        $this->recipe('starter', 1);
        $french = $this->recipe('main', 1);
        $french->update(['title' => 'Poulet à la française', 'total_minutes' => 40]);
        $this->recipe('dessert', 1);
        $unwanted = $this->recipe('main', 2);
        $unwanted->update(['title' => 'Couscous express', 'total_minutes' => 5]);
        config(['services.openai.key' => 'test-key']);
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipe_ids' => [0, 1, 2]])]]]]])]);
        $plan = MealPlan::create(['user_id' => $owner->id, 'title' => 'Menu français', 'start_date' => '2026-10-19', 'days' => 1, 'people' => 4, 'status' => 'queued', 'criteria' => [...$this->criteria(), 'meals' => ['lunch'], 'preferences' => 'Cuisine française']]);

        (new GenerateMealPlan($plan->id))->handle(app(HybridMenuPlanner::class));

        $this->assertSame('ready', $plan->fresh()->status, $plan->fresh()->error_message ?? '');
        $this->assertSame($french->id, $plan->meals()->firstOrFail()->main_recipe_id);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['input'], 'Cuisine française') && str_contains($request['input'], 'Couscous express'));
    }

    public function test_weekly_variety_counts_couscous_variants_together_and_does_not_repeat_main_recipes(): void
    {
        foreach (['starter', 'main', 'dessert'] as $course) {
            for ($number = 0; $number < 17; $number++) {
                $recipe = $this->recipe($course, $number);
                if ($course === 'main' && $number < 3) {
                    $recipe->update(['title' => 'Couscous variante '.$number]);
                }
            }
        }
        $owner = User::factory()->create();
        $plan = MealPlan::create(['user_id' => $owner->id, 'title' => 'Menus variés', 'start_date' => '2026-10-19', 'days' => 7, 'people' => 4, 'status' => 'queued', 'criteria' => $this->criteria(7)]);
        (new GenerateMealPlan($plan->id))->handle(app(HybridMenuPlanner::class));
        $this->assertSame('ready', $plan->fresh()->status, $plan->fresh()->error_message ?? '');
        $meals = $plan->meals()->get();
        $this->assertCount(14, $meals);
        $this->assertCount(14, $meals->pluck('main_recipe_id')->unique());
        $this->assertLessThanOrEqual(2, $meals->filter(fn ($meal) => str_starts_with($meal->snapshot['main']['title'], 'Couscous'))->count());
        Http::assertNothingSent();
    }

    public function test_preferences_are_checked_on_new_recipes_before_they_are_saved(): void
    {
        $owner = User::factory()->create();
        $this->recipe('starter', 1);
        $this->recipe('main', 1);
        config(['services.openai.key' => 'test-key']);
        $dessert = ['title' => 'Dessert à refuser', 'description' => 'Un dessert qui ne correspond pas aux envies indiquées.', 'steps' => ['Épluchez les pommes et servez-les en petits morceaux.'], 'course_type' => 'dessert', 'servings' => 4, 'total_minutes' => 5, 'difficulty' => 1, 'vegetarian' => true, 'main_ingredient' => 'pomme', 'food_groups' => ['fruit'], 'protein_source' => null, 'dessert_profile' => 'fruit', 'ingredients' => [['name' => 'Pomme', 'quantity' => 4, 'unit' => 'pièce']]];
        $output = fn ($data) => ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]];
        Http::fake(['api.openai.com/*' => Http::sequence()->push($output(['recipe_ids' => [0, 1]]))->push($output(['recipes' => [$dessert]]))->push($output(['recipe_ids' => []]))]);
        $plan = MealPlan::create(['user_id' => $owner->id, 'title' => 'Menu', 'start_date' => '2026-10-19', 'days' => 1, 'people' => 4, 'status' => 'queued', 'criteria' => [...$this->criteria(), 'meals' => ['lunch'], 'preferences' => 'Cuisine française']]);
        (new GenerateMealPlan($plan->id))->handle(app(HybridMenuPlanner::class));
        $this->assertSame('failed', $plan->fresh()->status);
        $this->assertStringContainsString('envies culinaires', $plan->fresh()->error_message);
        $this->assertFalse(Recipe::where('is_ai_generated', true)->exists());
        $this->assertSame(0, $plan->meals()->count());
        Http::assertSentCount(3);
    }

    public function test_similar_dishes_and_vegetable_variants_are_spaced_across_days(): void
    {
        foreach (['starter', 'main', 'dessert'] as $course) {
            for ($number = 0; $number < 17; $number++) {
                $recipe = $this->recipe($course, $number);
                if ($course === 'main' && $number < 5) {
                    $recipe->update(['title' => ['Croziflette savoyarde', 'Tartiflette au reblochon', 'Cabillaud et fondue de poireaux', 'Dinde braisée aux poireaux', 'Poulet aux poireaux'][$number]]);
                }
            }
        }
        $owner = User::factory()->create();
        $plan = MealPlan::create(['user_id' => $owner->id, 'title' => 'Menus variés', 'start_date' => '2026-10-19', 'days' => 7, 'people' => 4, 'status' => 'queued', 'criteria' => $this->criteria(7)]);
        (new GenerateMealPlan($plan->id))->handle(app(HybridMenuPlanner::class));
        $this->assertSame('ready', $plan->fresh()->status, $plan->fresh()->error_message ?? '');
        $meals = $plan->meals()->orderBy('date')->get();
        $diversity = app(MenuDiversity::class);
        foreach ($meals as $index => $meal) {
            $main = $diversity->profile(PlanningRecipe::fromRecord(Recipe::findOrFail($meal->main_recipe_id)));
            foreach ($meals->take($index) as $other) {
                if (abs($meal->date->diffInDays($other->date)) <= 2) {
                    $previous = $diversity->profile(PlanningRecipe::fromRecord(Recipe::findOrFail($other->main_recipe_id)));
                    $this->assertTrue($diversity->allowed('main', $main, ['main' => [$previous]]), $meal->snapshot['main']['title'].' / '.$other->snapshot['main']['title']);
                }
            }
        }
        Http::assertNothingSent();
    }

    public function test_generated_recipes_are_discoverable_in_the_owners_menu_library_before_moderation(): void
    {
        $owner = User::factory()->create();
        $pending = $this->recipe('main', 1, $owner, 'pending');
        $pending->forceFill(['is_ai_generated' => true])->save();
        $this->recipe('main', 2, User::factory()->create(), 'pending')->forceFill(['is_ai_generated' => true])->save();
        $this->get('/recettes?source=menus')->assertForbidden();
        $this->get('/recettes')->assertInertia(fn (Assert $page) => $page->where('recipes.total', 0));
        $this->actingAs($owner)->get('/recettes?source=menus&q=Poulet')->assertInertia(fn (Assert $page) => $page->where('recipes.total', 1)->where('recipes.data.0.slug', $pending->slug)->where('recipes.data.0.private_preview', true));
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('recipes.0.is_ai_generated', true));
        $this->actingAs(User::factory()->create())->get('/recettes?source=menus')->assertInertia(fn (Assert $page) => $page->where('recipes.total', 0));
        $pending->update(['status' => 'published', 'published_at' => now()]);
        $this->get('/recettes')->assertInertia(fn (Assert $page) => $page->where('recipes.total', 1)->where('recipes.data.0.private_preview', false));
    }

    public function test_replacing_a_course_checks_the_following_locked_meal_and_keeps_its_snapshot(): void
    {
        $owner = User::factory()->create();
        $plan = $this->savedPlan($owner);
        $locked = $plan->meals[1];
        $snapshot = $locked->snapshot;
        $snapshot['main']['title'] = 'Cabillaud et fondue de poireaux';
        $snapshot['main']['main_ingredient'] = 'cabillaud';
        $snapshot['main']['ingredients'] = [['name' => 'Poireaux', 'quantity' => 600, 'unit' => 'g']];
        Recipe::findOrFail($locked->main_recipe_id)->forceFill(collect($snapshot['main'])->only(['title', 'main_ingredient', 'ingredients'])->all())->save();
        $locked->update(['locked' => true, 'snapshot' => $snapshot]);
        $this->recipe('main', 3)->update(['title' => 'Poulet aux poireaux', 'total_minutes' => 5]);
        $alternative = $this->recipe('main', 5);
        $plan->update(['status' => 'generating']);
        app(HybridMenuPlanner::class)->generate($plan->fresh(), $plan->meals[0]->id, 'main');
        $this->assertSame($alternative->id, $plan->meals[0]->fresh()->main_recipe_id);
        $this->assertSame($snapshot, $locked->fresh()->snapshot);
        Http::assertNothingSent();
    }

    public function test_a_repetitive_generated_dish_is_retried_and_only_the_varied_recipe_is_saved(): void
    {
        $owner = User::factory()->create();
        foreach (['starter', 'dessert'] as $course) {
            $this->recipe($course, 1);
            $this->recipe($course, 2);
        }
        $this->recipe('main', 1);
        config(['services.openai.key' => 'test-key']);
        $dish = ['title' => 'Poulet citron et riz', 'description' => 'Un plat familial complet à cuisiner pour le menu.', 'steps' => ['Faites cuire le riz dans une casserole et faites revenir les ingrédients du plat.'], 'course_type' => 'main', 'servings' => 4, 'total_minutes' => 25, 'difficulty' => 1, 'vegetarian' => false, 'main_ingredient' => 'poulet', 'food_groups' => ['starch', 'protein'], 'protein_source' => 'poultry', 'dessert_profile' => null, 'ingredients' => [['name' => 'Poulet', 'quantity' => 500, 'unit' => 'g'], ['name' => 'Riz', 'quantity' => 300, 'unit' => 'g']]];
        $varied = [...$dish, 'title' => 'Lentilles mijotées et riz', 'vegetarian' => true, 'main_ingredient' => 'lentilles', 'protein_source' => 'legumes', 'ingredients' => [['name' => 'Lentilles', 'quantity' => 500, 'unit' => 'g'], ['name' => 'Riz', 'quantity' => 300, 'unit' => 'g']]];
        $output = fn ($recipe) => ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => [$recipe]])]]]]];
        Http::fake(['api.openai.com/*' => Http::sequence()->push($output($dish))->push($output($varied))]);
        $plan = MealPlan::create(['user_id' => $owner->id, 'title' => 'Menus variés', 'start_date' => '2026-10-19', 'days' => 2, 'people' => 4, 'status' => 'queued', 'criteria' => [...$this->criteria(2), 'meals' => ['lunch']]]);
        (new GenerateMealPlan($plan->id))->handle(app(HybridMenuPlanner::class));
        $this->assertSame('ready', $plan->fresh()->status, $plan->fresh()->error_message ?? '');
        $this->assertFalse(Recipe::where('title', $dish['title'])->exists());
        $this->assertSame('pending', Recipe::where('title', $varied['title'])->firstOrFail()->status);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request['input'], 'diversity_retry') && str_contains($request['input'], 'avoid_ingredients_by_course'));
    }

    public function test_access_and_quick_meal_slots_are_validated_before_queueing(): void
    {
        Queue::fake();
        $this->get('/menus')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get('/menus')->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->create())->post('/menus', [...$this->criteria(), 'quick_slots' => ['6:lunch']])->assertSessionHasErrors('quick_slots');
        Queue::assertNothingPushed();
        $this->post('/menus', $this->criteria())->assertRedirect();
        Queue::assertPushed(GenerateMealPlan::class);
    }
}
