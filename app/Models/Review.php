<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id @property int $recipe_id @property int $user_id @property User $user @property string $content @property string|null $reply @property int|null $rating @property \Illuminate\Support\Carbon $created_at */
class Review extends Model
{
    protected $fillable = ['user_id', 'recipe_id', 'content', 'rating', 'reply'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
