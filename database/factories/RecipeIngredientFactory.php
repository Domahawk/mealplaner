<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecipeIngredient> */
class RecipeIngredientFactory extends Factory
{
    protected $model = RecipeIngredient::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'product_id' => function (array $attributes): int {
                $recipe = Recipe::query()->whereKey($attributes['recipe_id'])->firstOrFail();

                return Product::factory()->for($recipe->user()->firstOrFail())->create()->id;
            },
            'quantity_g' => '100.000',
        ];
    }
}
