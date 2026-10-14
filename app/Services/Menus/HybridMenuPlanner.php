<?php

namespace App\Services\Menus;

use App\Models\MealPlan;
use App\Models\Recipe as StoredRecipe;
use App\Services\Menus\PlanningIngredient as Ingredient;
use App\Services\Menus\PlanningRecipe as Recipe;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HybridMenuPlanner
{
    private const COURSES = ['starter', 'main', 'dessert'];

    public function __construct(private OpenAiRecipeService $ai, private RecipeCriteria $rules) {}

    public function generate(MealPlan $plan, ?int $replaceMealId = null, ?string $replaceCourse = null): void
    {
        $criteria = $plan->criteria;
        $catalogue = StoredRecipe::with('category')->where(function ($query) use ($plan) {
            $query->where('status', 'published')->orWhere(function ($query) use ($plan) {
                $query->where('user_id', $plan->user_id)->where('status', 'pending');
            });
        })->orderBy('id')->get()->map(fn ($recipe) => Recipe::fromRecord($recipe));
        $enriched = $this->enrichCatalogue($catalogue, $criteria);
        $eligible = $catalogue->filter(fn ($recipe) => $this->rules->eligible($recipe, $criteria));
        $plan->load('meals');
        $prepared = [];
        $drafts = [];
        $usage = [];
        $weekly = [];
        $previous = [];
        // Frozen meals retain their snapshots, even if their recipes are edited later.
        foreach ($plan->meals as $meal) {
            if ($meal->locked || ($replaceMealId !== null && $meal->id !== $replaceMealId)) {
                $week = intdiv((int) CarbonImmutable::parse($plan->start_date)->diffInDays($meal->date), 7);
                $this->trackWeek($meal->snapshot, $weekly[$week]);
                foreach ($meal->snapshot as $recipe) {
                    $key = Str::lower(Str::ascii(trim($recipe['title'])));
                    $usage[$key] = ($usage[$key] ?? 0) + 1;
                }
            }
        }

        for ($offset = 0; $offset < $plan->days; $offset++) {
            $date = CarbonImmutable::parse($plan->start_date)->addDays($offset)->format('Y-m-d');
            foreach ($criteria['meals'] as $type) {
                $week = intdiv($offset, 7);
                $weekMeals = min(7, $plan->days - $week * 7) * count($criteria['meals']);
                $counts = $weekly[$week] ?? [];
                $old = $plan->meals->first(fn ($meal) => $meal->date->format('Y-m-d') === $date && $meal->meal_type === $type);
                if ($old && ($old->locked || ($replaceMealId !== null && $old->id !== $replaceMealId))) {
                    $previous = $old->snapshot;

                    continue;
                }
                $quick = in_array($offset.':'.$type, $criteria['quick_slots'], true);
                $limit = $quick ? $criteria['quick_minutes'] : $criteria['normal_minutes'];
                $fixed = [];
                $banned = [];
                if ($old && $replaceCourse) {
                    foreach ($old->snapshot as $course => $snapshot) {
                        if ($course === $replaceCourse) {
                            $banned[] = Str::lower(Str::ascii(trim($snapshot['title'])));

                            continue;
                        }
                        $fixed[$course] = $this->fromSnapshot($snapshot);
                        $fixed[$course]->id = $old->{$course.'_recipe_id'};
                    }
                }
                $selected = $this->select($eligible, $criteria, $limit, $usage, $previous, $fixed, $banned, $counts, $weekMeals);
                $missing = array_values(array_diff(self::COURSES, array_keys($selected)));
                if ($missing) {
                    $remaining = $limit - array_sum(array_map(fn ($recipe) => $recipe->total_minutes, $selected));
                    $generated = $this->ai->generate([
                        'missing_courses' => $missing, 'remaining_minutes' => $remaining,
                        'people' => $plan->people, 'difficulty' => $criteria['difficulty'],
                        'vegetarian' => $criteria['vegetarian'], 'excluded' => $criteria['excluded'],
                        'preferences' => $criteria['preferences'], 'date' => $date, 'meal' => $type,
                        'required_food_groups' => array_values(array_diff(['vegetables', 'starch', 'protein'], array_unique(array_merge(...array_values(array_map(fn ($recipe) => $recipe->food_groups ?? [], array_intersect_key($selected, array_flip(['starter', 'main'])))))))),
                        'allowed_protein_sources' => array_values(array_filter(RecipeCriteria::PROTEINS, fn ($source) => $this->rules->allowedByWeek(new Recipe(['course_type' => 'main', 'protein_source' => $source]), $counts, $weekMeals)
                            && (! $criteria['vegetarian'] || in_array($source, ['eggs', 'legumes', 'plant', 'dairy'], true)))),
                        'allow_treat_dessert' => $this->rules->allowedByWeek(new Recipe(['course_type' => 'dessert', 'dessert_profile' => 'treat']), $counts, $weekMeals),
                        'existing_courses' => array_map(fn ($recipe) => ['title' => $recipe->title, 'main_ingredient' => $recipe->main_ingredient], $selected),
                        'avoid_titles' => array_values(array_unique([...array_keys($usage), ...$banned])),
                    ]);
                    foreach ($generated as $data) {
                        $ingredients = $data['ingredients'];
                        if (! is_array($ingredients)) {
                            throw new PlanningException('Les ingrédients proposés sont incomplets.');
                        }
                        unset($data['ingredients']);
                        $recipe = new Recipe([...$data, 'user_id' => $plan->user_id, 'is_ai_generated' => true, 'planning_estimated' => true]);
                        $recipe->setRelation('ingredients', collect($ingredients)->map(fn ($ingredient) => new Ingredient($ingredient)));
                        $fingerprint = $this->rules->fingerprint($recipe);
                        if (! $this->rules->eligible($recipe, $criteria)
                            || ! $this->rules->allowedByWeek($recipe, $counts, $weekMeals)
                            || in_array($fingerprint, $banned, true)
                            || $catalogue->contains(fn ($item) => $this->rules->fingerprint($item) === $fingerprint)) {
                            throw new PlanningException('Une recette créée ne respecte pas les critères ou existe déjà. Ajustez les critères et réessayez.');
                        }
                        $drafts[] = $recipe;
                        $catalogue->push($recipe);
                        $eligible->push($recipe);
                        $selected[$recipe->course_type] = $recipe;
                    }
                }
                if (count($selected) !== 3 || ! $this->rules->balanced($selected)
                    || array_sum(array_map(fn ($recipe) => $recipe->total_minutes, $selected)) > $limit) {
                    throw new PlanningException('Impossible de composer un repas complet avec légumes, féculents et protéines dans le temps demandé.');
                }
                $this->trackWeek(array_map(fn ($recipe) => $recipe->only(['protein_source', 'dessert_profile']), $selected), $weekly[$week]);
                foreach ($selected as $recipe) {
                    $key = $this->rules->fingerprint($recipe);
                    $usage[$key] = ($usage[$key] ?? 0) + 1;
                }
                $previous = array_map(fn ($recipe) => ['title' => $recipe->title, 'main_ingredient' => $recipe->main_ingredient, 'protein_source' => $recipe->protein_source], $selected);
                $prepared[] = compact('date', 'type', 'quick', 'selected');
            }
        }

        // API calls happen before the transaction: no partially imported menu on failure.
        DB::transaction(function () use ($plan, $enriched, $drafts, $prepared) {
            $current = MealPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== 'generating') {
                throw new PlanningException('Ce planning n’est plus en cours de génération.');
            }
            foreach ($enriched as $recipe) {
                $recipe->saveMetadata();
            }
            foreach ($drafts as $recipe) {
                $recipe->saveDraft($plan->user_id);
            }
            foreach ($prepared as $item) {
                $selected = $item['selected'];
                $plan->meals()->updateOrCreate(['date' => $item['date'], 'meal_type' => $item['type']], [
                    'quick' => $item['quick'], 'total_minutes' => array_sum(array_map(fn ($recipe) => $recipe->total_minutes, $selected)),
                    'starter_recipe_id' => $selected['starter']->id, 'main_recipe_id' => $selected['main']->id,
                    'dessert_recipe_id' => $selected['dessert']->id,
                    'snapshot' => array_map(fn ($recipe) => $this->snapshot($recipe), $selected),
                ]);
            }
            $current->update(['status' => 'ready', 'error_message' => null]);
        });
    }

    /**
     * @param  Collection<int,Recipe>  $catalogue
     * @param  array<string,mixed>  $criteria
     * @return array<int,Recipe>
     */
    private function enrichCatalogue(Collection $catalogue, array $criteria): array
    {
        if (! $this->ai->configured()) {
            return [];
        }
        $complete = $catalogue->filter(fn ($recipe) => $this->rules->eligible($recipe, $criteria));
        $minimum = 0;
        foreach (self::COURSES as $course) {
            $duration = $complete->where('course_type', $course)->min('total_minutes');
            if ($duration === null) {
                $minimum = PHP_INT_MAX;
                break;
            }
            $minimum += $duration;
        }
        $limit = $criteria['quick_slots'] ? $criteria['quick_minutes'] : $criteria['normal_minutes'];
        if ($minimum <= $limit) {
            $candidate = $this->select($complete, $criteria, $limit, [], [], [], [], [], min(7, $criteria['days']) * count($criteria['meals']));
            if (count($candidate) === 3) {
                return [];
            }
        }
        $incomplete = $catalogue->filter(fn ($recipe) => $recipe->ingredients->isNotEmpty()
            && ! $this->rules->containsExcluded($recipe, $criteria['excluded'])
            && (! $recipe->course_type || ! $recipe->servings || ! $recipe->total_minutes || ! $recipe->difficulty || $recipe->vegetarian === null
                || ! $recipe->food_groups || ($recipe->course_type === 'main' && $recipe->protein_source === null)
                || ($recipe->course_type === 'dessert' && $recipe->dessert_profile === null)));
        // Bound enrichment per generation; prioritize each course rather than one category.
        $candidates = collect();
        foreach ([...self::COURSES, null] as $course) {
            $candidates = $candidates->concat($incomplete->where('course_type', $course)->take(6));
        }
        foreach ($candidates->unique('id')->chunk(6) as $batch) {
            $input = array_values($batch->map(fn ($recipe) => [
                'id' => $recipe->id, 'title' => $recipe->title, 'description' => $recipe->description,
                'ingredients' => $recipe->ingredients->pluck('name')->all(),
            ])->all());
            foreach ($this->ai->enrich($input) as $metadata) {
                $recipe = $catalogue->firstWhere('id', $metadata['id']);
                unset($metadata['id']);
                // Preserve fields already supplied by the author.
                foreach ($metadata as $key => $value) {
                    if ($recipe->$key === null || ($key === 'food_groups' && ! $recipe->food_groups)) {
                        $recipe->$key = $value;
                    }
                }
                $recipe->planning_estimated = true;
            }
        }

        return $candidates->unique('id')->all();
    }

    /**
     * @param  Collection<int,Recipe>  $catalogue
     * @param  array<string,mixed>  $criteria
     * @param  array<string,int>  $usage
     * @param  array<string,array<string,mixed>>  $previous
     * @param  array<string,Recipe>  $fixed
     * @param  list<string>  $banned
     * @param  array<string,int>  $counts
     * @return array<string,Recipe>
     */
    private function select(Collection $catalogue, array $criteria, int $limit, array $usage, array $previous, array $fixed, array $banned, array $counts, int $weekMeals): array
    {
        $pools = [];
        $maxUses = 2 * (int) ceil($criteria['days'] / 7);
        foreach (self::COURSES as $course) {
            if (isset($fixed[$course])) {
                $pools[$course] = [$fixed[$course]];

                continue;
            }
            $pools[$course] = $catalogue->filter(fn ($recipe) => $recipe->course_type === $course
                && $recipe->total_minutes <= $limit - 2
                && ($usage[$this->rules->fingerprint($recipe)] ?? 0) < $maxUses
                && $this->rules->allowedByWeek($recipe, $counts, $weekMeals)
                && ! in_array($this->rules->fingerprint($recipe), $banned, true))
                ->sortBy(fn ($recipe) => ($usage[$this->rules->fingerprint($recipe)] ?? 0) * 100 + $recipe->total_minutes)
                ->take(20)->values()->all();
            $pools[$course][] = null;
        }
        $best = [];
        $bestScore = -INF;
        foreach ($pools['starter'] as $starter) {
            foreach ($pools['main'] as $main) {
                foreach ($pools['dessert'] as $dessert) {
                    $recipes = array_filter(compact('starter', 'main', 'dessert'));
                    $minutes = array_sum(array_map(fn ($recipe) => $recipe->total_minutes, $recipes));
                    if ($minutes + (3 - count($recipes)) > $limit || ! $this->rules->balanced($recipes)) {
                        continue;
                    }
                    $score = count($recipes) * 100000 - $minutes;
                    if ($main) {
                        $score -= ($counts[$main->protein_source] ?? 0) * 1200;
                        if (($previous['main']['protein_source'] ?? null) === $main->protein_source) {
                            $score -= 2000;
                        }
                    }
                    if ($dessert && $dessert->dessert_profile === 'treat') {
                        $score -= 250;
                    }
                    $dominants = [];
                    foreach ($recipes as $course => $recipe) {
                        $key = $this->rules->fingerprint($recipe);
                        $score -= ($usage[$key] ?? 0) * 1000;
                        if (($previous[$course]['title'] ?? null) === $recipe->title) {
                            $score -= 3000;
                        }
                        $dominant = Str::lower(Str::ascii($recipe->main_ingredient ?? ''));
                        if ($dominant && in_array($dominant, $dominants, true)) {
                            $score -= 500;
                        }
                        if ($dominant && $course === 'main' && $dominant === Str::lower(Str::ascii($previous['main']['main_ingredient'] ?? ''))) {
                            $score -= 1500;
                        }
                        $dominants[] = $dominant;
                    }
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = $recipes;
                    }
                }
            }
        }

        return $best;
    }

    /**
     * @return array<string,mixed>
     */
    private function snapshot(Recipe $recipe): array
    {
        return [...$recipe->only(['id', 'slug', 'title', 'description', 'steps', 'course_type', 'servings', 'total_minutes', 'difficulty', 'vegetarian', 'main_ingredient', 'is_ai_generated', 'planning_estimated', 'food_groups', 'protein_source', 'dessert_profile']),
            'ingredients' => $recipe->ingredients->map(fn ($ingredient) => $ingredient->only(['name', 'quantity', 'unit']))->all(),
        ];
    }

    /**
     * @param  array<string,mixed>  $snapshot
     */
    private function fromSnapshot(array $snapshot): Recipe
    {
        $recipe = new Recipe;
        if (! isset($snapshot['ingredients']) || ! is_array($snapshot['ingredients'])) {
            throw new PlanningException('Cette fiche conservée ne contient pas ses ingrédients.');
        }
        $recipe->forceFill(collect($snapshot)->except('ingredients')->all());
        $recipe->setRelation('ingredients', collect($snapshot['ingredients'])->map(fn ($data) => new Ingredient($data)));

        return $recipe;
    }

    /**
     * @param  array<string,array<string,mixed>>  $snapshot
     * @param  array<string,int>|null  $counts
     *
     * @param-out array<string,int> $counts
     */
    private function trackWeek(array $snapshot, ?array &$counts): void
    {
        $counts ??= [];
        $protein = $snapshot['main']['protein_source'] ?? null;
        if (is_string($protein) && $protein !== '') {
            $counts[$protein] = ($counts[$protein] ?? 0) + 1;
        }
        if (($snapshot['dessert']['dessert_profile'] ?? null) === 'treat') {
            $counts['treat'] = ($counts['treat'] ?? 0) + 1;
        }
    }
}
