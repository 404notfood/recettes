<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $recipes = Recipe::with('category')->where('user_id', $request->user()->id)->latest()->get();

        return Inertia::render('dashboard', [
            'recipes' => $recipes->map(fn (Recipe $recipe) => $recipe->publicData() + ['status' => $recipe->status, 'is_ai_generated' => $recipe->is_ai_generated, 'has_revision' => $recipe->proposed_changes !== null, 'moderation_note' => $recipe->moderation_note]),
            'stats' => ['total' => $recipes->count(), 'published' => $recipes->where('status', 'published')->count(), 'pending' => $recipes->where('status', 'pending')->count(), 'favorites' => DB::table('favorites')->where('user_id', $request->user()->id)->count()],
        ]);
    }

    public function favorites(Request $request): Response
    {
        $recipes = Recipe::with('category')->where('status', 'published')->whereHas('favoritedBy', fn ($q) => $q->where('users.id', $request->user()->id))->latest()->get()->map(fn (Recipe $recipe) => $recipe->publicData());

        return Inertia::render('favorites', ['recipes' => $recipes]);
    }

    public function admin(Request $request): Response
    {
        abort_unless($request->user()->is_admin, 403);

        return Inertia::render('admin/index', [
            'pending' => Recipe::with('category')->where(fn ($q) => $q->where('status', 'pending')->orWhere(fn ($q) => $q->whereNotNull('proposed_changes')->whereNull('moderation_note')))->oldest()->get()->map(fn (Recipe $r) => array_replace($r->publicData(true), $r->proposed_changes ?? []) + ['status' => $r->status, 'has_revision' => $r->proposed_changes !== null, 'guest_email' => $r->getAttribute('guest_email')]),
            'recipes' => Recipe::with('category')->latest()->paginate(20)->through(fn (Recipe $r) => $r->publicData() + ['status' => $r->status]),
            'categories' => Category::withCount('recipes')->orderBy('id')->get(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'is_admin', 'created_at']),
            'stats' => ['published' => Recipe::where('status', 'published')->count(), 'pending' => Recipe::where('status', 'pending')->orWhere(fn ($q) => $q->whereNotNull('proposed_changes')->whereNull('moderation_note'))->count(), 'members' => User::count(), 'categories' => Category::count()],
        ]);
    }

    public function moderate(Request $request, Recipe $recipe): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate(['action' => 'required|in:publish,changes,feature,unfeature', 'note' => 'required_if:action,changes|nullable|string|min:5|max:1000']);
        if ($data['action'] === 'publish') {
            $recipe->update(($recipe->proposed_changes ?? []) + ['status' => 'published', 'published_at' => $recipe->published_at ?? now(), 'proposed_changes' => null, 'moderation_note' => null]);
        } elseif ($data['action'] === 'changes') {
            $recipe->update(['status' => $recipe->status === 'published' ? 'published' : 'changes_requested', 'moderation_note' => $data['note']]);
        } else {
            $recipe->update(['featured' => $data['action'] === 'feature']);
        }

        return back()->with('success', 'La recette a été mise à jour.');
    }

    public function category(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate(['name' => 'required|string|max:80|unique:categories,name', 'description' => 'nullable|string|max:255']);
        $slug = Str::slug($data['name']);
        if (Category::where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(5));
        }
        Category::create($data + ['slug' => $slug]);

        return back()->with('success', 'Catégorie ajoutée.');
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        $category->update($request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('categories', 'name')->ignore($category)], 'description' => 'nullable|string|max:255']));

        return back()->with('success', 'Catégorie mise à jour.');
    }

    public function deleteCategory(Request $request, Category $category): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        if ($category->recipes()->withTrashed()->exists()) {
            return back()->with('error', 'Cette catégorie contient des recettes et doit être conservée.');
        }
        $category->delete();

        return back()->with('success', 'Catégorie supprimée.');
    }

    public function role(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas retirer votre propre accès administrateur.');
        $data = $request->validate(['is_admin' => 'required|boolean']);
        $user->is_admin = (bool) $data['is_admin'];
        $user->save();

        return back()->with('success', 'Rôle mis à jour.');
    }
}
