<?php

use App\Http\Controllers\CommunityController;
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
