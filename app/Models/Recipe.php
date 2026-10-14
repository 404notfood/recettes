<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property string $author_name
 * @property string|null $image
 * @property string $status
 * @property string $difficulty
 * @property int $prep_time
 * @property int $cook_time
 * @property int $servings
 * @property bool $featured
 * @property array<int, array{quantity: float|null, unit: string, name: string}> $ingredients
 * @property array<int, string> $steps
 * @property array<int, string>|null $tags
 * @property array<string, mixed>|null $proposed_changes
 * @property string|null $moderation_note
 * @property Carbon|null $published_at
 * @property Carbon $updated_at
 * @property Carbon $created_at
 * @property Category $category
 */
class Recipe extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'category_id', 'title', 'slug', 'description', 'author_name', 'guest_email', 'image', 'prep_time', 'cook_time', 'servings', 'difficulty', 'ingredients', 'steps', 'tags', 'status', 'featured', 'published_at', 'proposed_changes', 'moderation_note'];

    protected $hidden = ['guest_email', 'proposed_changes', 'moderation_note'];

    protected function casts(): array
    {
        return ['ingredients' => 'array', 'steps' => 'array', 'tags' => 'array', 'proposed_changes' => 'array', 'featured' => 'boolean', 'prep_time' => 'integer', 'cook_time' => 'integer', 'servings' => 'integer', 'published_at' => 'datetime'];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /** @return array<string, mixed> */
    public function publicData(bool $detail = false): array
    {
        $data = [
            'id' => $this->id, 'slug' => $this->slug, 'title' => $this->title, 'description' => $this->description,
            'author_name' => $this->author_name, 'image_url' => $this->image ? ($this->image[0] === '/' ? $this->image : asset('storage/'.$this->image)) : '/images/recipe-placeholder.svg',
            'prep_time' => $this->prep_time, 'cook_time' => $this->cook_time, 'total_time' => $this->prep_time + $this->cook_time,
            'servings' => $this->servings, 'difficulty' => $this->difficulty, 'category' => $this->category->only(['id', 'name', 'slug', 'icon']),
            'tags' => $this->tags ?? [], 'featured' => $this->featured,
            'rating' => $this->getAttribute('reviews_avg_rating') ? round((float) $this->getAttribute('reviews_avg_rating'), 1) : null,
            'reviews_count' => (int) ($this->getAttribute('reviews_count') ?? 0),
        ];
        if ($detail) {
            $data += ['ingredients' => $this->ingredients, 'steps' => $this->steps, 'published_at' => $this->published_at?->toIso8601String()];
            $credit = config('recipe-photo-credits.'.$this->slug);
            if (is_array($credit) && ($credit['image'] ?? null) === $this->image) {
                $data['image_credit'] = $credit;
            }
        }

        return $data;
    }
}
