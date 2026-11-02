<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$name = getenv('TEST_DATABASE');
if (! is_string($name) || preg_match('/^starter_test_[0-9]+_[0-9]+$/', $name) !== 1) {
    throw new RuntimeException('Only a generated starter_test_<timestamp>_<pid> database is allowed.');
}

$connection = config('database.connections.pgsql');
$connection['database'] = $name;
$connection['url'] = null;
config(['database.connections.verification' => $connection, 'database.default' => 'verification']);

if (DB::connection()->getDriverName() !== 'pgsql' || DB::selectOne('SELECT current_database() AS name')->name !== $name) {
    throw new RuntimeException('Isolated PostgreSQL connection failed.');
}

if (($argv[1] ?? '') === 'write') {
    Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
    Schema::create('starter_verification', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('value');
    });
    DB::table('starter_verification')->insert(['id' => 1, 'value' => 'survives-recreation']);
} elseif (($argv[1] ?? '') === 'read') {
    if (DB::table('starter_verification')->where('id', 1)->value('value') !== 'survives-recreation') {
        throw new RuntimeException('PostgreSQL did not retain the test row.');
    }
} else {
    throw new RuntimeException('Use write or read.');
}

$response = $app->make(HttpKernel::class)->handle(Request::create('/api/ready', 'GET'));
if ($response->getStatusCode() !== 200 || json_decode($response->getContent(), true) !== ['status' => 'ok', 'database' => 'connected']) {
    throw new RuntimeException('Readiness endpoint failed against real PostgreSQL.');
}

echo "Isolated PostgreSQL query, migrations, API, and persistence probe passed.\n";
