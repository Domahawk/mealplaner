<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        config(['hashing.bcrypt.rounds' => 4]);
    }

    public function test_owner_can_create_read_update_and_delete_a_product(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $created = $this->postJson('/api/products', $this->attributes())
            ->assertCreated()->assertHeader('Content-Type', 'application/json')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.name', 'Rolled oats')
            ->assertJsonPath('data.package_weight_g', '500.125')
            ->assertJsonPath('data.calories_per_100g', '367.10')
            ->assertJsonPath('data.protein_per_100g', '13.20')
            ->assertJsonPath('data.carbohydrates_per_100g', '60.00')
            ->assertJsonPath('data.fat_per_100g', '6.50');
        $id = $created->json('data.id');
        $this->assertDatabaseHas('products', ['id' => $id, 'user_id' => $owner->id]);
        $this->assertSame([
            'id', 'name', 'brand', 'package_weight_g', 'calories_per_100g',
            'protein_per_100g', 'carbohydrates_per_100g', 'fat_per_100g', 'created_at', 'updated_at',
        ], array_keys($created->json('data')));

        $this->getJson('/api/products/'.$id)->assertOk()->assertExactJson($created->json());
        $this->putJson('/api/products/'.$id, $this->attributes(['name' => 'Oats', 'fat_per_100g' => '7.25']))
            ->assertOk()->assertJsonPath('data.name', 'Oats')->assertJsonPath('data.fat_per_100g', '7.25');
        $this->patchJson('/api/products/'.$id, ['brand' => null, 'package_weight_g' => null])
            ->assertOk()->assertJsonPath('data.brand', null)->assertJsonPath('data.package_weight_g', null)
            ->assertJsonPath('data.name', 'Oats')->assertJsonPath('data.protein_per_100g', '13.20');
        $this->deleteJson('/api/products/'.$id)->assertNoContent()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseMissing('products', ['id' => $id]);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
        $this->getJson('/api/products/'.$id)->assertNotFound();
    }

    public function test_optional_fields_can_be_omitted_and_zero_nutrition_is_valid(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/products', [
            'name' => 'Water', 'calories_per_100g' => 0, 'protein_per_100g' => 0,
            'carbohydrates_per_100g' => 0, 'fat_per_100g' => 0,
        ])->assertCreated()->assertJsonPath('data.brand', null)->assertJsonPath('data.package_weight_g', null)
            ->assertJsonPath('data.calories_per_100g', '0.00')->assertJsonPath('data.fat_per_100g', '0.00');
    }

    public function test_input_is_trimmed_and_only_validated_business_fields_are_written(): void
    {
        $this->actingAs(User::factory()->create());
        $response = $this->postJson('/api/products', $this->attributes([
            'name' => '  Oats  ', 'brand' => '  ', 'unexpected' => 'ignored',
        ]))->assertCreated()->assertJsonPath('data.name', 'Oats')->assertJsonPath('data.brand', null)
            ->assertJsonMissingPath('data.unexpected');
        $this->assertSame('Oats', Product::query()->findOrFail($response->json('data.id'))->name);
    }

    public function test_schema_boundaries_and_shorter_decimal_inputs_are_accepted(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/products', $this->attributes([
            'name' => str_repeat('a', 255), 'brand' => str_repeat('b', 255),
            'package_weight_g' => '9999999.999', 'calories_per_100g' => '999999.99',
            'protein_per_100g' => 100, 'carbohydrates_per_100g' => '0.1', 'fat_per_100g' => 0,
        ]))->assertCreated()->assertJsonPath('data.package_weight_g', '9999999.999')
            ->assertJsonPath('data.protein_per_100g', '100.00')->assertJsonPath('data.carbohydrates_per_100g', '0.10');
    }

    public function test_listing_is_paginated_stably_and_scoped_including_counts_and_links(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Product::factory()->for($other)->count(3)->create();
        $products = Product::factory()->for($owner)->count(23)->create(['name' => 'Same name']);
        $this->actingAs($owner);

        $this->getJson('/api/products')->assertOk()->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 23)->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.last_page', 2)->assertJsonPath('data.0.id', $products[0]->id);
        $this->getJson('/api/products?page=2')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.current_page', 2)->assertJsonPath('data.0.id', $products[20]->id);
        $small = $this->getJson('/api/products?per_page=2')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 23);
        $this->assertStringContainsString('per_page=2', $small->json('links.next'));
        $this->assertSame($products->take(2)->modelKeys(), array_column($small->json('data'), 'id'));
        $this->getJson('/api/products?page=99')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 23);
    }

    public function test_a_user_with_no_products_receives_an_empty_collection_without_foreign_counts(): void
    {
        Product::factory()->count(2)->create();
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    #[DataProvider('endpoints')]
    public function test_guests_cannot_access_any_product_operation(string $method, bool $item): void
    {
        $product = Product::factory()->create();
        $this->json($method, '/api/products'.($item ? '/'.$product->id : ''), $this->attributes())
            ->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        $this->assertDatabaseCount('products', 1);
    }

    #[DataProvider('endpoints')]
    public function test_each_http_operation_invokes_authorization(string $method, bool $item): void
    {
        $product = Product::factory()->create();
        $this->actingAs($product->user);
        Gate::before(fn (): Response => Response::deny('Fixture action denial.'));
        $this->json($method, '/api/products'.($item ? '/'.$product->id : ''), $this->attributes())
            ->assertForbidden();
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => $product->name]);
    }

    /** @return array<string, array{string, bool}> */
    public static function endpoints(): array
    {
        return [
            'list' => ['GET', false], 'store' => ['POST', false], 'show' => ['GET', true],
            'replace' => ['PUT', true], 'patch' => ['PATCH', true], 'delete' => ['DELETE', true],
        ];
    }

    #[DataProvider('itemMethods')]
    public function test_foreign_and_missing_ids_return_404_even_with_invalid_payloads(string $method): void
    {
        $foreign = Product::factory()->create();
        $this->actingAs(User::factory()->create());
        foreach ([$foreign->id, $foreign->id + 9999] as $id) {
            $this->json($method, '/api/products/'.$id, $this->attributes())->assertNotFound();
            $this->json($method, '/api/products/'.$id, ['name' => null])->assertNotFound();
        }
        $this->assertDatabaseHas('products', ['id' => $foreign->id, 'name' => $foreign->name, 'user_id' => $foreign->user_id]);
        $this->assertDatabaseCount('products', 1);
    }

    /** @return array<string, array{string}> */
    public static function itemMethods(): array
    {
        return ['show' => ['GET'], 'replace' => ['PUT'], 'patch' => ['PATCH'], 'delete' => ['DELETE']];
    }

    public function test_non_integer_and_out_of_range_route_ids_return_json_404_instead_of_database_errors(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['invalid', '-1', '0', '1.2', '999999999999999999999999999999'] as $id) {
            $this->getJson('/api/products/'.$id)->assertNotFound()->assertHeader('Content-Type', 'application/json');
        }
    }

    public function test_html_accept_headers_do_not_turn_api_errors_into_redirects_or_html(): void
    {
        $this->get('/api/products', ['Accept' => 'text/html'])->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
        $this->actingAs(User::factory()->create());
        $this->get('/api/products/999999', ['Accept' => 'text/html'])->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
        $this->post('/api/products', [], ['Accept' => 'text/html'])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'calories_per_100g']);
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidInputs')]
    public function test_invalid_create_and_patch_requests_do_not_change_database_records(array $changes, string $field): void
    {
        $product = Product::factory()->create();
        $original = $product->fresh()->getAttributes();
        $this->actingAs($product->user);
        $this->postJson('/api/products', $this->attributes($changes))
            ->assertUnprocessable()->assertJsonValidationErrors($field)->assertJsonStructure(['message', 'errors']);
        $this->patchJson('/api/products/'.$product->id, $changes)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($original, $product->fresh()->getAttributes());
        $this->assertDatabaseCount('products', 1);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidInputs(): array
    {
        $cases = [
            'null name' => [['name' => null], 'name'],
            'blank name' => [['name' => '   '], 'name'],
            'name array' => [['name' => []], 'name'],
            'name with null byte' => [['name' => "Oat\0s"], 'name'],
            'long name' => [['name' => str_repeat('a', 256)], 'name'],
            'long brand' => [['brand' => str_repeat('a', 256)], 'brand'],
            'brand array' => [['brand' => ['unexpected']], 'brand'],
            'brand with null byte' => [['brand' => "Brand\0name"], 'brand'],
            'zero weight' => [['package_weight_g' => '0'], 'package_weight_g'],
            'negative weight' => [['package_weight_g' => '-0.001'], 'package_weight_g'],
            'weight overflow' => [['package_weight_g' => '10000000.000'], 'package_weight_g'],
            'weight precision' => [['package_weight_g' => '1.2345'], 'package_weight_g'],
        ];
        foreach (['calories_per_100g', 'protein_per_100g', 'carbohydrates_per_100g', 'fat_per_100g'] as $field) {
            foreach ([null, '-0.01', '1.001', 'not-a-number', 'NaN', 'Infinity', '1e2', true, [], '1,25'] as $index => $invalid) {
                $cases[$field.' invalid '.$index] = [[$field => $invalid], $field];
            }
            $cases[$field.' overflow'] = [[$field => $field === 'calories_per_100g' ? '1000000' : '100.01'], $field];
        }
        foreach (['user_id', 'owner_id', 'id', 'created_at', 'updated_at'] as $field) {
            $cases[$field.' protected'] = [[$field => 999999], $field];
            $cases[$field.' present null'] = [[$field => null], $field];
        }

        return $cases;
    }

    public function test_post_and_put_require_core_fields_while_patch_preserves_omitted_fields(): void
    {
        $product = Product::factory()->create();
        $this->actingAs($product->user);
        foreach (['POST' => '/api/products', 'PUT' => '/api/products/'.$product->id] as $method => $url) {
            $this->json($method, $url, [])->assertUnprocessable()->assertJsonValidationErrors([
                'name', 'calories_per_100g', 'protein_per_100g', 'carbohydrates_per_100g', 'fat_per_100g',
            ]);
        }
        $this->patchJson('/api/products/'.$product->id, ['name' => 'Renamed'])->assertOk()
            ->assertJsonPath('data.name', 'Renamed')->assertJsonPath('data.calories_per_100g', $product->calories_per_100g);
    }

    public function test_pagination_rejects_invalid_sizes_and_page_numbers(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['0', '-1', '1.5', 'letters', '2147483648'] as $value) {
            $this->getJson('/api/products?page='.$value)->assertUnprocessable()->assertJsonValidationErrors('page');
        }
        foreach (['0', '-1', '101', '1.5', 'letters'] as $value) {
            $this->getJson('/api/products?per_page='.$value)->assertUnprocessable()->assertJsonValidationErrors('per_page');
        }
    }

    #[DataProvider('mutationMethods')]
    public function test_cookie_authenticated_mutations_require_csrf_protection(string $method): void
    {
        $product = Product::factory()->create();
        $this->actingAs($product->user);
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app->make(Encrypter::class)) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $url = '/api/products'.($method === 'POST' ? '' : '/'.$product->id);
        $this->json($method, $url, $this->attributes())->assertStatus(419);
        $this->assertDatabaseCount('products', 1);
        $this->withSession(['_token' => 'product-csrf-fixture']);
        $response = $this->json($method, $url, $this->attributes(), ['X-CSRF-TOKEN' => 'product-csrf-fixture']);
        $response->assertStatus(match ($method) {
            'POST' => 201, 'DELETE' => 204, default => 200
        });
    }

    /** @return array<string, array{string}> */
    public static function mutationMethods(): array
    {
        return ['create' => ['POST'], 'replace' => ['PUT'], 'patch' => ['PATCH'], 'delete' => ['DELETE']];
    }

    /** @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function attributes(array $changes = []): array
    {
        return array_replace([
            'name' => 'Rolled oats', 'brand' => 'Fixture brand', 'package_weight_g' => '500.125',
            'calories_per_100g' => '367.10', 'protein_per_100g' => '13.20',
            'carbohydrates_per_100g' => '60.00', 'fat_per_100g' => '6.50',
        ], $changes);
    }
}
