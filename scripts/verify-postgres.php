<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';

$arguments = array_slice($_SERVER['argv'], 1);
if ($arguments !== [] && $arguments !== ['--coverage']) {
    fwrite(STDERR, "Usage: php scripts/verify-postgres.php [--coverage]\n");
    exit(1);
}
$coverage = $arguments === ['--coverage'];
if ($coverage && ! extension_loaded('xdebug')) {
    fwrite(STDERR, "PHP coverage requires Xdebug. Rebuild the backend development image.\n");
    exit(1);
}

if (is_file(__DIR__.'/../bootstrap/cache/config.php')
    || ! empty($_SERVER['APP_CONFIG_CACHE'])
    || ! empty($_ENV['APP_CONFIG_CACHE'])
    || getenv('APP_CONFIG_CACHE')) {
    fwrite(STDERR, "PostgreSQL verification requires uncached local configuration.\n");
    exit(1);
}

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('local') || config('database.default') !== 'pgsql') {
    fwrite(STDERR, "PostgreSQL verification requires the existing local pgsql environment.\n");
    exit(1);
}

// Administrative credentials are used only for provisioning, never by the test process.
$name = 'mealplaner_test_'.bin2hex(random_bytes(8));
$password = bin2hex(random_bytes(32));
$identifier = '"'.$name.'"';
$databaseCreated = false;
$roleCreated = false;
$admin = null;
$exitCode = 1;

try {
    $connection = DB::connection();
    $admin = $connection->getPdo();
    $configuration = $connection->getConfig();
    $developmentDatabase = $connection->selectOne('SELECT current_database() AS name')->name;

    if ($name === $developmentDatabase || $connection->getDriverName() !== 'pgsql') {
        throw new RuntimeException('An isolated PostgreSQL database is required.');
    }

    // PDO avoids application query logging of the generated test password.
    $admin->exec('CREATE ROLE '.$identifier.' LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD '.$admin->quote($password));
    $roleCreated = true;
    $admin->exec('CREATE DATABASE '.$identifier.' OWNER '.$identifier." TEMPLATE template0 ENCODING 'UTF8'");
    $databaseCreated = true;
    $admin->exec('REVOKE ALL ON DATABASE '.$identifier.' FROM PUBLIC');

    $command = [
        PHP_BINARY,
        base_path('vendor/bin/phpunit'),
        '--configuration',
        base_path('phpunit.postgres.xml'),
        '--colors=never',
        '--fail-on-warning',
        '--fail-on-risky',
    ];
    if ($coverage) {
        array_push($command,
            '--coverage-clover', base_path('coverage/php/clover.xml'),
            '--coverage-html', base_path('coverage/php/html'),
            '--coverage-text',
        );
    }

    $process = new Process($command, base_path(), [
        'XDEBUG_MODE' => $coverage ? 'coverage' : 'off',
        'APP_ENV' => 'testing',
        'APP_DEBUG' => 'false',
        'APP_KEY' => false,
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER' => 'array',
        'DB_CONNECTION' => 'pgsql',
        'DB_URL' => '',
        'DB_HOST' => $configuration['host'],
        'DB_PORT' => (string) $configuration['port'],
        'DB_DATABASE' => $name,
        'DB_USERNAME' => $name,
        'DB_PASSWORD' => $password,
        'DB_SSLMODE' => $configuration['sslmode'],
    ]);
    $process->setTimeout(180);
    $exitCode = $process->run();
    echo str_replace($password, '[redacted]', $process->getOutput());
    fwrite(STDERR, str_replace($password, '[redacted]', $process->getErrorOutput()));
} catch (Throwable) {
    // Connection/SQL exceptions may include credentials; never render their messages.
    fwrite(STDERR, "PostgreSQL verification failed. Check local connectivity and provisioning permissions.\n");
    $exitCode = 1;
} finally {
    // These flags refer only to resources successfully created by this invocation.
    // Never force-disconnect other sessions or touch pre-existing databases/roles.
    try {
        if ($databaseCreated || $roleCreated) {
            if (! $admin instanceof PDO) {
                throw new LogicException('Test-resource cleanup requires its administrative connection.');
            }

            if ($databaseCreated) {
                $admin->exec('DROP DATABASE '.$identifier);
            }
            if ($roleCreated) {
                $admin->exec('DROP ROLE '.$identifier);
            }
        }
    } catch (Throwable) {
        fwrite(STDERR, "Cleanup of generated PostgreSQL test resources failed; no forced cleanup was attempted.\n");
        $exitCode = 1;
    }
}

if ($exitCode === 0) {
    echo "Isolated PostgreSQL backend suite passed; temporary database and role removed.\n";
}

exit($exitCode);
