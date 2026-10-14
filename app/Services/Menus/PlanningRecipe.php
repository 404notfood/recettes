<?php

namespace App\Services\Menus;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A normalized view of Cuistot Club recipes, preserving the public recipe format.
 *
 * @property int|null $id
 * @property string|null $slug
 * @property string $title
 * @property string $description
 * @property list<string> $steps
 * @property string|null $course_type
 * @property int $servings
 * @property int $total_minutes
 * @property int $difficulty
 * @property bool|null $vegetarian
 * @property string|null $main_ingredient
 * @property bool $is_ai_generated
 * @property bool $planning_estimated
 * @property list<string>|null $food_groups
 * @property string|null $protein_source
 * @property string|null $dessert_profile
 * @property Collection<int,PlanningIngredient> $ingredients
 */
class PlanningRecipe extends Model
{
    protected $guarded = [];

    public ?Recipe $record = null;

    public static function fromRecord(Recipe $recipe): self
    {
        $metadata = app(CatalogueMetadata::class)->forRecipe($recipe);
        $item = new self([
            ...$recipe->only(['id', 'title', 'slug', 'description', 'servings', 'steps', 'is_ai_generated', 'planning_estimated']),
            ...$metadata, 'total_minutes' => $recipe->total_minutes ?? $recipe->prep_time + $recipe->cook_time,
            'difficulty' => ['facile' => 1, 'moyen' => 2, 'avance' => 3][$recipe->difficulty],
        ]);
        $item->record = $recipe;
        $item->setRelation('ingredients', collect($recipe->ingredients)->map(fn ($data) => new PlanningIngredient($data)));

        return $item;
    }

    public function saveMetadata(): void
    {
        $this->record?->forceFill($this->only(CatalogueMetadata::FIELDS))->save();
    }

    public function saveDraft(int $userId): void
    {
        [$categorySlug, $categoryName] = match ($this->course_type) {
            'starter' => ['entrees', 'Entrées & apéro'], 'main' => ['plats', 'Plats du quotidien'], 'dessert' => ['douceurs', 'Petites douceurs'],
            default => throw new PlanningException('Le rôle de cette recette est inconnu.'),
        };
        $category = Category::firstOrCreate(['slug' => $categorySlug], [
            'name' => $categoryName,
            'icon' => 'utensils',
        ]);
        $record = new Recipe;
        $record->forceFill([
            ...$this->only(CatalogueMetadata::FIELDS),
            'user_id' => $userId, 'category_id' => $category->id,
            'title' => $this->title, 'slug' => Str::slug($this->title).'-'.Str::lower(Str::random(8)),
            'description' => $this->description, 'steps' => $this->steps,
            'author_name' => User::findOrFail($userId)->name, 'status' => 'pending',
            'servings' => $this->servings, 'prep_time' => $this->total_minutes, 'cook_time' => 0,
            'difficulty' => [1 => 'facile', 2 => 'moyen', 3 => 'avance'][$this->difficulty],
            'tags' => $this->vegetarian ? ['vegetarien'] : [],
            'ingredients' => $this->ingredients->map(fn ($item) => $item->only(['name', 'quantity', 'unit']))->all(),
            'is_ai_generated' => true, 'planning_estimated' => true,
        ])->save();
        $this->record = $record;
        $this->id = $record->id;
        $this->slug = $record->slug;
    }
}
