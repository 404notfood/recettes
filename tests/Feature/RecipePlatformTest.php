<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RecipePlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function member(bool $admin = false): User
    {
        return User::factory()->create(['is_admin' => $admin]);
    }

    private function payload(): array
    {
        $category = Category::firstOrCreate(['slug' => 'plats'], ['name' => 'Plats', 'icon' => 'utensils']);

        return ['title' => 'Une recette de tomates', 'description' => 'Une recette très simple à préparer pour toute la famille.', 'category_id' => $category->id, 'prep_time' => 10, 'cook_time' => 15, 'servings' => 4, 'difficulty' => 'facile', 'ingredients' => [['quantity' => 250, 'unit' => 'g', 'name' => 'tomates']], 'steps' => ['Coupez les tomates en morceaux et faites-les cuire doucement.'], 'tags' => ['vegetarien'], 'author_name' => 'Camille', 'guest_email' => 'camille@example.test', 'intent' => 'submit', 'website' => ''];
    }

    private function recipe(string $status = 'published', ?User $owner = null): Recipe
    {
        $data = $this->payload();
        unset($data['intent'], $data['website']);

        return Recipe::create($data + ['slug' => 'recette-'.uniqid(), 'status' => $status, 'user_id' => $owner?->id, 'published_at' => $status === 'published' ? now() : null]);
    }

    public function test_guests_can_browse_published_recipes_but_never_pending_or_drafts(): void
    {
        $published = $this->recipe();
        $pending = $this->recipe('pending');
        $draft = $this->recipe('draft');
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('welcome')->has('recipes', 1));
        $this->get('/recettes')->assertOk()->assertInertia(fn (Assert $page) => $page->where('recipes.total', 1));
        $this->get('/recettes/'.$published->slug)->assertOk()->assertInertia(fn (Assert $page) => $page->missing('recipe.guest_email')->missing('recipe.proposed_changes'));
        $this->get('/recettes/'.$pending->slug)->assertNotFound();
        $this->get('/recettes/'.$draft->slug)->assertNotFound();
    }

    public function test_guest_submission_is_pending_and_does_not_create_a_user(): void
    {
        $this->post('/recettes', $this->payload() + ['status' => 'published', 'featured' => true, 'user_id' => 999])->assertRedirect('/proposition-envoyee');
        $this->assertDatabaseHas('recipes', ['status' => 'pending', 'author_name' => 'Camille', 'user_id' => null, 'featured' => false]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_guest_cannot_create_a_private_draft(): void
    {
        $this->post('/recettes', array_replace($this->payload(), ['intent' => 'draft']))->assertRedirect('/proposition-envoyee');
        $this->assertDatabaseHas('recipes', ['status' => 'pending']);
    }

    public function test_invalid_proposals_and_honeypot_are_rejected(): void
    {
        $data = $this->payload();
        $this->postJson('/recettes', array_replace($data, ['website' => 'spam.example']))->assertUnprocessable()->assertJsonValidationErrors('website');
        $this->postJson('/recettes', array_replace($data, ['steps' => [], 'category_id' => 999]))->assertUnprocessable()->assertJsonValidationErrors(['steps', 'category_id']);
        $this->assertDatabaseCount('recipes', 0);
    }

    public function test_image_is_converted_and_stored_as_webp(): void
    {
        Storage::fake('public');
        $this->post('/recettes', $this->payload() + ['image' => UploadedFile::fake()->image('photo.jpg', 1800, 1200)])->assertRedirect();
        $recipe = Recipe::firstOrFail();
        $this->assertStringEndsWith('.webp', $recipe->image);
        Storage::disk('public')->assertExists($recipe->image);
        $size = getimagesize(Storage::disk('public')->path($recipe->image));
        $this->assertSame(1600, $size[0]);
    }

    public function test_members_have_private_drafts_and_cannot_edit_other_members_recipes(): void
    {
        $owner = $this->member();
        $other = $this->member();
        $recipe = $this->recipe('draft', $owner);
        $this->actingAs($other)->get('/mes-recettes/'.$recipe->id.'/modifier')->assertForbidden();
        $this->actingAs($other)->put('/mes-recettes/'.$recipe->id, $this->payload())->assertForbidden();
        $this->actingAs($other)->delete('/mes-recettes/'.$recipe->id)->assertForbidden();
        $this->actingAs($owner)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->has('recipes', 1));
    }

    public function test_authenticated_author_name_cannot_be_spoofed(): void
    {
        $member = $this->member();
        $this->actingAs($member)->post('/recettes', array_replace($this->payload(), ['author_name' => 'Autre personne', 'intent' => 'draft']))->assertRedirect('/dashboard');
        $this->assertDatabaseHas('recipes', ['user_id' => $member->id, 'author_name' => $member->name, 'status' => 'draft', 'guest_email' => null]);
    }

    public function test_only_admin_can_moderate_or_access_administration(): void
    {
        $recipe = $this->recipe('pending');
        $this->actingAs($this->member())->get('/administration')->assertForbidden();
        $this->put('/administration/recettes/'.$recipe->id, ['action' => 'publish'])->assertForbidden();
        $this->actingAs($this->member(true))->put('/administration/recettes/'.$recipe->id, ['action' => 'publish'])->assertRedirect();
        $this->assertSame('published', $recipe->fresh()->status);
        $this->assertNotNull($recipe->fresh()->published_at);
    }

    public function test_published_revision_is_private_until_approved(): void
    {
        $owner = $this->member();
        $recipe = $this->recipe('published', $owner);
        $oldTitle = $recipe->title;
        $this->actingAs($owner)->put('/mes-recettes/'.$recipe->id, array_replace($this->payload(), ['title' => 'La nouvelle version de ma recette']))->assertRedirect();
        $this->assertSame($oldTitle, $recipe->fresh()->title);
        $this->assertSame('La nouvelle version de ma recette', $recipe->fresh()->proposed_changes['title']);
        $this->get('/recettes/'.$recipe->slug)->assertInertia(fn (Assert $page) => $page->where('recipe.title', $oldTitle)->missing('recipe.proposed_changes'));
        $this->actingAs($this->member(true))->put('/administration/recettes/'.$recipe->id, ['action' => 'publish'])->assertRedirect();
        $this->assertSame('La nouvelle version de ma recette', $recipe->fresh()->title);
        $this->assertNull($recipe->fresh()->proposed_changes);
    }

    public function test_requested_corrections_preserve_the_proposed_revision(): void
    {
        $recipe = $this->recipe('published', $this->member());
        $recipe->update(['proposed_changes' => ['title' => 'Titre révisé']]);
        $this->actingAs($this->member(true))->put('/administration/recettes/'.$recipe->id, ['action' => 'changes', 'note' => 'Merci de préciser la cuisson.'])->assertRedirect();
        $this->assertSame('Titre révisé', $recipe->fresh()->proposed_changes['title']);
        $this->get('/administration')->assertInertia(fn (Assert $page) => $page->has('pending', 0));
    }

    public function test_favorites_are_private_and_idempotent(): void
    {
        $recipe = $this->recipe();
        $owner = $this->member();
        $this->actingAs($owner)->put('/recettes/'.$recipe->slug.'/favori')->assertRedirect();
        $this->put('/recettes/'.$recipe->slug.'/favori')->assertRedirect();
        $this->assertDatabaseCount('favorites', 1);
        $this->get('/mes-favoris')->assertInertia(fn (Assert $page) => $page->has('recipes', 1));
        $this->actingAs($this->member())->get('/mes-favoris')->assertInertia(fn (Assert $page) => $page->has('recipes', 0));
        $this->actingAs($owner)->delete('/recettes/'.$recipe->slug.'/favori')->assertRedirect();
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_reviews_update_instead_of_duplicating_and_replies_require_ownership(): void
    {
        $owner = $this->member();
        $recipe = $this->recipe('published', $owner);
        $reviewer = $this->member();
        $this->actingAs($reviewer)->post('/recettes/'.$recipe->slug.'/avis', ['content' => 'Délicieuse recette !', 'rating' => 5])->assertRedirect();
        $this->post('/recettes/'.$recipe->slug.'/avis', ['content' => 'Encore meilleure la seconde fois.', 'rating' => 4])->assertRedirect();
        $this->assertDatabaseCount('reviews', 1);
        $review = $recipe->reviews()->firstOrFail();
        $this->put('/recettes/'.$recipe->slug.'/avis/'.$review->id.'/reponse', ['reply' => 'Merci beaucoup'])->assertForbidden();
        $this->actingAs($owner)->put('/recettes/'.$recipe->slug.'/avis/'.$review->id.'/reponse', ['reply' => 'Merci beaucoup'])->assertRedirect();
        $this->assertDatabaseHas('reviews', ['rating' => 4, 'reply' => 'Merci beaucoup']);
    }

    public function test_authors_cannot_rate_their_own_recipe(): void
    {
        $owner = $this->member();
        $recipe = $this->recipe('published', $owner);
        $this->actingAs($owner)->postJson('/recettes/'.$recipe->slug.'/avis', ['content' => 'Ma recette est super.', 'rating' => 5])->assertUnprocessable();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_search_and_filters_work_with_postgresql(): void
    {
        $recipe = $this->recipe();
        $this->get('/recettes?q=tomates&time=30&tag=vegetarien&sort=rating')->assertOk()->assertInertia(fn (Assert $page) => $page->where('recipes.total', 1));
        $this->get('/recettes?time=15')->assertInertia(fn (Assert $page) => $page->where('recipes.total', 0));
        $this->get('/recettes?category='.$recipe->category->slug)->assertInertia(fn (Assert $page) => $page->where('recipes.total', 1));
    }

    public function test_archive_keeps_data_and_removes_public_access_and_sitemap_entry(): void
    {
        $owner = $this->member();
        $recipe = $this->recipe('published', $owner);
        $this->actingAs($owner)->delete('/mes-recettes/'.$recipe->id)->assertRedirect();
        $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
        $this->get('/recettes/'.$recipe->slug)->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee($recipe->slug);
    }

    public function test_used_categories_and_self_demotion_are_protected(): void
    {
        $recipe = $this->recipe();
        $admin = $this->member(true);
        $this->actingAs($admin)->delete('/administration/categories/'.$recipe->category_id)->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $recipe->category_id]);
        $this->putJson('/administration/membres/'.$admin->id, ['is_admin' => false])->assertUnprocessable();
        $this->assertTrue($admin->fresh()->is_admin);
    }
}
