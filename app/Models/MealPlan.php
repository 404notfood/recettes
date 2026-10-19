<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $days
 * @property int $people
 * @property string $title
 * @property string $status
 * @property Carbon $start_date
 * @property array{days:int, meals:list<string>, quick_slots:list<string>, difficulty:int, quick_minutes:int, normal_minutes:int, vegetarian:bool, excluded:list<string>, preferences:string, start_date?:string, seasonal?:bool, variety?:string, diets?:list<string>, allergens?:list<string>} $criteria
 * @property Collection<int, PlannedMeal> $meals
 */
class MealPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['criteria' => 'array', 'start_date' => 'date:Y-m-d', 'days' => 'integer', 'people' => 'integer'];
    }

    /** @return HasMany<PlannedMeal, $this> */
    public function meals(): HasMany
    {
        return $this->hasMany(PlannedMeal::class)->orderBy('date')->orderByRaw("CASE WHEN meal_type = 'lunch' THEN 0 ELSE 1 END");
    }
}
