<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecipeRequest;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Review;
use App\Services\Menus\DietaryRules;
use App\Services\Menus\PlanningRecipe;
use App\Services\Menus\SeasonalProduce;
use App\Services\RecipeImages;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('welcome', [
            'recipes' => Recipe::with('category')->withAvg('reviews', 'rating')->withCount('reviews')->where('status', 'published')->orderByDesc('featured')->latest('published_at')->limit(6)->get()->map(fn (Recipe $recipe) => $recipe->publicData()),
            'categories' => $this->categories(),
            'recipeCount' => Recipe::where('status', 'published')->count(),
        ]);
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate(['source' => 'nullable|in:menus', 'q' => 'nullable|string|max:100', 'category' => 'nullable|string|max:80', 'difficulty' => 'nullable|in:facile,moyen,avance', 'time' => 'nullable|integer|in:15,30,60', 'tag' => 'nullable|in:vegetarien,vegan,sans-gluten,rapide,economique,de-saison', 'sort' => 'nullable|in:recent,time,rating', 'seasonal' => 'sometimes|boolean', 'diets' => 'sometimes|array|max:7', 'diets.*' => ['required', Rule::in(array_keys(DietaryRules::DIETS))], 'allergens' => 'sometimes|array|max:14', 'allergens.*' => ['required', Rule::in(array_keys(DietaryRules::ALLERGENS))]]);
        $menuLibrary = ($filters['source'] ?? null) === 'menus';
        abort_if($menuLibrary && ! $request->user(), 403);
        $query = Recipe::with('category')->withAvg('reviews', 'rating')->withCount('reviews');
        if ($menuLibrary) {
            $query->where('user_id', $request->user()->id)->where('is_ai_generated', true)->whereIn('status', ['pending', 'published', 'changes_requested']);
        } else {
            $query->where('status', 'published');
        }
        if (! empty($filters['q'])) {
            $search = '%'.mb_strtolower($filters['q']).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', [$search])->orWhereRaw('LOWER(description) LIKE ?', [$search])->orWhereRaw('LOWER(CAST(ingredients AS TEXT)) LIKE ?', [$search]));
        }
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }
        if (! empty($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
        }
        if (! empty($filters['time'])) {
            $query->whereRaw('prep_time + cook_time <= ?', [(int) $filters['time']]);
        }
        if (! empty($filters['tag'])) {
            $query->whereJsonContains('tags', $filters['tag']);
        }
        match ($filters['sort'] ?? 'recent') {
            'time' => $query->orderByRaw('prep_time + cook_time ASC'),
            'rating' => $query->orderByRaw('reviews_avg_rating DESC NULLS LAST'),
            default => $menuLibrary ? $query->latest('created_at') : $query->latest('published_at'),
        };
        if (! empty($filters['seasonal']) || ! empty($filters['diets']) || ! empty($filters['allergens'])) {
            $dietary = app(DietaryRules::class);
            $seasonal = app(SeasonalProduce::class);
            $date = now('Europe/Paris')->format('Y-m-d');
            $ids = (clone $query)->get()->filter(function (Recipe $record) use ($dietary, $seasonal, $date, $filters) {
                $recipe = PlanningRecipe::fromRecord($record);

                return $dietary->eligible($recipe, $filters) && (empty($filters['seasonal']) || $seasonal->eligible($recipe, $date));
            })->modelKeys();
            $query->whereIn('id', $ids);
        }
        $recipes = $query->orderByDesc('id')->paginate(12)->withQueryString()->through(fn (Recipe $recipe) => $recipe->publicData() + ['private_preview' => $recipe->status !== 'published']);

        return Inertia::render('recipes/index', ['recipes' => $recipes, 'categories' => $this->categories(), 'filters' => $filters, 'menuLibraryAvailable' => (bool) $request->user()]);
    }

    public function show(Request $request, Recipe $recipe): Response
    {
        abort_unless($recipe->status === 'published' || ($recipe->is_ai_generated && $request->user() && ($request->user()->id === $recipe->user_id || $request->user()->is_admin)), 404);
        $recipe->load('category')->loadAvg('reviews', 'rating')->loadCount('reviews');

        return Inertia::render('recipes/show', [
            'recipe' => $recipe->publicData(true) + ['private_preview' => $recipe->status !== 'published'],
            'isFavorite' => $request->user() ? $recipe->favoritedBy()->where('users.id', $request->user()->id)->exists() : false,
            'canReply' => $request->user() && ($request->user()->is_admin || $recipe->user_id === $request->user()->id),
            'reviews' => $recipe->reviews()->with('user')->latest()->limit(50)->get()->map(fn (Review $review) => ['id' => $review->id, 'user_id' => $review->user_id, 'author' => $review->user->name, 'content' => $review->content, 'rating' => $review->rating, 'reply' => $review->reply, 'date' => $review->created_at->format('d/m/Y')]),
            'related' => Recipe::with('category')->where('status', 'published')->where('category_id', $recipe->category_id)->where('id', '!=', $recipe->id)->limit(3)->get()->map(fn (Recipe $item) => $item->publicData()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('recipes/create', ['categories' => Category::orderBy('id')->get(['id', 'name', 'slug', 'icon'])]);
    }

    public function store(RecipeRequest $request, RecipeImages $images): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'intent', 'website']);
        $data['user_id'] = $request->user()?->id;
        if ($request->user()) {
            $data['author_name'] = $request->user()->name;
        }
        $data['guest_email'] = $request->user() ? null : $data['guest_email'];
        $data['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(7));
        $data['status'] = $request->user() && $request->input('intent') === 'draft' ? 'draft' : 'pending';
        if ($request->hasFile('image')) {
            $data['image'] = $images->store($request->file('image'));
        }
        Recipe::create($data);
        if ($request->user()) {
            return to_route('dashboard')->with('success', $data['status'] === 'draft' ? 'Brouillon enregistré. À vous de jouer quand vous voulez !' : 'Votre recette est envoyée à notre équipe pour validation.');
        }

        return to_route('recipes.thanks')->with('success', 'Merci ! Votre recette a bien été transmise.');
    }

    public function edit(Recipe $recipe): Response
    {
        Gate::authorize('update', $recipe);

        return Inertia::render('recipes/create', ['categories' => Category::orderBy('id')->get(['id', 'name', 'slug', 'icon']), 'recipe' => array_replace($recipe->only(['id', 'title', 'description', 'category_id', 'prep_time', 'cook_time', 'servings', 'difficulty', 'ingredients', 'steps', 'tags', 'status', 'allergens', 'accompaniment']), $recipe->proposed_changes ?? [])]);
    }

    public function update(RecipeRequest $request, Recipe $recipe, RecipeImages $images): RedirectResponse
    {
        Gate::authorize('update', $recipe);
        $data = $request->safe()->except(['image', 'intent', 'website', 'author_name', 'guest_email']);
        if ($request->hasFile('image')) {
            $data['image'] = $images->store($request->file('image'));
        }
        if ($recipe->status === 'published') {
            $recipe->update(['proposed_changes' => $data, 'moderation_note' => null]);
        } else {
            $data['status'] = $request->input('intent') === 'draft' ? 'draft' : 'pending';
            $recipe->update($data + ['moderation_note' => null]);
        }

        return to_route('dashboard')->with('success', 'Modifications enregistrées. Les changements d’une recette publiée seront validés avant leur mise en ligne.');
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        Gate::authorize('delete', $recipe);
        $recipe->delete();

        return back()->with('success', 'Recette archivée.');
    }

    public function favorite(Request $request, Recipe $recipe): RedirectResponse
    {
        abort_unless($recipe->status === 'published', 404);
        DB::table('favorites')->upsert([['user_id' => $request->user()->id, 'recipe_id' => $recipe->id, 'created_at' => now(), 'updated_at' => now()]], ['user_id', 'recipe_id'], ['updated_at']);

        return back()->with('success', 'Recette ajoutée à votre carnet.');
    }

    public function unfavorite(Request $request, Recipe $recipe): RedirectResponse
    {
        DB::table('favorites')->where('user_id', $request->user()->id)->where('recipe_id', $recipe->id)->delete();

        return back()->with('success', 'Recette retirée de votre carnet.');
    }

    public function review(Request $request, Recipe $recipe): RedirectResponse
    {
        abort_unless($recipe->status === 'published', 404);
        $data = $request->validate(['content' => 'required|string|min:5|max:2000', 'rating' => 'nullable|integer|min:1|max:5']);
        abort_if($recipe->user_id === $request->user()->id && ! empty($data['rating']), 422, 'Vous pouvez répondre aux lecteurs, mais pas noter votre propre recette.');
        $recipe->reviews()->updateOrCreate(['user_id' => $request->user()->id], $data);

        return back()->with('success', 'Merci pour votre retour !');
    }

    public function reply(Request $request, Recipe $recipe, Review $review): RedirectResponse
    {
        Gate::authorize('update', $recipe);
        abort_unless($review->recipe_id === $recipe->id, 404);
        $review->update($request->validate(['reply' => 'required|string|min:3|max:2000']));

        return back()->with('success', 'Votre réponse a été publiée.');
    }

    /** @return Collection<int, Category> */
    private function categories(): Collection
    {
        return Category::withCount(['recipes' => fn ($q) => $q->where('status', 'published')])->orderBy('id')->get();
    }
}
