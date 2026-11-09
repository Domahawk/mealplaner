<?php

namespace App\Models;

use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'servings'];

    protected static function booted(): void
    {
        static::saving(function (Recipe $recipe): void {
            Validator::make($recipe->getAttributes(), [
                'name' => ['required', 'string', 'max:255', 'regex:/\S/u', 'not_regex:/\x00/'],
                'description' => ['nullable', 'string', 'not_regex:/\x00/'],
                'servings' => ['required', 'integer', 'regex:/^\d+$/D', 'min:1', 'max:2147483647'],
            ])->validate();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'servings' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<RecipeIngredient, $this> */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    /** @return BelongsToMany<Product, $this, RecipeIngredient> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'recipe_product')->using(RecipeIngredient::class)
            ->withPivot(['id', 'user_id', 'quantity_g'])->withTimestamps();
    }
}
