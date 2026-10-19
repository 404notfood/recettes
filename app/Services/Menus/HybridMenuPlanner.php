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

    public function __construct(private OpenAiRecipeService $ai, private RecipeCriteria $rules, private MenuDiversity $diversity) {}

    public function generate(MealPlan $plan, ?int $replaceMealId = null, ?string $replaceCourse = null): void
    {
        $criteria = ['start_date' => $plan->start_date->format('Y-m-d'), ...$plan->criteria];
        $catalogue = StoredRecipe::with('category')->where(function ($query) use ($plan) {
            $query->where('status', 'published')->orWhere(function ($query) use ($plan) {
                $query->where('user_id', $plan->user_id)->where('status', 'pending');
            });
        })->orderBy('id')->get()->map(fn ($recipe) => Recipe::fromRecord($recipe));
        $matching = $this->matchingPreferences($catalogue, $criteria);
        $enriched = $this->enrichCatalogue($matching, $criteria);
        $eligible = $matching->filter(fn ($recipe) => $this->rules->eligible($recipe, [...$criteria, 'seasonal' => false]));
        $plan->load('meals');
        $prepared = [];
        $drafts = [];
        $usage = [];
        $weekly = [];
        $previous = [];
        $history = [];
        // Frozen meals retain their snapshots, even if their recipes are edited later.
        foreach ($plan->meals as $meal) {
            if ($meal->locked || ($replaceMealId !== null && $meal->id !== $replaceMealId)) {
                $history[] = ['date' => $meal->date->format('Y-m-d'), 'profiles' => array_map(fn ($snapshot) => $this->diversity->profile($this->fromSnapshot($snapshot)), $meal->snapshot)];
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
                $recent = [];
                foreach ($history as $item) {
                    if (abs(CarbonImmutable::parse($date)->diffInDays($item['date'])) <= 2) {
                        foreach ($item['profiles'] as $course => $profile) {
                            $recent[$course][] = $profile;
                        }
                    }
                }
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
                $selected = $this->select($eligible, $criteria, $limit, $usage, $previous, $fixed, $banned, $counts, $weekMeals, $recent, $date);
                $missing = array_values(array_diff(self::COURSES, array_keys($selected)));
                if ($missing) {
                    $remaining = $limit - array_sum(array_map(fn ($recipe) => $recipe->total_minutes, $selected));
                    $avoidIngredients = array_map(fn ($profiles) => array_values(array_unique(array_merge(...array_column($profiles, 'ingredients')))), $recent);
                    $avoidIngredients['main'] = array_values(array_unique([...($avoidIngredients['main'] ?? []), ...array_map(fn ($key) => substr($key, 11), array_keys(array_filter($counts, fn ($count, $key) => str_starts_with($key, 'ingredient:') && $count >= max(1, (int) ceil($weekMeals / 7)), ARRAY_FILTER_USE_BOTH)))]));
                    $context = [
                        'missing_courses' => $missing, 'remaining_minutes' => $remaining,
                        'people' => $plan->people, 'difficulty' => $criteria['difficulty'],
                        'vegetarian' => $criteria['vegetarian'], 'excluded' => $criteria['excluded'],
                        'preferences' => $criteria['preferences'], 'date' => $date, 'meal' => $type,
                        'diets' => $criteria['diets'] ?? [], 'excluded_allergens' => $criteria['allergens'] ?? [],
                        'seasonal' => $criteria['seasonal'] ?? false,
                        'seasonal_produce' => ($criteria['seasonal'] ?? false) ? app(SeasonalProduce::class)->suggestions($date) : [],
                        'variety' => $criteria['variety'] ?? 'flexible',
                        'weekly_protein_needs' => $this->rules->weeklyNeeds($counts, $weekMeals, $criteria),
                        'recent_meal' => $previous,
                        'avoid_main_ingredients' => $avoidIngredients['main'],
                        'avoid_dish_families' => array_values(array_unique([...array_column($recent['main'] ?? [], 'family'), ...array_map(fn ($key) => substr($key, 7), array_keys(array_filter($counts, fn ($count, $key) => str_starts_with($key, 'family:') && $count >= max(1, (int) ceil($weekMeals / 7)), ARRAY_FILTER_USE_BOTH)))])),
                        'avoid_ingredients_by_course' => $avoidIngredients,
                        'nearby_recipes_by_course' => array_map(fn ($profiles) => array_column($profiles, 'title'), $recent),
                        'required_food_groups' => array_values(array_diff(['vegetables', 'starch', 'protein'], array_unique(array_merge(...array_values(array_map(fn ($recipe) => $recipe->food_groups ?? [], array_intersect_key($selected, array_flip(['starter', 'main'])))))))),
                        'allowed_protein_sources' => array_values(array_filter(RecipeCriteria::PROTEINS, fn ($source) => $this->rules->allowedByWeek(new Recipe(['course_type' => 'main', 'protein_source' => $source]), $counts, $weekMeals, $criteria)
                            && (! $criteria['vegetarian'] || in_array($source, ['eggs', 'legumes', 'plant', 'dairy'], true)))),
                        'allow_treat_dessert' => $this->rules->allowedByWeek(new Recipe(['course_type' => 'dessert', 'dessert_profile' => 'treat']), $counts, $weekMeals, $criteria),
                        'existing_courses' => array_map(fn ($recipe) => ['title' => $recipe->title, 'main_ingredient' => $recipe->main_ingredient], $selected),
                        'avoid_titles' => array_values(array_unique([...array_keys($usage), ...$banned])),
                    ];
                    // Retry a conflicting proposal before giving up; rejected recipes
                    // never enter the catalogue or the transaction.
                    for ($attempt = 0; $attempt < 3; $attempt++) {
                        $proposed = $selected;
                        $newRecipes = [];
                        $conflict = false;
                        foreach ($this->ai->generate($context) as $data) {
                            $ingredients = $data['ingredients'];
                            if (! is_array($ingredients)) {
                                throw new PlanningException('Les ingrédients proposés sont incomplets.');
                            }
                            unset($data['ingredients']);
                            $recipe = new Recipe([...$data, 'user_id' => $plan->user_id, 'is_ai_generated' => true, 'planning_estimated' => true]);
                            $recipe->setRelation('ingredients', collect($ingredients)->map(fn ($ingredient) => new Ingredient($ingredient)));
                            $fingerprint = $this->rules->fingerprint($recipe);
                            if (! $this->rules->eligible($recipe, $criteria, $date)) {
                                throw new PlanningException('Une recette créée ne respecte pas les critères. Ajustez les critères et réessayez.');
                            }
                            $conflict = $conflict || ! $this->rules->allowedByWeek($recipe, $counts, $weekMeals, $criteria)
                                || ! $this->diversity->allowed($recipe->course_type, $this->diversity->profile($recipe), $recent)
                                || ($recipe->course_type === 'main' && ! $this->diversity->allowedByWeek($this->diversity->profile($recipe), $counts, $weekMeals))
                                || in_array($fingerprint, $banned, true)
                                || $catalogue->contains(fn ($item) => $this->rules->fingerprint($item) === $fingerprint);
                            $newRecipes[] = $recipe;
                            $proposed[$recipe->course_type] = $recipe;
                        }
                        if (! $conflict && (isset($fixed['starter'], $fixed['main']) || $this->diversity->compatible($proposed))) {
                            foreach ($newRecipes as $recipe) {
                                $drafts[] = $recipe;
                                $catalogue->push($recipe);
                                $eligible->push($recipe);
                            }
                            $selected = $proposed;
                            break;
                        }
                        $context['avoid_titles'] = array_values(array_unique([...$context['avoid_titles'], ...array_map(fn ($recipe) => $recipe->title, $newRecipes)]));
                        $context['diversity_retry'] = 'La proposition précédente répétait un plat ou un ingrédient dominant. Change réellement de plat et de légumes, en respectant les deux jours voisins et les autres rôles du repas.';
                        if ($attempt === 2) {
                            throw new PlanningException('Les propositions restent trop répétitives. Réessayez avec davantage de temps ou des envies moins restrictives.');
                        }
                    }
                }
                if (count($selected) !== 3 || ! $this->rules->balanced($selected, $criteria)
                    || (! isset($fixed['starter'], $fixed['main']) && ! $this->diversity->compatible($selected))
                    || array_sum(array_map(fn ($recipe) => $recipe->total_minutes, $selected)) > $limit) {
                    throw new PlanningException('Impossible de composer un repas complet avec légumes, féculents et protéines dans le temps demandé.');
                }
                $this->trackWeek(array_map(fn ($recipe) => $this->snapshot($recipe), $selected), $weekly[$week]);
                foreach ($selected as $recipe) {
                    $key = $this->rules->fingerprint($recipe);
                    $usage[$key] = ($usage[$key] ?? 0) + 1;
                }
                $previous = array_map(fn ($recipe) => ['title' => $recipe->title, 'main_ingredient' => $recipe->main_ingredient, 'protein_source' => $recipe->protein_source], $selected);
                $history[] = ['date' => $date, 'profiles' => array_map(fn ($recipe) => $this->diversity->profile($recipe), $selected)];
                $prepared[] = compact('date', 'type', 'quick', 'selected');
            }
        }

        // Verify newly generated recipes against the same preferences before saving anything.
        if ($drafts && count($this->matchingPreferences(collect($drafts), $criteria)) !== count($drafts)) {
            throw new PlanningException('Les recettes proposées ne respectent pas vos envies culinaires. Réessayez la génération.');
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
     * @return Collection<int,Recipe>
     */
    private function matchingPreferences(Collection $catalogue, array $criteria): Collection
    {
        $preferences = trim($criteria['preferences'] ?? '');
        if ($preferences === '' || $catalogue->isEmpty()) {
            return $catalogue;
        }
        if (! $this->ai->configured()) {
            throw new PlanningException('La prise en compte de vos envies nécessite le service de création de recettes. Vérifiez sa configuration côté serveur.');
        }
        $matches = collect();
        // Temporary local IDs also cover new recipes that have not been saved yet.
        foreach ($catalogue->values()->chunk(40) as $batch) {
            $input = array_values($batch->map(fn ($recipe, $index) => [
                'id' => $index, 'title' => $recipe->title, 'description' => $recipe->description,
                'course_type' => $recipe->course_type, 'ingredients' => $recipe->ingredients->pluck('name')->all(),
            ])->all());
            $ids = $this->ai->preferenceMatches($input, $preferences);
            $matches = $matches->concat($batch->filter(fn ($recipe, $index) => in_array($index, $ids, true)));
        }

        return $matches->values();
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
                || ($recipe->course_type === 'dessert' && $recipe->dessert_profile === null)
                || ($recipe->allergens === null && (! empty($criteria['allergens']) || array_intersect($criteria['diets'] ?? [], ['gluten_free', 'lactose_free'])))));
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
     * @param  array<string,list<array{title:string,family:string,ingredients:list<string>}>>  $recent
     * @return array<string,Recipe>
     */
    private function select(Collection $catalogue, array $criteria, int $limit, array $usage, array $previous, array $fixed, array $banned, array $counts, int $weekMeals, array $recent = [], ?string $date = null): array
    {
        $pools = [];
        foreach (self::COURSES as $course) {
            $maxUses = ($course === 'main' || ($criteria['variety'] ?? 'flexible') === 'varied' ? 1 : 2) * (int) ceil($criteria['days'] / 7);
            if (isset($fixed[$course])) {
                $pools[$course] = [$fixed[$course]];

                continue;
            }
            $pools[$course] = $catalogue->filter(fn ($recipe) => $recipe->course_type === $course
                && $this->rules->eligible($recipe, $criteria, $date)
                && $recipe->total_minutes <= $limit - 2
                && ($usage[$this->rules->fingerprint($recipe)] ?? 0) < $maxUses
                && $this->rules->allowedByWeek($recipe, $counts, $weekMeals, $criteria)
                && ($course !== 'main' || $this->diversity->allowedByWeek($this->diversity->profile($recipe), $counts, $weekMeals))
                && $this->diversity->allowed($course, $this->diversity->profile($recipe), $recent)
                && ! in_array($this->rules->fingerprint($recipe), $banned, true))
                ->sortBy(fn ($recipe) => ($usage[$this->rules->fingerprint($recipe)] ?? 0) * 10000 + ($counts['family:'.$this->rules->family($recipe)] ?? 0) * 1000 + $recipe->total_minutes)
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
                    if ($minutes + (3 - count($recipes)) > $limit || ! $this->rules->balanced($recipes, $criteria)
                        || (! isset($fixed['starter'], $fixed['main']) && ! $this->diversity->compatible($recipes))) {
                        continue;
                    }
                    $score = count($recipes) * 100000 - $minutes;
                    if ($main) {
                        $score -= ($counts['family:'.$this->rules->family($main)] ?? 0) * 4000;
                        $score -= ($counts[$main->protein_source] ?? 0) * 1200;
                        $score += ($this->rules->weeklyNeeds($counts, $weekMeals, $criteria)[$main->protein_source] ?? 0) * 1800;
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
        return [...$recipe->only(['id', 'slug', 'title', 'description', 'steps', 'course_type', 'servings', 'total_minutes', 'difficulty', 'vegetarian', 'main_ingredient', 'is_ai_generated', 'planning_estimated', 'food_groups', 'protein_source', 'dessert_profile', 'allergens', 'accompaniment']),
            'allergens' => $recipe->allergens === null ? null : app(DietaryRules::class)->detectedAllergens($recipe),
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
        $counts['meals'] = ($counts['meals'] ?? 0) + 1;
        foreach (['starter', 'dessert'] as $course) {
            if (isset($snapshot[$course]['title'])) {
                $key = 'recipe:'.Str::lower(Str::ascii(trim($snapshot[$course]['title'])));
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        $protein = $snapshot['main']['protein_source'] ?? null;
        if (isset($snapshot['main']['title'])) {
            $main = new Recipe(['title' => $snapshot['main']['title']]);
            $recipeKey = 'recipe:'.$this->rules->fingerprint($main);
            $counts[$recipeKey] = ($counts[$recipeKey] ?? 0) + 1;
            $familyKey = 'family:'.$this->rules->family($main);
            $counts[$familyKey] = ($counts[$familyKey] ?? 0) + 1;
            foreach ($this->diversity->profile($this->fromSnapshot($snapshot['main']))['ingredients'] as $ingredient) {
                $key = 'ingredient:'.$ingredient;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        if (is_string($protein) && $protein !== '') {
            $counts[$protein] = ($counts[$protein] ?? 0) + 1;
        }
        if (($snapshot['dessert']['dessert_profile'] ?? null) === 'treat') {
            $counts['treat'] = ($counts['treat'] ?? 0) + 1;
        }
    }
}
