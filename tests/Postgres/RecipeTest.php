<?php

namespace Tests\Postgres;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecipeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashing.bcrypt.rounds' => 4]);
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidRecipes')]
    public function test_recipe_constraints_reject_invalid_direct_sql_writes(array $changes, string $state): void
    {
        $owner = User::factory()->create();
        $values = array_replace(['user_id' => $owner->id, 'name' => 'Raw recipe fixture', 'servings' => 1], $changes);
        try {
            DB::transaction(fn (): bool => DB::table('recipes')->insert($values));
            $this->fail('Invalid recipe data was accepted by PostgreSQL.');
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->errorInfo[0]);
        }
        $this->assertDatabaseCount('recipes', 0);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidRecipes(): array
    {
        return [
            'null owner' => [['user_id' => null], '23502'],
            'missing owner' => [['user_id' => -1], '23503'],
            'null name' => [['name' => null], '23502'],
            'blank name' => [['name' => " \t "], '23514'],
            'long name' => [['name' => str_repeat('a', 256)], '22001'],
            'null servings' => [['servings' => null], '23502'],
            'zero servings' => [['servings' => 0], '23514'],
            'negative servings' => [['servings' => -1], '23514'],
            'servings overflow' => [['servings' => '2147483648'], '22003'],
        ];
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidIngredients')]
    public function test_ingredient_constraints_reject_invalid_direct_sql_writes(array $changes, string $state): void
    {
        $recipe = Recipe::factory()->create();
        $product = Product::factory()->for($recipe->user)->create();
        $values = array_replace(['recipe_id' => $recipe->id, 'product_id' => $product->id, 'user_id' => $recipe->user_id, 'quantity_g' => '1.000'], $changes);
        try {
            DB::transaction(fn (): bool => DB::table('recipe_product')->insert($values));
            $this->fail('Invalid ingredient data was accepted by PostgreSQL.');
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->errorInfo[0]);
        }
        $this->assertDatabaseCount('recipe_product', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidIngredients(): array
    {
        return [
            'null recipe' => [['recipe_id' => null], '23502'],
            'missing recipe' => [['recipe_id' => -1], '23503'],
            'null product' => [['product_id' => null], '23502'],
            'missing product' => [['product_id' => -1], '23503'],
            'null owner' => [['user_id' => null], '23502'],
            'null quantity' => [['quantity_g' => null], '23502'],
            'zero quantity' => [['quantity_g' => '0.000'], '23514'],
            'negative quantity' => [['quantity_g' => '-0.001'], '23514'],
            'quantity rounds to zero' => [['quantity_g' => '0.0004'], '23514'],
            'quantity NaN' => [['quantity_g' => 'NaN'], '23514'],
            'quantity infinity' => [['quantity_g' => 'Infinity'], '22003'],
            'quantity overflow' => [['quantity_g' => '10000000.000'], '22003'],
        ];
    }

    public function test_composite_foreign_keys_block_cross_user_links_even_without_model_events(): void
    {
        $recipe = Recipe::factory()->create();
        $foreign = Product::factory()->create();
        foreach ([$recipe->user_id, $foreign->user_id] as $forgedOwner) {
            try {
                DB::transaction(fn (): bool => DB::table('recipe_product')->insert([
                    'recipe_id' => $recipe->id, 'product_id' => $foreign->id, 'user_id' => $forgedOwner, 'quantity_g' => '10.000',
                ]));
                $this->fail('A cross-user ingredient escaped the composite ownership constraints.');
            } catch (QueryException $exception) {
                $this->assertSame('23503', $exception->errorInfo[0]);
            }
        }
        $this->assertDatabaseCount('recipe_product', 0);
    }

    public function test_parent_owner_changes_cannot_break_existing_ingredient_ownership(): void
    {
        $ingredient = RecipeIngredient::factory()->create();
        $foreign = User::factory()->create();
        foreach (['recipes' => $ingredient->recipe_id, 'products' => $ingredient->product_id] as $table => $id) {
            try {
                DB::transaction(fn (): int => DB::table($table)->where('id', $id)->update(['user_id' => $foreign->id]));
                $this->fail('A referenced parent changed owner.');
            } catch (QueryException $exception) {
                $this->assertSame('23503', $exception->errorInfo[0]);
            }
            $this->assertDatabaseHas($table, ['id' => $id, 'user_id' => $ingredient->user_id]);
        }
    }

    public function test_exact_quantity_storage_schema_and_parent_indexes(): void
    {
        $ingredient = RecipeIngredient::factory()->create(['quantity_g' => '1234.567']);
        $this->assertSame('1234.567', DB::table('recipe_product')->where('id', $ingredient->id)->value('quantity_g'));
        $column = DB::table('information_schema.columns')->where('table_schema', 'public')
            ->where('table_name', 'recipe_product')->where('column_name', 'quantity_g')->first();
        $this->assertSame('numeric', $column->data_type);
        $this->assertSame(10, $column->numeric_precision);
        $this->assertSame(3, $column->numeric_scale);
        foreach (['recipes', 'products'] as $table) {
            $index = collect(Schema::getIndexes($table))->first(fn (array $index): bool => $index['columns'] === ['id', 'user_id']);
            $this->assertNotNull($index);
            $this->assertTrue($index['unique']);
        }
        $this->assertEqualsCanonicalizing(['id', 'recipe_id', 'product_id', 'user_id', 'quantity_g', 'created_at', 'updated_at'], Schema::getColumnListing('recipe_product'));
    }

    public function test_database_restricts_product_deletion_and_cascades_recipe_associations(): void
    {
        $ingredient = RecipeIngredient::factory()->create();
        try {
            DB::transaction(fn (): int => DB::table('products')->where('id', $ingredient->product_id)->delete());
            $this->fail('A referenced product was deleted.');
        } catch (QueryException $exception) {
            $this->assertSame('23001', $exception->errorInfo[0]);
        }
        $this->assertDatabaseHas('recipe_product', ['id' => $ingredient->id]);
        DB::table('recipes')->where('id', $ingredient->recipe_id)->delete();
        $this->assertDatabaseCount('recipe_product', 0);
        $this->assertDatabaseHas('products', ['id' => $ingredient->product_id]);
        DB::table('products')->where('id', $ingredient->product_id)->delete();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_user_deletion_is_restricted_when_recipes_exist(): void
    {
        $recipe = Recipe::factory()->create();
        try {
            DB::transaction(fn (): int => DB::table('users')->where('id', $recipe->user_id)->delete());
            $this->fail('An account with recipes was deleted implicitly.');
        } catch (QueryException $exception) {
            $this->assertSame('23001', $exception->errorInfo[0]);
        }
        $this->assertDatabaseHas('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseHas('users', ['id' => $recipe->user_id]);
    }
}
