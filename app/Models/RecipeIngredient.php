<?php

namespace App\Models;

use Database\Factories\RecipeIngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecipeIngredient extends Pivot
{
    /** @use HasFactory<RecipeIngredientFactory> */
    use HasFactory;

    protected $table = 'recipe_product';

    public $incrementing = true;

    protected $fillable = ['product_id', 'quantity_g'];

    protected static function booted(): void
    {
        static::saving(function (RecipeIngredient $ingredient): void {
            Validator::make($ingredient->getAttributes(), [
                'recipe_id' => ['required', 'integer', 'regex:/^\d+$/D', 'min:1'],
                'product_id' => ['required', 'integer', 'regex:/^\d+$/D', 'min:1'],
                'quantity_g' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:9999999.999'],
            ])->validate();

            $owner = Recipe::query()->whereKey($ingredient->recipe_id)->value('user_id');
            if ($owner === null) {
                throw ValidationException::withMessages(['recipe_id' => 'The selected recipe is unavailable.']);
            }
            if (! Product::query()->where('user_id', $owner)->whereKey($ingredient->product_id)->exists()) {
                throw ValidationException::withMessages(['product_id' => 'The selected product is unavailable for this recipe.']);
            }
            // Never trust a supplied pivot owner; both composite FKs enforce this too.
            $ingredient->user_id = $owner;
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['recipe_id' => 'integer', 'product_id' => 'integer', 'user_id' => 'integer', 'quantity_g' => 'decimal:3'];
    }

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
