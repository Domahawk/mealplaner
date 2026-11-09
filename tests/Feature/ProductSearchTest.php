<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        config(['hashing.bcrypt.rounds' => 4]);
    }

    public function test_case_insensitive_substring_search_matches_name_or_brand_without_leaking_foreign_products(): void
    {
        $owner = User::factory()->create();
        $byName = Product::factory()->for($owner)->create(['name' => 'Rolled OATS', 'brand' => null]);
        $byBrand = Product::factory()->for($owner)->create(['name' => 'Porridge', 'brand' => 'Oats Company']);
        Product::factory()->for($owner)->create(['name' => 'Rice', 'brand' => null]);
        Product::factory()->create(['name' => 'Private oats', 'brand' => 'Oats Company']);
        $this->actingAs($owner);

        $response = $this->getJson('/api/products?search=%20oAtS%20')->assertOk()->assertJsonPath('meta.total', 2);
        $this->assertSame([$byName->id, $byBrand->id], array_column($response->json('data'), 'id'));
        $this->getJson('/api/products?search=missing')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/products?search=%20%20')->assertOk()->assertJsonPath('meta.total', 3);
    }

    #[DataProvider('literalSearches')]
    public function test_search_uses_literal_parameterized_values(string $term): void
    {
        $owner = User::factory()->create();
        $match = Product::factory()->for($owner)->create(['name' => 'Contains '.$term.' literally']);
        Product::factory()->for($owner)->create(['name' => 'Unrelated product']);
        Product::factory()->create(['name' => $term]);
        $this->actingAs($owner);
        $response = $this->getJson('/api/products?'.http_build_query(['search' => $term]))
            ->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame([$match->id], array_column($response->json('data'), 'id'));
    }

    /** @return array<string, array{string}> */
    public static function literalSearches(): array
    {
        return ['percent' => ['%'], 'underscore' => ['_'], 'escape' => ['!'], 'backslash' => ['\\'], 'SQL fragment' => ["%' OR 1=1 --"]];
    }

    #[DataProvider('nutrients')]
    public function test_each_nutrient_accepts_inclusive_open_and_closed_ranges(string $nutrient): void
    {
        $owner = User::factory()->create();
        $column = $nutrient.'_per_100g';
        $zero = Product::factory()->for($owner)->create([$column => '0.00']);
        $middle = Product::factory()->for($owner)->create([$column => '10.25']);
        $high = Product::factory()->for($owner)->create([$column => '100.00']);
        Product::factory()->create([$column => '10.25']);
        $this->actingAs($owner);
        foreach ([
            [$nutrient.'_min' => '10.25'],
            [$nutrient.'_max' => '10.25'],
            [$nutrient.'_min' => '10.25', $nutrient.'_max' => '10.25'],
            [$nutrient.'_min' => '0', $nutrient.'_max' => '0'],
            [$nutrient.'_min' => '', $nutrient.'_max' => ''],
        ] as $index => $query) {
            $expected = match ($index) {
                0 => [$middle->id, $high->id], 1 => [$zero->id, $middle->id],
                2 => [$middle->id], 3 => [$zero->id], default => [$zero->id, $middle->id, $high->id],
            };
            $response = $this->getJson('/api/products?'.http_build_query($query))->assertOk();
            $this->assertSame($expected, array_column($response->json('data'), 'id'));
        }
    }

    /** @return array<string, array{string}> */
    public static function nutrients(): array
    {
        return ['calories' => ['calories'], 'protein' => ['protein'], 'carbohydrates' => ['carbohydrates'], 'fat' => ['fat']];
    }

    public function test_combined_filters_search_and_pagination_preserve_owner_scope_order_and_validated_links(): void
    {
        $owner = User::factory()->create();
        $values = ['name' => 'Target oats', 'brand' => 'Example', 'calories_per_100g' => '100.25', 'protein_per_100g' => '10.00', 'carbohydrates_per_100g' => '20.00', 'fat_per_100g' => '1.50'];
        $matches = Product::factory()->for($owner)->count(3)->create($values);
        foreach (['name' => 'Rice', 'calories_per_100g' => '200.00', 'protein_per_100g' => '0.00', 'carbohydrates_per_100g' => '90.00', 'fat_per_100g' => '10.00'] as $column => $value) {
            Product::factory()->for($owner)->create(array_replace($values, [$column => $value]));
        }
        Product::factory()->create($values);
        $this->actingAs($owner);
        $query = ['search' => 'TARGET', 'calories_max' => '100.25', 'protein_min' => '10', 'carbohydrates_max' => '20', 'fat_max' => '1.50', 'per_page' => 2, 'user_id' => 'forged'];
        $first = $this->getJson('/api/products?'.http_build_query($query))->assertOk()
            ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2)->assertJsonCount(2, 'data');
        parse_str(parse_url($first->json('links.next'), PHP_URL_QUERY), $linkQuery);
        $this->assertSame('TARGET', $linkQuery['search']);
        $this->assertSame('1.50', $linkQuery['fat_max']);
        $this->assertArrayNotHasKey('user_id', $linkQuery);
        $second = $this->getJson('/api/products?'.http_build_query(array_replace($query, ['page' => 2])))
            ->assertOk()->assertJsonPath('meta.total', 3)->assertJsonCount(1, 'data');
        $this->assertSame($matches->modelKeys(), array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->getJson('/api/products?search=TARGET&protein_min=99')->assertOk()->assertJsonPath('meta.total', 0);
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('invalidQueries')]
    public function test_invalid_filters_return_field_errors_without_database_changes(array $query, string $field): void
    {
        $product = Product::factory()->create();
        $this->actingAs($product->user);
        $this->getJson('/api/products?'.http_build_query($query))->assertUnprocessable()
            ->assertJsonValidationErrors($field)->assertJsonStructure(['message', 'errors']);
        $this->assertDatabaseCount('products', 1);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidQueries(): array
    {
        $cases = [
            'search array' => [['search' => ['unexpected']], 'search'],
            'long search' => [['search' => str_repeat('a', 256)], 'search'],
            'null byte search' => [['search' => "a\0b"], 'search'],
        ];
        foreach (['calories', 'protein', 'carbohydrates', 'fat'] as $nutrient) {
            foreach (['min', 'max'] as $bound) {
                $field = $nutrient.'_'.$bound;
                foreach (['-0.01', '1.001', 'NaN', 'Infinity', '1e2', ['array'], $nutrient === 'calories' ? '1000000' : '100.01'] as $index => $value) {
                    $cases[$field.' invalid '.$index] = [[$field => $value], $field];
                }
            }
            $cases[$nutrient.' reversed'] = [[$nutrient.'_min' => '10.01', $nutrient.'_max' => '10.00'], $nutrient.'_max'];
            $cases[$nutrient.' invalid sibling'] = [[$nutrient.'_min' => ['invalid'], $nutrient.'_max' => '10.00'], $nutrient.'_min'];
        }

        return $cases;
    }

    public function test_search_and_filters_still_require_authentication(): void
    {
        $this->getJson('/api/products?search=oats&protein_min=0')->assertUnauthorized();
    }
}
