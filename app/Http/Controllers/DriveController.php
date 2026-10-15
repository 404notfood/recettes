<?php

namespace App\Http\Controllers;

use App\Models\MealPlan;
use App\Services\Menus\ShoppingList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriveController extends Controller
{
    public function show(Request $request, MealPlan $mealPlan, ShoppingList $shopping): Response
    {
        abort_unless($mealPlan->user_id === $request->user()?->id, 403);
        $mealPlan->load('meals');
        $expected = $mealPlan->days * count($mealPlan->criteria['meals'] ?? []);
        abort_unless($mealPlan->status === 'ready' && $expected > 0 && $mealPlan->meals->count() === $expected, 409, 'Terminez votre planning avant de préparer les courses.');

        return Inertia::render('menus/drives', [
            'plan' => $mealPlan->only(['id', 'title', 'people']),
            'items' => array_map(fn (array $item): array => [
                ...$item,
                'id' => hash('sha256', $item['name'].'|'.$item['unit'].'|'.($item['quantity'] === null ? 'unknown' : 'quantity')),
            ], $shopping->forPlan($mealPlan)),
        ]);
    }
}
