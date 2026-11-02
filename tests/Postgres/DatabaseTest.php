<?php

namespace Tests\Postgres;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.pgsql.database');
        $this->assertTrue($this->app->environment('testing'));
        $this->assertSame('pgsql', config('database.default'));
        $this->assertIsString($database);
        $this->assertMatchesRegularExpression('/^mealplaner_test_[a-f0-9]{16}$/', $database);
        $this->assertSame($database, config('database.connections.pgsql.username'));
        $this->assertEmpty(config('database.connections.pgsql.url'));

        $identity = DB::selectOne('SELECT current_database() AS database, current_user AS role');
        $this->assertSame($database, $identity->database);
        $this->assertSame($database, $identity->role);
        $permissions = DB::selectOne('SELECT r.rolsuper, r.rolcreatedb, r.rolcreaterole, d.datdba = r.oid AS owns_database FROM pg_roles r JOIN pg_database d ON d.datname = current_database() WHERE r.rolname = current_user');
        $this->assertFalse($permissions->rolsuper);
        $this->assertFalse($permissions->rolcreatedb);
        $this->assertFalse($permissions->rolcreaterole);
        $this->assertTrue($permissions->owns_database);

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_laravel_connects_to_its_isolated_postgres_database(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->getJson('/api/ready')->assertOk()
            ->assertExactJson(['status' => 'ok', 'database' => 'connected']);
    }

    public function test_existing_migrations_are_compatible_with_postgres(): void
    {
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('cache_locks'));
        $this->assertDatabaseHas('migrations', ['migration' => '0001_01_01_000001_create_cache_table']);
        $this->artisan('migrate:status')->assertSuccessful();
    }

    public function test_a_committed_record_survives_a_new_database_connection(): void
    {
        $key = 'mealplaner-persistence-probe';
        $this->assertFalse(DB::table('cache')->where('key', $key)->exists());

        try {
            DB::table('cache')->insert([
                'key' => $key,
                'value' => 'survives-reconnection',
                'expiration' => 2147483647,
            ]);
            $previousSession = DB::selectOne('SELECT pg_backend_pid() AS id')->id;

            DB::purge();
            DB::reconnect();

            $this->assertNotSame($previousSession, DB::selectOne('SELECT pg_backend_pid() AS id')->id);
            $this->assertSame('survives-reconnection', DB::table('cache')->where('key', $key)->value('value'));
        } finally {
            DB::table('cache')->where('key', $key)->delete();
        }
    }
}
