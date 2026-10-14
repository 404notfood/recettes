<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_catalogue_is_complete_and_does_not_create_an_admin(): void
    {
        $this->app['env'] = 'production';
        config(['recipes.editor_email' => 'redaction@recettes.cuistot-club.fr']);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(110, Recipe::where('status', 'published')->count());
        $this->assertSame(10, Category::count());
        $this->assertSame(1, User::count());
        $this->assertSame(0, User::where('is_admin', true)->count());
        $this->assertDatabaseMissing('users', ['email' => 'admin@idee.test']);
        foreach (Category::withCount('recipes')->get() as $category) {
            $this->assertGreaterThanOrEqual(10, $category->recipes_count);
        }
        foreach (Recipe::all() as $recipe) {
            $this->assertFileExists(public_path(ltrim($recipe->image, '/')));
            if (str_starts_with($recipe->image, '/images/collection/')) {
                $credit = config('recipe-photo-credits.'.$recipe->slug);
                $this->assertSame($recipe->image, $credit['image']);
                $this->assertNotEmpty($credit['source']);
                $this->assertNotEmpty($credit['license']);
            }
        }
    }

    public function test_reseeding_preserves_edits_and_archives_in_both_recipe_sources(): void
    {
        $this->seed(DatabaseSeeder::class);
        $original = Recipe::where('slug', 'bowl-croquant-patate-douce-avocat')->firstOrFail();
        $collection = Recipe::where('slug', 'onigiri-thon-mayonnaise')->firstOrFail();
        $original->update(['title' => 'Mon titre corrigé', 'status' => 'draft']);
        $original->delete();
        $collection->update(['title' => 'Mes onigiri corrigés']);
        $collection->delete();
        $editor = $original->user_id;

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(110, Recipe::withTrashed()->count());
        $this->assertSame(108, Recipe::count());
        $this->assertSame('Mon titre corrigé', $original->fresh()->title);
        $this->assertSame('draft', $original->fresh()->status);
        $this->assertSame('Mes onigiri corrigés', $collection->fresh()->title);
        $this->assertSame($editor, $collection->fresh()->user_id);
        $this->assertSame(1, User::count());
    }

    public function test_console_creates_a_verified_admin_with_a_hashed_password(): void
    {
        $this->artisan('app:create-admin', ['email' => 'ADMIN@example.fr', '--name' => 'Camille'])
            ->expectsQuestion('Mot de passe (12 caractères minimum)', 'Un-secret-pour-les-tests-42')
            ->expectsQuestion('Confirmer le mot de passe', 'Un-secret-pour-les-tests-42')
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.fr')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('Camille', $user->name);
        $this->assertTrue(Hash::check('Un-secret-pour-les-tests-42', $user->password));
    }

    public function test_console_does_not_promote_or_reset_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'membre@example.fr', 'is_admin' => false]);
        $password = $user->password;

        $this->artisan('app:create-admin', ['email' => $user->email])->assertFailed();

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertSame($password, $user->fresh()->password);
    }

    public function test_invalid_password_does_not_create_an_admin(): void
    {
        $this->artisan('app:create-admin', ['email' => 'admin@example.fr'])
            ->expectsQuestion('Mot de passe (12 caractères minimum)', 'court')
            ->expectsQuestion('Confirmer le mot de passe', 'court')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }
}
