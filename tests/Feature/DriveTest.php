<?php

namespace Tests\Feature;

use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DriveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function plan(User $owner): MealPlan
    {
        $plan = MealPlan::create([
            'user_id' => $owner->id, 'title' => 'Nos courses', 'start_date' => '2026-10-19',
            'days' => 1, 'people' => 4, 'criteria' => ['meals' => ['lunch']], 'status' => 'ready',
        ]);
        $plan->meals()->create([
            'date' => '2026-10-19', 'meal_type' => 'lunch', 'quick' => false, 'locked' => false, 'total_minutes' => 20,
            'snapshot' => ['main' => ['title' => 'Carottes', 'servings' => 2, 'ingredients' => [
                ['name' => 'Carotte', 'quantity' => 300, 'unit' => 'g'],
                ['name' => 'Sel', 'quantity' => null, 'unit' => ''],
            ]]],
        ]);

        return $plan;
    }

    public function test_drive_page_is_private_and_requires_a_verified_account(): void
    {
        $owner = User::factory()->create();
        $plan = $this->plan($owner);
        $this->get(route('menus.drives', $plan))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get(route('menus.drives', $plan))->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->create())->get(route('menus.drives', $plan))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_owner_gets_scaled_courses_and_unknown_quantities_without_external_requests(): void
    {
        $owner = User::factory()->create();
        $plan = $this->plan($owner);
        $this->actingAs($owner)->get(route('menus.drives', $plan))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('menus/drives')
            ->where('plan.title', 'Nos courses')
            ->has('items', 2)
            ->where('items.0.name', 'Carotte')
            ->where('items.0.quantity', 600)
            ->where('items.1.quantity', null)
            ->where('items.0.id', fn ($id) => is_string($id) && strlen($id) === 64)
            ->missing('plan.criteria')
            ->missing('plan.user_id'));
        Http::assertNothingSent();
    }

    public function test_generation_and_incomplete_plans_cannot_prepare_drives(): void
    {
        $owner = User::factory()->create();
        $plan = $this->plan($owner);
        $this->actingAs($owner);
        foreach (['queued', 'generating', 'failed'] as $status) {
            $plan->update(['status' => $status]);
            $this->get(route('menus.drives', $plan))->assertStatus(409);
        }
        $plan->update(['status' => 'ready', 'days' => 7]);
        $this->get(route('menus.drives', $plan))->assertStatus(409);
        Http::assertNothingSent();
    }
}
