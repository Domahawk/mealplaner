<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        config(['hashing.bcrypt.rounds' => 4]);
    }

    public function test_factory_persists_a_product_with_an_owner_and_optional_information(): void
    {
        $product = Product::factory()->create()->fresh();

        $this->assertInstanceOf(User::class, $product->user);
        $this->assertTrue($product->user->products->contains($product));
        $this->assertNull($product->brand);
        $this->assertNull($product->package_weight_g);
        $this->assertSame('122.50', $product->calories_per_100g);
        $this->assertSame('7.25', $product->protein_per_100g);
        $this->assertSame('20.00', $product->carbohydrates_per_100g);
        $this->assertSame('1.50', $product->fat_per_100g);
        $this->assertNotNull($product->created_at);
        $this->assertNotNull($product->updated_at);
    }

    public function test_packaged_products_keep_decimal_values_as_strings_in_serialization(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->for($user)->create([
            'name' => 'Rolled oats', 'brand' => 'Fixture brand', 'package_weight_g' => '500.125',
            'calories_per_100g' => '367.10', 'protein_per_100g' => '13.20',
            'carbohydrates_per_100g' => '60.00', 'fat_per_100g' => '6.50',
        ])->fresh();

        $this->assertTrue($product->user->is($user));
        $this->assertSame('Fixture brand', $product->brand);
        $values = $product->toArray();
        $this->assertSame('500.125', $values['package_weight_g']);
        $this->assertSame('367.10', $values['calories_per_100g']);
        $this->assertSame('13.20', $values['protein_per_100g']);
        $this->assertSame('60.00', $values['carbohydrates_per_100g']);
        $this->assertSame('6.50', $values['fat_per_100g']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_relationship_queries_hide_other_owners_and_duplicate_names_are_allowed(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $owned = Product::factory()->for($alice)->count(2)->create(['name' => 'Oats']);
        $foreign = Product::factory()->for($bob)->create(['name' => 'Oats']);

        $this->assertEqualsCanonicalizing($owned->modelKeys(), $alice->products()->pluck('id')->all());
        $this->assertSame([$foreign->id], $bob->products()->pluck('id')->all());
        $this->expectException(ModelNotFoundException::class);
        $alice->products()->findOrFail($foreign->id);
    }

    public function test_product_ownership_cannot_be_mass_assigned(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $values = Product::factory()->for($other)->raw();
        $product = $owner->products()->create($values);
        $product->fill(['user_id' => $other->id, 'name' => 'Updated product'])->save();

        $this->assertSame($owner->id, $product->fresh()->user_id);
        $this->assertSame('Updated product', $product->fresh()->name);
        $this->assertFalse($product->isFillable('user_id'));
    }

    public function test_real_product_policy_is_discovered_and_denies_foreign_records_as_not_found(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->for($owner)->create();

        $this->assertInstanceOf(ProductPolicy::class, Gate::getPolicyFor($product));
        $this->assertTrue(Gate::forUser($owner)->allows('viewAny', Product::class));
        $this->assertTrue(Gate::forUser($owner)->allows('create', Product::class));
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($owner)->allows($ability, $product));
            $denial = Gate::forUser($other)->inspect($ability, $product);
            $this->assertTrue($denial->denied());
            $this->assertSame(404, $denial->status());
            $this->assertFalse(Gate::allows($ability, $product));
        }
        $this->assertFalse(Gate::allows('create', Product::class));
    }
}
