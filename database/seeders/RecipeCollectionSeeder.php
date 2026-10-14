<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RecipeCollectionSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{title: string, slug: string, category: string, image: string, description: string, prep_time: int, cook_time: int, servings: int, difficulty: string, tags: list<string>, ingredients: list<array{quantity: float|null, unit: string, name: string}>, steps: list<string>}> $recipes */
        $recipes = json_decode(File::get(database_path('seeders/data/recipe-collection.json')), true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($recipes): void {
            foreach ([
                ['Entrées & apéro', 'entrees', 'utensils', 'De quoi ouvrir le repas et partager les premières bouchées.'],
                ['Végétal & gourmand', 'vegetal', 'leaf', 'Les légumes ont le premier rôle.'],
                ['Brunch & petit-déj', 'brunch', 'coffee', 'Les matins qui prennent leur temps.'],
                ['Fait maison', 'maison', 'wheat', 'Le plaisir de faire soi-même.'],
                ['Mijotés & terroir', 'terroir', 'utensils', 'Les grandes tablées et les recettes de nos régions.'],
                ['Potages & veloutés', 'potages', 'utensils', 'Des bols réconfortants, avec une touche originale.'],
                ['Cuisine japonaise', 'japon', 'utensils', 'Des recettes japonaises à cuisiner chez soi.'],
                ['Salades composées', 'salades', 'leaf', 'Du croquant, de la fraîcheur et de belles assiettes.'],
                ['Plats du quotidien', 'plats', 'utensils', 'Du bon, du simple, à partager.'],
                ['Petites douceurs', 'douceurs', 'cake', 'Une bonne raison de garder une place.'],
            ] as [$name, $slug, $icon, $description]) {
                Category::firstOrCreate(['slug' => $slug], compact('name', 'icon', 'description'));
            }

            $editor = User::firstOrCreate(['email' => config('recipes.editor_email')], [
                'name' => config('recipes.editor_name'),
                'password' => Str::password(32),
                'email_verified_at' => now(),
            ]);
            $categoryIds = Category::pluck('id', 'slug');

            $publishedAt = now();
            foreach ($recipes as $recipe) {
                if (! is_file(public_path(ltrim($recipe['image'], '/')))) {
                    throw new \RuntimeException('Photo du catalogue manquante : '.$recipe['slug']);
                }
                // Preserve existing recipes, including ones archived by an administrator.
                Recipe::withTrashed()->firstOrCreate(['slug' => $recipe['slug']], [
                    'user_id' => $editor->id,
                    'author_name' => config('recipes.editor_name'),
                    'category_id' => $categoryIds[$recipe['category']],
                    'title' => $recipe['title'],
                    'description' => $recipe['description'],
                    'image' => $recipe['image'],
                    'prep_time' => $recipe['prep_time'],
                    'cook_time' => $recipe['cook_time'],
                    'servings' => $recipe['servings'],
                    'difficulty' => $recipe['difficulty'],
                    'tags' => $recipe['tags'],
                    'ingredients' => $recipe['ingredients'],
                    'steps' => $recipe['steps'],
                    'status' => 'published',
                    'featured' => false,
                    'published_at' => $publishedAt,
                ]);
            }
        });
    }
}
