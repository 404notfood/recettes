<?php

namespace Tests\Feature;

use App\Models\MealPlan;
use App\Models\User;
use App\Services\Menus\MenuPdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

class MenuPdfExportTest extends TestCase
{
    use RefreshDatabase;

    private function plan(User $owner): MealPlan
    {
        $plan = MealPlan::create([
            'user_id' => $owner->id, 'title' => 'Menus du foyer', 'start_date' => '2026-10-19',
            'days' => 1, 'people' => 4, 'status' => 'ready',
            'criteria' => ['meals' => ['lunch'], 'vegetarian' => false],
        ]);
        $snapshot = [];
        foreach (['starter' => 'Carottes râpées au citron', 'main' => 'Poulet, riz et légumes', 'dessert' => 'Compote de pommes'] as $course => $title) {
            $snapshot[$course] = [
                'title' => $title, 'servings' => 2, 'accompaniment' => $course === 'main' ? 'Riz complet et légumes de saison rôtis au four' : null,
                'ingredients' => [['name' => 'Pomme', 'quantity' => 250, 'unit' => 'g']],
            ];
        }
        $plan->meals()->create(['date' => '2026-10-19', 'meal_type' => 'lunch', 'quick' => true, 'total_minutes' => 25, 'snapshot' => $snapshot]);

        return $plan;
    }

    public function test_owner_can_download_all_three_pdf_variants(): void
    {
        $owner = User::factory()->create();
        $plan = $this->plan($owner);
        $this->actingAs($owner);
        foreach (['full' => 'menus-et-courses', 'menus' => 'menus', 'shopping' => 'liste-de-courses'] as $document => $prefix) {
            $response = $this->get(route('menus.pdf', ['mealPlan' => $plan->id, 'document' => $document]));
            $response->assertOk()->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', 'attachment; filename="'.$prefix.'-2026-10-19.pdf"');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertGreaterThan(1000, strlen($response->getContent()));
            $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        }
    }

    public function test_planning_can_be_printed_as_html_without_using_the_pdf_engine(): void
    {
        $owner = User::factory()->create();
        $plan = $this->plan($owner);
        $this->actingAs($owner)->get(route('menus.print-plan', $plan))->assertOk()
            ->assertSee('Imprimer ou enregistrer en PDF')->assertSee('Carottes râpées au citron')
            ->assertSee('La liste de courses')->assertSee('1,5 kg');
        $this->actingAs(User::factory()->create())->get(route('menus.print-plan', $plan))->assertForbidden();
    }

    public function test_default_download_includes_menus_and_courses(): void
    {
        $owner = User::factory()->create();
        $response = $this->actingAs($owner)->get(route('menus.pdf', $this->plan($owner)));
        $response->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="menus-et-courses-2026-10-19.pdf"');
    }

    public function test_pdf_download_requires_authentication_and_plan_ownership(): void
    {
        $owner = User::factory()->create();
        $plan = $this->plan($owner);
        $this->get(route('menus.pdf', $plan))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('menus.pdf', $plan))->assertForbidden();
    }

    public function test_unverified_user_cannot_download_their_pdf(): void
    {
        $owner = User::factory()->unverified()->create();
        $this->actingAs($owner)->get(route('menus.pdf', $this->plan($owner)))->assertRedirect(route('verification.notice'));
    }

    public function test_incomplete_or_generating_plan_cannot_be_exported(): void
    {
        $owner = User::factory()->create();
        $plan = $this->plan($owner);
        $this->actingAs($owner);
        $plan->update(['status' => 'generating']);
        $this->get(route('menus.pdf', $plan))->assertStatus(409);
        $plan->update(['status' => 'ready', 'days' => 7]);
        $this->get(route('menus.pdf', $plan))->assertStatus(409);
    }

    public function test_invalid_document_type_is_rejected(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get(route('menus.pdf', ['mealPlan' => $this->plan($owner)->id, 'document' => 'other']))->assertSessionHasErrors('document');
    }

    public function test_weekly_pages_stay_landscape_and_courses_stay_portrait(): void
    {
        $plan = $this->plan(User::factory()->create());
        $snapshot = $plan->meals()->first()->snapshot;
        $plan->meals()->delete();
        $exporter = app(MenuPdfExporter::class);
        foreach ([1, 7, 14] as $days) {
            $plan->update(['days' => $days]);
            $plan->meals()->delete();
            for ($day = 0; $day < $days; $day++) {
                foreach (['lunch', 'dinner'] as $type) {
                    $plan->meals()->create([
                        'date' => $plan->start_date->addDays($day)->format('Y-m-d'),
                        'meal_type' => $type, 'total_minutes' => 30, 'snapshot' => $snapshot,
                    ]);
                }
            }
            $plan->unsetRelation('meals');
            foreach (['menus', 'shopping', 'full'] as $variant) {
                $reader = new Fpdi;
                $count = $reader->setSourceFile(StreamReader::createByString($exporter->render($plan, $variant)));
                $weeks = (int) ceil($days / 7);
                $this->assertSame($variant === 'menus' ? $weeks : ($variant === 'shopping' ? 1 : $weeks + 1), $count);
                for ($page = 1; $page <= $count; $page++) {
                    $size = $reader->getTemplateSize($reader->importPage($page));
                    $landscape = $variant !== 'shopping' && $page <= $weeks;
                    $this->assertSame($landscape ? 'L' : 'P', $size['orientation']);
                }
            }
        }
    }

    public function test_long_recipe_titles_fit_on_one_weekly_page(): void
    {
        $plan = $this->plan(User::factory()->create());
        $snapshot = $plan->meals()->first()->snapshot;
        foreach ($snapshot as &$recipe) {
            $recipe['title'] = 'Légumes de saison rôtis au four, accompagnés de leur sauce au yaourt et aux herbes fraîches, avec une touche de citron';
        }
        unset($recipe);
        $plan->meals()->delete();
        $plan->update(['days' => 7]);
        for ($day = 0; $day < 7; $day++) {
            foreach (['lunch', 'dinner'] as $type) {
                $plan->meals()->create(['date' => $plan->start_date->addDays($day)->format('Y-m-d'), 'meal_type' => $type, 'total_minutes' => 40, 'snapshot' => $snapshot]);
            }
        }
        $reader = new Fpdi;
        $this->assertSame(1, $reader->setSourceFile(StreamReader::createByString(app(MenuPdfExporter::class)->render($plan, 'menus'))));
    }
}
