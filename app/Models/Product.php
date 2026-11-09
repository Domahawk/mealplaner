<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    // Ownership is assigned through the user relationship, never mass-assigned.
    protected $fillable = [
        'name',
        'brand',
        'package_weight_g',
        'calories_per_100g',
        'protein_per_100g',
        'carbohydrates_per_100g',
        'fat_per_100g',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'package_weight_g' => 'decimal:3',
            'calories_per_100g' => 'decimal:2',
            'protein_per_100g' => 'decimal:2',
            'carbohydrates_per_100g' => 'decimal:2',
            'fat_per_100g' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Recipe, $this, RecipeIngredient> */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_product')->using(RecipeIngredient::class)
            ->withPivot(['id', 'user_id', 'quantity_g'])->withTimestamps();
    }

    /** @return HasMany<RecipeIngredient, $this> */
    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }
}
