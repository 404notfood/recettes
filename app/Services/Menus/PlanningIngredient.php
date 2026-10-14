<?php

namespace App\Services\Menus;

use Illuminate\Database\Eloquent\Model;

/**
 * Ingredient value used by the planner; it is never saved as a separate record.
 *
 * @property string $name
 * @property float|null $quantity
 * @property string|null $unit
 */
class PlanningIngredient extends Model
{
    protected $guarded = [];
}
