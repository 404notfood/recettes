<?php

use App\Http\Controllers\CommunityController;
use App\Http\Controllers\MealPlanController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RecipeController::class, 'home'])->name('home');
Route::get('/recettes', [RecipeController::class, 'index'])->name('recipes.index');
Route::get('/proposer', [RecipeController::class, 'create'])->name('recipes.create');
Route::post('/recettes', [RecipeController::class, 'store'])->middleware('throttle:5,1')->name('recipes.store');
Route::inertia('/proposition-envoyee', 'recipes/thanks')->name('recipes.thanks');
Route::get('/recettes/{recipe:slug}', [RecipeController::class, 'show'])->name('recipes.show');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::inertia('/a-propos', 'about')->name('about');
Route::inertia('/confidentialite', 'privacy')->name('privacy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/menus', [MealPlanController::class, 'index'])->name('menus.index');
    Route::post('/menus', [MealPlanController::class, 'store'])->middleware('throttle:5,1')->name('menus.store');
    Route::get('/menus/{mealPlan}', [MealPlanController::class, 'show'])->name('menus.show');
    Route::get('/menus/{mealPlan}/pdf', [MealPlanController::class, 'export'])->middleware('throttle:15,1')->name('menus.pdf');
    Route::get('/menus/{mealPlan}/imprimer', [MealPlanController::class, 'printRecipes'])->middleware('throttle:15,1')->name('menus.print');
    Route::get('/menus/{mealPlan}/recettes/{meal}/{course}', [MealPlanController::class, 'recipe'])->name('menus.recipe');
    Route::post('/menus/{mealPlan}/regenerate', [MealPlanController::class, 'regenerate'])->middleware('throttle:5,1')->name('menus.regenerate');
    Route::post('/menus/{mealPlan}/meals/{meal}/replace', [MealPlanController::class, 'replace'])->middleware('throttle:5,1')->name('menus.replace');
    Route::patch('/menus/{mealPlan}/meals/{meal}/lock', [MealPlanController::class, 'lock'])->name('menus.lock');
    Route::get('/dashboard', [CommunityController::class, 'dashboard'])->name('dashboard');
    Route::get('/mes-favoris', [CommunityController::class, 'favorites'])->name('favorites');
    Route::get('/mes-recettes/{recipe}/modifier', [RecipeController::class, 'edit'])->name('recipes.edit');
    Route::put('/mes-recettes/{recipe}', [RecipeController::class, 'update'])->name('recipes.update');
    Route::delete('/mes-recettes/{recipe}', [RecipeController::class, 'destroy'])->name('recipes.destroy');
    Route::put('/recettes/{recipe:slug}/favori', [RecipeController::class, 'favorite'])->name('recipes.favorite');
    Route::delete('/recettes/{recipe:slug}/favori', [RecipeController::class, 'unfavorite'])->name('recipes.unfavorite');
    Route::post('/recettes/{recipe:slug}/avis', [RecipeController::class, 'review'])->middleware('throttle:10,1')->name('recipes.review');
    Route::put('/recettes/{recipe:slug}/avis/{review}/reponse', [RecipeController::class, 'reply'])->name('recipes.reply');
    Route::get('/administration', [CommunityController::class, 'admin'])->name('admin');
    Route::put('/administration/recettes/{recipe}', [CommunityController::class, 'moderate'])->name('admin.moderate');
    Route::post('/administration/categories', [CommunityController::class, 'category'])->name('admin.categories.store');
    Route::put('/administration/categories/{category}', [CommunityController::class, 'updateCategory'])->name('admin.categories.update');
    Route::delete('/administration/categories/{category}', [CommunityController::class, 'deleteCategory'])->name('admin.categories.destroy');
    Route::put('/administration/membres/{user}', [CommunityController::class, 'role'])->name('admin.role');
});

require __DIR__.'/settings.php';
