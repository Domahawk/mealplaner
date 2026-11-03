<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

class DatabaseSafetyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_an_unsafe_connection_is_rejected_before_transaction_traits_run(): void
    {
        $original = config('database.default');
        $path = sys_get_temp_dir().'/mealplaner-unsafe-'.bin2hex(random_bytes(8)).'/database.sqlite';
        config([
            'database.default' => 'isolation_probe',
            'database.connections.isolation_probe' => [
                'driver' => 'sqlite', 'database' => $path, 'prefix' => '',
            ],
        ]);

        $this->expectException(AssertionFailedError::class);

        try {
            // Re-enter the actual hook after a configuration change, before any trait runs.
            $this->setUpTraits();
        } finally {
            config(['database.default' => $original]);
            DB::purge('isolation_probe');
            $this->assertFileDoesNotExist($path);
        }
    }
}
