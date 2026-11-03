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
        $this->assertContains(DB::connection()->getDriverName(), ['sqlite', 'pgsql']);
        $this->assertNotEmpty(DB::select('SELECT 1'));
    }

    public function test_health_is_public_and_returns_the_expected_json(): void
    {
        $this->get('/api/health')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['status' => 'ok'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_health_does_not_require_a_database_connection(): void
    {
        config(['database.default' => 'unconfigured']);

        $this->get('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_health_rejects_writes_with_a_json_method_error(): void
    {
        $this->postJson('/api/health')->assertStatus(405)
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_readiness_checks_an_actual_query(): void
    {
        $this->getJson('/api/ready')
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

        $this->getJson('/api/ready')
            ->assertStatus(503)
            ->assertExactJson([
                'status' => 'error',
                'database' => 'unavailable',
                'message' => 'Database connectivity is temporarily unavailable.',
            ])
            ->assertDontSee('private-password')
            ->assertDontSee('private-host');

        $this->getJson('/api/health')->assertOk()->assertExactJson(['status' => 'ok']);
        $this->get('/up')->assertOk();
    }
}
