<?php

namespace Tests\Postgres;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashing.bcrypt.rounds' => 4]);
    }

    public function test_postgres_stores_exact_fixed_scale_values_and_decimal_arithmetic(): void
    {
        $product = Product::factory()->create([
            'package_weight_g' => '1234.567', 'calories_per_100g' => '123456.78',
            'protein_per_100g' => '0.10', 'carbohydrates_per_100g' => '0.20', 'fat_per_100g' => '0.30',
        ]);
        $stored = DB::table('products')->where('id', $product->id)->first();

        $this->assertSame('1234.567', $stored->package_weight_g);
        $this->assertSame('123456.78', $stored->calories_per_100g);
        $this->assertSame('0.10', $stored->protein_per_100g);
        $this->assertSame('0.20', $stored->carbohydrates_per_100g);
        $this->assertSame('0.30', $stored->fat_per_100g);
        $sum = DB::table('products')->where('id', $product->id)
            ->selectRaw('protein_per_100g + carbohydrates_per_100g AS grams')->first();
        $this->assertSame('0.30', $sum->grams);
    }

    public function test_postgres_rounds_to_the_declared_scale_and_accepts_boundaries(): void
    {
        $rounded = Product::factory()->create(['package_weight_g' => '1.2345', 'protein_per_100g' => '1.235'])->fresh();
        $this->assertSame('1.235', $rounded->package_weight_g);
        $this->assertSame('1.24', $rounded->protein_per_100g);
        $boundary = Product::factory()->create([
            'package_weight_g' => '9999999.999', 'calories_per_100g' => '999999.99',
            'protein_per_100g' => '0.00', 'carbohydrates_per_100g' => '100.00', 'fat_per_100g' => '0.00',
        ])->fresh();
        $this->assertSame('9999999.999', $boundary->package_weight_g);
        $this->assertSame('999999.99', $boundary->calories_per_100g);
        $this->assertSame('100.00', $boundary->carbohydrates_per_100g);
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidProducts')]
    public function test_database_rejects_invalid_products_without_eloquent_validation(array $changes, string $state): void
    {
        $owner = User::factory()->create();
        $attributes = array_replace(Product::factory()->for($owner)->raw(), $changes);
        try {
            // Savepoints keep the outer test transaction usable after a rejected SQL write.
            DB::transaction(fn (): bool => DB::table('products')->insert($attributes));
            $this->fail('The database accepted invalid product data.');
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->errorInfo[0]);
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidProducts(): array
    {
        return [
            'missing owner' => [['user_id' => null], '23502'],
            'unknown owner' => [['user_id' => -1], '23503'],
            'missing name' => [['name' => null], '23502'],
            'empty name' => [['name' => ''], '23514'],
            'whitespace name' => [['name' => " \t\n "], '23514'],
            'long name' => [['name' => str_repeat('a', 256)], '22001'],
            'blank brand' => [['brand' => " \t "], '23514'],
            'long brand' => [['brand' => str_repeat('a', 256)], '22001'],
            'zero weight' => [['package_weight_g' => '0.000'], '23514'],
            'negative weight' => [['package_weight_g' => '-0.001'], '23514'],
            'weight overflow' => [['package_weight_g' => '10000000.000'], '22003'],
            'weight NaN' => [['package_weight_g' => 'NaN'], '23514'],
            'negative calories' => [['calories_per_100g' => '-0.01'], '23514'],
            'calories overflow' => [['calories_per_100g' => '1000000.00'], '22003'],
            'calories NaN' => [['calories_per_100g' => 'NaN'], '23514'],
            'calories infinity' => [['calories_per_100g' => 'Infinity'], '22003'],
            'negative protein' => [['protein_per_100g' => '-0.01'], '23514'],
            'protein above 100g' => [['protein_per_100g' => '100.01'], '23514'],
            'protein NaN' => [['protein_per_100g' => 'NaN'], '23514'],
            'negative carbohydrates' => [['carbohydrates_per_100g' => '-0.01'], '23514'],
            'carbohydrates above 100g' => [['carbohydrates_per_100g' => '100.01'], '23514'],
            'carbohydrates NaN' => [['carbohydrates_per_100g' => 'NaN'], '23514'],
            'negative fat' => [['fat_per_100g' => '-0.01'], '23514'],
            'fat above 100g' => [['fat_per_100g' => '100.01'], '23514'],
            'fat NaN' => [['fat_per_100g' => 'NaN'], '23514'],
            'missing calories' => [['calories_per_100g' => null], '23502'],
            'missing protein' => [['protein_per_100g' => null], '23502'],
            'missing carbohydrates' => [['carbohydrates_per_100g' => null], '23502'],
            'missing fat' => [['fat_per_100g' => null], '23502'],
        ];
    }

    public function test_owner_deletion_is_restricted_while_products_exist(): void
    {
        $product = Product::factory()->create();
        try {
            DB::transaction(fn (): int => DB::table('users')->where('id', $product->user_id)->delete());
            $this->fail('An owner with products must not be deleted implicitly.');
        } catch (QueryException $exception) {
            $this->assertSame('23001', $exception->errorInfo[0]);
        }
        $this->assertDatabaseHas('users', ['id' => $product->user_id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_numeric_column_types_and_owner_index_match_the_persistence_contract(): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')->where('table_name', 'products')
            ->get()->keyBy('column_name');

        foreach ([
            'package_weight_g' => [10, 3],
            'calories_per_100g' => [8, 2],
            'protein_per_100g' => [5, 2],
            'carbohydrates_per_100g' => [5, 2],
            'fat_per_100g' => [5, 2],
        ] as $column => [$precision, $scale]) {
            $this->assertSame('numeric', $columns[$column]->data_type);
            $this->assertSame($precision, $columns[$column]->numeric_precision);
            $this->assertSame($scale, $columns[$column]->numeric_scale);
        }

        $index = collect(Schema::getIndexes('products'))->first(
            fn (array $index): bool => $index['columns'] === ['user_id', 'name']
        );
        $this->assertNotNull($index);
        $this->assertFalse($index['unique']);
    }
}
