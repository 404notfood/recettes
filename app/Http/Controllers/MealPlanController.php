<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateMealPlan;
use App\Models\MealPlan;
use App\Models\PlannedMeal;
use App\Models\Recipe;
use App\Services\Menus\MenuPdfExporter;
use App\Services\Menus\OpenAiRecipeService;
use App\Services\Menus\ShoppingList;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MealPlanController extends Controller
{
    public function index(Request $request, OpenAiRecipeService $ai): \Inertia\Response
    {
        return Inertia::render('menus/index', [
            'plans' => MealPlan::where('user_id', $request->user()->id)->latest()->limit(30)->get([
                'id', 'title', 'start_date', 'days', 'people', 'status',
            ]),
            'aiConfigured' => $ai->configured(),
            'catalogueCount' => Recipe::where('status', 'published')->count(),
            'today' => now('Europe/Paris')->format('Y-m-d'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'days' => 'required|integer|in:1,7,14', 'people' => 'required|integer|between:1,20',
            'meals' => 'required|array|min:1|max:2', 'meals.*' => 'required|in:lunch,dinner|distinct',
            'difficulty' => 'required|integer|between:1,3',
            'quick_minutes' => 'required|integer|in:15,30,45',
            'normal_minutes' => 'required|integer|between:15,240|gte:quick_minutes',
            'quick_slots' => 'present|array|max:28',
            'quick_slots.*' => ['required', 'string', 'distinct', 'regex:/^(?:[0-9]|1[0-3]):(?:lunch|dinner)$/'],
            'vegetarian' => 'required|boolean', 'excluded' => 'present|array|max:20',
            'excluded.*' => 'required|string|min:2|max:80|distinct',
            'preferences' => 'nullable|string|max:1000',
        ]);
        foreach ($data['quick_slots'] as $slot) {
            [$day, $meal] = explode(':', $slot);
            if ((int) $day >= $data['days'] || ! in_array($meal, $data['meals'], true)) {
                throw ValidationException::withMessages(['quick_slots' => 'Les créneaux rapides doivent appartenir aux jours et repas sélectionnés.']);
            }
        }
        $plan = MealPlan::create([
            'user_id' => $request->user()->id,
            'title' => 'Menus du '.Carbon::parse($data['start_date'])->format('d/m/Y'),
            'start_date' => $data['start_date'], 'days' => $data['days'], 'people' => $data['people'],
            'criteria' => [...$data, 'preferences' => $data['preferences'] ?? ''], 'status' => 'queued',
        ]);
        GenerateMealPlan::dispatch($plan->id);

        return redirect()->route('menus.show', $plan);
    }

    public function show(Request $request, MealPlan $mealPlan, ShoppingList $shopping): \Inertia\Response
    {
        $this->authorizePlan($request, $mealPlan);
        $mealPlan->load('meals');

        $items = $shopping->forPlan($mealPlan);
        $ids = $mealPlan->meals->flatMap(fn ($meal) => [$meal->starter_recipe_id, $meal->main_recipe_id, $meal->dessert_recipe_id])->filter()->unique();
        $available = Recipe::whereIn('id', $ids)->where(fn ($query) => $query->where('status', 'published')->orWhere(fn ($query) => $query->where('user_id', $request->user()->id)->where('is_ai_generated', true)))->get()->keyBy('id');
        foreach ($mealPlan->meals as $meal) {
            $links = [];
            foreach (['starter', 'main', 'dessert'] as $course) {
                $record = $available->get($meal->{$course.'_recipe_id'});
                $links[$course] = $record ? route('recipes.show', ['recipe' => $record->slug]) : route('menus.recipe', ['mealPlan' => $mealPlan->id, 'meal' => $meal->id, 'course' => $course]);
            }
            $meal->setAttribute('recipe_links', $links);
        }

        return Inertia::render('menus/show', [
            'plan' => $mealPlan, 'shoppingList' => $items, 'shoppingCategories' => $shopping->grouped($items),
        ]);
    }

    public function recipe(Request $request, MealPlan $mealPlan, PlannedMeal $meal, string $course): \Inertia\Response
    {
        $this->authorizePlan($request, $mealPlan);
        abort_unless($meal->meal_plan_id === $mealPlan->id && in_array($course, ['starter', 'main', 'dessert'], true), 404);

        return Inertia::render('menus/recipe', ['plan' => $mealPlan->only(['id', 'title', 'people']), 'recipe' => $meal->snapshot[$course]]);
    }

    public function printRecipes(Request $request, MealPlan $mealPlan, MenuPdfExporter $exporter): Response
    {
        $this->authorizePlan($request, $mealPlan);
        $data = $request->validate([
            'recipes' => 'required|array|min:1|max:84',
            'recipes.*' => ['required', 'string', 'distinct', 'regex:/^[0-9]+:(starter|main|dessert)$/'],
            'format' => ['sometimes', Rule::in(['html', 'pdf'])],
        ]);
        $mealPlan->load('meals');
        $selected = [];
        foreach ($data['recipes'] as $key) {
            [$mealId, $course] = explode(':', $key);
            $meal = $mealPlan->meals->firstWhere('id', (int) $mealId);
            abort_unless($meal && isset($meal->snapshot[$course]), 404);
            $snapshot = $meal->snapshot[$course];
            $fingerprint = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            $snapshot['steps'] = $snapshot['steps'] ?? array_values(array_filter(explode("\n", $snapshot['description'] ?? '')));
            $selected[$fingerprint] = $snapshot;
        }
        if (($data['format'] ?? 'html') === 'pdf') {
            return response($exporter->recipes($mealPlan, array_values($selected)), 200, [
                'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="recettes-du-menu.pdf"',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response()->view('menus.recipes-print', ['plan' => $mealPlan, 'recipes' => array_values($selected), 'pdf' => false])->header('Cache-Control', 'private, no-store');
    }

    public function regenerate(Request $request, MealPlan $mealPlan): RedirectResponse
    {
        $this->authorizePlan($request, $mealPlan);
        $this->enqueue($mealPlan);

        return back();
    }

    public function export(Request $request, MealPlan $mealPlan, MenuPdfExporter $exporter): Response
    {
        $this->authorizePlan($request, $mealPlan);
        $data = $request->validate(['document' => ['sometimes', Rule::in(['full', 'menus', 'shopping'])]]);
        $mealPlan->load('meals');
        $expected = $mealPlan->days * count($mealPlan->criteria['meals']);
        abort_if(in_array($mealPlan->status, ['queued', 'generating'], true) || $mealPlan->meals->count() !== $expected, 409, 'Le planning doit être complet avant son export.');
        $document = $data['document'] ?? 'full';
        $prefix = ['full' => 'menus-et-courses', 'menus' => 'menus', 'shopping' => 'liste-de-courses'][$document];

        return response($exporter->render($mealPlan, $document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$prefix.'-'.$mealPlan->start_date->format('Y-m-d').'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function replace(Request $request, MealPlan $mealPlan, PlannedMeal $meal): RedirectResponse
    {
        $this->authorizePlan($request, $mealPlan);
        abort_unless($meal->meal_plan_id === $mealPlan->id, 404);
        $data = $request->validate(['course' => ['required', Rule::in(['starter', 'main', 'dessert'])]]);
        $this->enqueue($mealPlan, $meal, $data['course']);

        return back();
    }

    public function lock(Request $request, MealPlan $mealPlan, PlannedMeal $meal): RedirectResponse
    {
        $this->authorizePlan($request, $mealPlan);
        abort_unless($meal->meal_plan_id === $mealPlan->id, 404);
        $data = $request->validate(['locked' => 'required|boolean']);
        DB::transaction(function () use ($mealPlan, $meal, $data) {
            $current = MealPlan::whereKey($mealPlan->id)->lockForUpdate()->firstOrFail();
            $this->assertIdle($current);
            $meal->update(['locked' => $data['locked']]);
        });

        return back();
    }

    private function authorizePlan(Request $request, MealPlan $plan): void
    {
        abort_unless($plan->user_id === $request->user()->id, 403);
    }

    private function assertIdle(MealPlan $plan): void
    {
        if (in_array($plan->status, ['queued', 'generating'], true)) {
            throw ValidationException::withMessages(['generation' => 'Ce planning est déjà en cours de génération.']);
        }
    }

    private function enqueue(MealPlan $plan, ?PlannedMeal $meal = null, ?string $course = null): void
    {
        DB::transaction(function () use ($plan, $meal, $course) {
            $current = MealPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $this->assertIdle($current);
            if ($meal && $meal->fresh()->locked) {
                throw ValidationException::withMessages(['generation' => 'Déverrouillez ce repas avant de remplacer une recette.']);
            }
            $current->update(['status' => 'queued', 'error_message' => null]);
            GenerateMealPlan::dispatch($plan->id, $meal?->id, $course)->afterCommit();
        });
    }
}
