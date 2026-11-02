<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    public function test_existing_migrations_work_in_the_isolated_memory_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('cache_locks'));
        $this->assertDatabaseHas('migrations', ['migration' => '0001_01_01_000001_create_cache_table']);
    }

    public function test_a_record_can_be_created_and_retrieved_without_development_data(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        DB::table('cache')->insert([
            'key' => 'mealplaner-test',
            'value' => 'isolated-value',
            'expiration' => 2147483647,
        ]);

        $this->assertSame('isolated-value', DB::table('cache')->where('key', 'mealplaner-test')->value('value'));
    }
}
