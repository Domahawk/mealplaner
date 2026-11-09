<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use App\Policies\RecipePolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        config(['hashing.bcrypt.rounds' => 4]);
    }

    public function test_recipe_factory_owner_optional_description_and_schema_do_not_store_nutrition_totals(): void
    {
        $recipe = Recipe::factory()->create()->fresh();
        $this->assertInstanceOf(User::class, $recipe->user);
        $this->assertTrue($recipe->user->recipes->contains($recipe));
        $this->assertNull($recipe->description);
        $this->assertSame(4, $recipe->servings);
        $this->assertEqualsCanonicalizing(['id', 'user_id', 'name', 'description', 'servings', 'created_at', 'updated_at'], Schema::getColumnListing('recipes'));
        $recipe->update(['description' => "A recipe description.\nWith instructions.", 'servings' => '2']);
        $this->assertSame(2, $recipe->fresh()->servings);
        $this->assertSame("A recipe description.\nWith instructions.", $recipe->fresh()->description);
    }

    public function test_ingredient_factory_creates_valid_parents_with_the_same_owner(): void
    {
        $ingredient = RecipeIngredient::factory()->create()->fresh();
        $this->assertNotNull($ingredient->id);
        $this->assertSame($ingredient->recipe->user_id, $ingredient->product->user_id);
        $this->assertSame($ingredient->recipe->user_id, $ingredient->user_id);
        $this->assertSame('100.000', $ingredient->quantity_g);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('recipes', 1);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_multiple_products_and_recipes_share_products_with_typed_pivot_quantities(): void
    {
        $owner = User::factory()->create();
        $first = Recipe::factory()->for($owner)->create();
        $second = Recipe::factory()->for($owner)->create();
        $oats = Product::factory()->for($owner)->create(['name' => 'Oats']);
        $milk = Product::factory()->for($owner)->create(['name' => 'Milk']);
        $first->products()->attach($oats, ['quantity_g' => '125.125']);
        $first->products()->attach($milk, ['quantity_g' => '200.000']);
        $oats->recipes()->attach($second, ['quantity_g' => '50.500']);

        $this->assertEqualsCanonicalizing([$oats->id, $milk->id], $first->products->modelKeys());
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $oats->recipes->modelKeys());
        $pivot = $first->products()->findOrFail($oats->id)->pivot;
        $this->assertInstanceOf(RecipeIngredient::class, $pivot);
        $this->assertSame('125.125', $pivot->quantity_g);
        $this->assertSame($owner->id, $pivot->user_id);
        $this->assertNotNull($pivot->created_at);
        $first->products()->updateExistingPivot($oats->id, ['quantity_g' => '150.250']);
        $this->assertSame('150.250', $first->products()->findOrFail($oats->id)->pivot->quantity_g);
    }

    public function test_ingredient_model_relations_creation_updates_and_deletion_use_the_pivot_id(): void
    {
        $recipe = Recipe::factory()->create();
        $product = Product::factory()->for($recipe->user)->create();
        $ingredient = $recipe->ingredients()->create(['product_id' => $product->id, 'quantity_g' => '1.001']);
        $this->assertTrue($ingredient->recipe->is($recipe));
        $this->assertTrue($ingredient->product->is($product));
        $this->assertTrue($product->recipeIngredients->contains($ingredient));
        $ingredient->update(['quantity_g' => '9999999.999']);
        $this->assertSame('9999999.999', $ingredient->fresh()->quantity_g);
        $ingredient->delete();
        $this->assertDatabaseCount('recipe_product', 0);
        $this->assertDatabaseCount('recipes', 1);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_recipe_deletion_cascades_only_its_ingredient_links(): void
    {
        $ingredient = RecipeIngredient::factory()->create();
        $otherRecipe = Recipe::factory()->for($ingredient->recipe->user)->create();
        $otherRecipe->products()->attach($ingredient->product, ['quantity_g' => '5.000']);
        $ingredient->recipe->delete();
        $this->assertDatabaseMissing('recipes', ['id' => $ingredient->recipe_id]);
        $this->assertDatabaseMissing('recipe_product', ['recipe_id' => $ingredient->recipe_id]);
        $this->assertDatabaseHas('products', ['id' => $ingredient->product_id]);
        $this->assertDatabaseHas('recipe_product', ['recipe_id' => $otherRecipe->id]);
    }

    public function test_pivot_ownership_is_derived_and_mass_assignment_cannot_change_recipe_or_owner(): void
    {
        $recipe = Recipe::factory()->create();
        $product = Product::factory()->for($recipe->user)->create();
        $foreign = Recipe::factory()->create();
        $recipe->products()->attach($product, ['quantity_g' => '10.000', 'user_id' => $foreign->user_id]);
        $ingredient = $recipe->ingredients()->sole();
        $ingredient->fill(['user_id' => $foreign->user_id, 'recipe_id' => $foreign->id])->save();
        $this->assertSame($recipe->user_id, $ingredient->fresh()->user_id);
        $this->assertSame($recipe->id, $ingredient->fresh()->recipe_id);
    }

    public function test_missing_ingredient_parents_are_validation_errors(): void
    {
        $recipe = Recipe::factory()->create();
        $product = Product::factory()->for($recipe->user)->create();
        foreach (['recipe_id', 'product_id'] as $field) {
            $ingredient = new RecipeIngredient;
            $ingredient->forceFill(array_replace([
                'recipe_id' => $recipe->id, 'product_id' => $product->id, 'quantity_g' => '10.000',
            ], [$field => 999999]));
            try {
                $ingredient->save();
                $this->fail('A missing parent was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
        $this->assertDatabaseCount('recipe_product', 0);
    }

    public function test_duplicate_products_in_one_recipe_are_rejected(): void
    {
        $ingredient = RecipeIngredient::factory()->create();
        try {
            DB::transaction(fn () => $ingredient->recipe->products()->attach($ingredient->product, ['quantity_g' => '2.000']));
            $this->fail('A product must appear only once per recipe.');
        } catch (QueryException $exception) {
            $this->assertSame(DB::getDriverName() === 'pgsql' ? '23505' : '23000', $exception->errorInfo[0]);
        }
        $this->assertDatabaseCount('recipe_product', 1);
    }

    #[DataProvider('ingredientWrites')]
    public function test_model_writes_reject_foreign_products_and_atomic_batches_roll_back(string $operation): void
    {
        $ingredient = RecipeIngredient::factory()->create();
        $recipe = $ingredient->recipe;
        $foreign = Product::factory()->create();
        try {
            DB::transaction(function () use ($operation, $recipe, $ingredient, $foreign): void {
                match ($operation) {
                    'attach' => $recipe->products()->attach($foreign, ['quantity_g' => '1.000']),
                    'reverse' => $foreign->recipes()->attach($recipe, ['quantity_g' => '1.000']),
                    'sync' => $recipe->products()->sync([$foreign->id => ['quantity_g' => '1.000']]),
                    'pivot update' => $recipe->products()->updateExistingPivot($ingredient->product_id, ['product_id' => $foreign->id]),
                    'ingredient create' => $recipe->ingredients()->create(['product_id' => $foreign->id, 'quantity_g' => '1.000']),
                    'factory' => RecipeIngredient::factory()->for($recipe)->for($foreign)->create(),
                };
            });
            $this->fail('A recipe accepted a foreign product.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product_id', $exception->errors());
        }
        $this->assertDatabaseCount('recipe_product', 1);
        $this->assertDatabaseHas('recipe_product', ['id' => $ingredient->id, 'product_id' => $ingredient->product_id]);
    }

    /** @return array<string, array{string}> */
    public static function ingredientWrites(): array
    {
        return ['attach' => ['attach'], 'reverse' => ['reverse'], 'sync' => ['sync'], 'pivot update' => ['pivot update'], 'ingredient create' => ['ingredient create'], 'factory' => ['factory']];
    }

    #[DataProvider('invalidQuantities')]
    public function test_quantities_are_validated_before_persistence(mixed $quantity): void
    {
        $recipe = Recipe::factory()->create();
        $product = Product::factory()->for($recipe->user)->create();
        try {
            $recipe->products()->attach($product, ['quantity_g' => $quantity]);
            $this->fail('Invalid quantity accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity_g', $exception->errors());
        }
        $this->assertDatabaseCount('recipe_product', 0);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidQuantities(): array
    {
        return ['null' => [null], 'zero' => ['0'], 'negative' => ['-0.001'], 'precision' => ['1.0001'], 'overflow' => ['10000000'], 'NaN' => ['NaN'], 'infinity' => ['Infinity'], 'boolean' => [true], 'array' => [[]]];
    }

    #[DataProvider('invalidRecipes')]
    public function test_recipe_values_are_validated_before_persistence(array $changes, string $field): void
    {
        try {
            Recipe::factory()->create($changes);
            $this->fail('Invalid recipe accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseCount('recipes', 0);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidRecipes(): array
    {
        $cases = ['empty name' => [['name' => ''], 'name'], 'whitespace name' => [['name' => " \t "], 'name'], 'long name' => [['name' => str_repeat('x', 256)], 'name'], 'description type' => [['description' => []], 'description'], 'null byte' => [['description' => "a\0b"], 'description']];
        foreach ([0, -1, null, '1.5', 'invalid', '2147483648', true] as $index => $servings) {
            $cases['servings '.$index] = [['servings' => $servings], 'servings'];
        }

        return $cases;
    }

    public function test_recipe_ownership_is_guarded_and_owner_scoped_queries_hide_foreign_ids(): void
    {
        $owner = User::factory()->create();
        $foreign = Recipe::factory()->create();
        $recipe = $owner->recipes()->create(['name' => 'Owned recipe', 'servings' => 1, 'user_id' => $foreign->user_id]);
        $recipe->fill(['user_id' => $foreign->user_id])->save();
        $this->assertSame($owner->id, $recipe->fresh()->user_id);
        $this->expectException(ModelNotFoundException::class);
        $owner->recipes()->findOrFail($foreign->id);
    }

    public function test_recipe_policy_is_discovered_and_denies_guests_and_foreign_owners(): void
    {
        $recipe = Recipe::factory()->create();
        $foreign = User::factory()->create();
        $this->assertInstanceOf(RecipePolicy::class, Gate::getPolicyFor($recipe));
        foreach (['viewAny', 'create'] as $ability) {
            $this->assertTrue(Gate::forUser($recipe->user)->allows($ability, Recipe::class));
            $this->assertFalse(Gate::allows($ability, Recipe::class));
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($recipe->user)->allows($ability, $recipe));
            $this->assertSame(404, Gate::forUser($foreign)->inspect($ability, $recipe)->status());
            $this->assertFalse(Gate::allows($ability, $recipe));
        }
    }

    public function test_product_api_rejects_referenced_deletion_and_allows_it_after_detaching(): void
    {
        $ingredient = RecipeIngredient::factory()->create();
        $url = '/api/products/'.$ingredient->product_id;
        $this->deleteJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->deleteJson($url)->assertNotFound();
        $this->actingAs($ingredient->recipe->user);
        $this->deleteJson($url)->assertConflict()->assertJsonPath('message', 'This product is used in a recipe. Remove it from recipes before deleting it.');
        $this->assertDatabaseHas('products', ['id' => $ingredient->product_id]);
        $this->assertDatabaseHas('recipe_product', ['id' => $ingredient->id]);
        $this->getJson($url)->assertOk();
        $ingredient->recipe->products()->detach($ingredient->product_id);
        $this->deleteJson($url)->assertNoContent();
        $this->assertDatabaseHas('recipes', ['id' => $ingredient->recipe_id]);
    }
}
