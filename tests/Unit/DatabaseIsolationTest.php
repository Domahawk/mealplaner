<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class DatabaseIsolationTest extends TestCase
{
    public function test_inherited_local_configuration_is_rejected_even_when_putenv_says_testing(): void
    {
        $process = new Process([
            PHP_BINARY,
            '-d',
            'display_errors=stderr',
            '-r',
            '$_SERVER["APP_ENV"] = "local"; $_ENV["APP_ENV"] = "testing"; putenv("APP_ENV=testing"); require $argv[1];',
            dirname(__DIR__).'/bootstrap.php',
        ], env: ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '']);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('Tests require isolated', $process->getErrorOutput());
    }

    public function test_a_custom_config_cache_override_is_rejected_before_boot(): void
    {
        $process = new Process([PHP_BINARY, '-d', 'display_errors=stderr', dirname(__DIR__).'/bootstrap.php'], env: [
            'APP_CONFIG_CACHE' => '/tmp/untrusted-config-cache.php',
        ]);
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('Tests require uncached', $process->getErrorOutput());
    }

    /** @param array<string, string> $configuration */
    #[DataProvider('unsafeConfigurations')]
    public function test_unsafe_database_configuration_is_rejected_before_boot(array $configuration): void
    {
        $process = new Process([PHP_BINARY, '-d', 'display_errors=stderr', dirname(__DIR__).'/bootstrap.php'], env: array_merge([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ], $configuration));
        $process->run();

        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('Tests require isolated', $process->getErrorOutput());
    }

    /** @return array<string, array{array<string, string>}> */
    public static function unsafeConfigurations(): array
    {
        return [
            'non-testing environment' => [['APP_ENV' => 'local']],
            'file-backed SQLite' => [['DB_DATABASE' => '/tmp/not-a-test-database.sqlite']],
            'database URL override' => [['DB_URL' => 'sqlite:///tmp/not-a-test-database.sqlite']],
            'unsupported database driver' => [['DB_CONNECTION' => 'mysql']],
            'ordinary PostgreSQL database' => [['DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'not-a-test-database']],
            'wrong PostgreSQL role' => [[
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => 'mealplaner_test_0123456789abcdef',
                'DB_USERNAME' => 'not-a-test-role',
                'DB_PASSWORD' => 'synthetic-test-only',
            ]],
            'missing PostgreSQL password' => [[
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => 'mealplaner_test_0123456789abcdef',
                'DB_USERNAME' => 'mealplaner_test_0123456789abcdef',
                'DB_PASSWORD' => '',
            ]],
        ];
    }
}
