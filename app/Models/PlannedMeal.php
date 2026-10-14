<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meal_plan_id
 * @property Carbon $date
 * @property string $meal_type
 * @property bool $locked
 * @property bool $quick
 * @property int $total_minutes
 * @property int|null $starter_recipe_id
 * @property int|null $main_recipe_id
 * @property int|null $dessert_recipe_id
 * @property array<string, array<string, mixed>> $snapshot
 */
class PlannedMeal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'date' => 'date:Y-m-d', 'quick' => 'boolean', 'locked' => 'boolean'];
    }
}
