<?php

require __DIR__.'/../vendor/autoload.php';

if (is_file(__DIR__.'/../bootstrap/cache/config.php')
    || ! empty($_SERVER['APP_CONFIG_CACHE'])
    || ! empty($_ENV['APP_CONFIG_CACHE'])
    || getenv('APP_CONFIG_CACHE')) {
    throw new RuntimeException('Tests require uncached application configuration.');
}

// Match Laravel's environment precedence, including values inherited in $_SERVER.
$variable = static function (string $name): string|false {
    $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

    return is_string($value) ? $value : false;
};

$driver = $variable('DB_CONNECTION');
$database = $variable('DB_DATABASE');
$sqlite = $driver === 'sqlite' && $database === ':memory:';
$postgres = $driver === 'pgsql'
    && is_string($database)
    && preg_match('/^mealplaner_test_[a-f0-9]{16}$/', $database) === 1
    && $variable('DB_USERNAME') === $database
    && is_string($variable('DB_PASSWORD'))
    && $variable('DB_PASSWORD') !== '';

if ($variable('APP_ENV') !== 'testing' || $variable('DB_URL') !== '' || (! $sqlite && ! $postgres)) {
    throw new RuntimeException('Tests require isolated SQLite memory storage or a generated PostgreSQL test database.');
}
