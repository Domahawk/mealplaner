<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Food product fixture',
            'brand' => null,
            'package_weight_g' => null,
            'calories_per_100g' => '122.50',
            'protein_per_100g' => '7.25',
            'carbohydrates_per_100g' => '20.00',
            'fat_per_100g' => '1.50',
        ];
    }
}
