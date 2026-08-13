<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$unpooledDatabaseUrl = env('DB_DATABASE_URL_UNPOOLED');

if ($unpooledDatabaseUrl) {
    config(['database.connections.pgsql.url' => $unpooledDatabaseUrl]);
    DB::purge('pgsql');
}

$connection = DB::connection();
$lockKey = 1_124_986_531;

$connection->select('select pg_advisory_lock(?)', [$lockKey]);

try {
    $exitCode = Artisan::call('migrate', [
        '--force' => true,
        '--no-interaction' => true,
    ]);

    echo Artisan::output();

    if ($exitCode !== 0) {
        throw new RuntimeException("Database migration failed with exit code {$exitCode}.");
    }
} finally {
    $connection->select('select pg_advisory_unlock(?)', [$lockKey]);
}

exit(0);
