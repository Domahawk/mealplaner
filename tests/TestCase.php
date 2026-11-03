<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    private static ?string $migratedDatabase = null;

    /** @return array<class-string, class-string> */
    protected function setUpTraits(): array
    {
        // Laravel calls this after boot and before DatabaseTransactions/RefreshDatabase.
        $this->assertTrue($this->app->environment('testing'));
        $this->assertFalse($this->app->configurationIsCached());
        $connection = DB::connection();
        $this->assertEmpty($connection->getConfig('url'));

        if ($connection->getDriverName() === 'sqlite') {
            $this->assertSame(':memory:', $connection->getDatabaseName());
        } else {
            $this->assertSame('pgsql', $connection->getDriverName());
            $database = $connection->getDatabaseName();
            $this->assertMatchesRegularExpression('/^mealplaner_test_[a-f0-9]{16}$/', $database);
            $this->assertSame($database, $connection->getConfig('username'));

            $identity = $connection->selectOne('SELECT current_database() AS database, current_user AS role', [], false);
            $this->assertSame($database, $identity->database);
            $this->assertSame($database, $identity->role);
            $permissions = $connection->selectOne('SELECT r.rolsuper, r.rolcreatedb, r.rolcreaterole, d.datdba = r.oid AS owns_database FROM pg_roles r JOIN pg_database d ON d.datname = current_database() WHERE r.rolname = current_user', [], false);
            $this->assertFalse($permissions->rolsuper);
            $this->assertFalse($permissions->rolcreatedb);
            $this->assertFalse($permissions->rolcreaterole);
            $this->assertTrue($permissions->owns_database);

            if (self::$migratedDatabase !== $database) {
                $this->artisan('migrate', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
                self::$migratedDatabase = $database;
            }
        }

        return parent::setUpTraits();
    }
}
