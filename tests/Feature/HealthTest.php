<?php

namespace Tests\Feature;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_application_boots(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_isolated_test_database_connects(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertNotEmpty(DB::select('SELECT 1'));
    }

    public function test_health_checks_an_actual_query(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'database' => 'connected'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_database_failure_is_safe_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('select')->once()->with('SELECT 1')
            ->andThrow(new RuntimeException('postgres://private-user:private-password@private-host'));
        DB::shouldReceive('connection')->once()->andReturn($connection);

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'error',
                'database' => 'unavailable',
                'message' => 'Database connectivity is temporarily unavailable.',
            ])
            ->assertDontSee('private-password')
            ->assertDontSee('private-host');
    }
}
