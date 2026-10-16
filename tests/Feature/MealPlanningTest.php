<?php

namespace Tests\Feature;

use App\Jobs\GenerateMealPlan;
use App\Models\Category;
use App\Models\MealPlan;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Menus\CatalogueMetadata;
use App\Services\Menus\HybridMenuPlanner;
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
        $ingredient = ['starter' => 'Carotte', 'main' => 'Poulet', 'dessert' => 'Pomme'][$course];
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
        $this->actingAs($owner)->get(route('menus.show', $plan))->assertInertia(fn (Assert $page) => $page->component('menus/show')->where('plan.meals.0.recipe_links.main', route('recipes.show', ['recipe' => 'main-1']))->where('shoppingCategories.0.label', 'Légumes')->where('shoppingCategories.0.items.0.quantity', 800));
    }

    public function test_print_contains_only_selected_unique_recipes_with_scaled_amounts_and_no_images(): void
    {
        $owner = User::factory()->create();
        $plan = $this->savedPlan($owner);
        $first = $plan->meals[0];
        $second = $plan->meals[1];
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

    public function test_invalid_generated_recipe_is_diagnosed_without_logging_private_values(): void
    {
        Log::spy();
        $owner = User::factory()->create();
        $this->recipe('starter', 1);
        $this->recipe('main', 1);
        config(['services.openai.key' => 'private-test-key']);
        $data = ['title' => 'Dessert privé', 'description' => 'secret-prompt', 'steps' => ['Préparez les pommes et servez-les en petits morceaux.'], 'course_type' => 'dessert', 'servings' => 4, 'total_minutes' => 10, 'difficulty' => 1, 'vegetarian' => true, 'main_ingredient' => 'pomme', 'food_groups' => ['fruit'], 'protein_source' => null, 'dessert_profile' => 'fruit', 'ingredients' => [['name' => 'Pomme', 'quantity' => 4, 'unit' => 'pièce']]];
        Http::fake(['api.openai.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['recipes' => [$data]])]]]]])]);
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
        Http::assertSentCount(1);
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
