<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = DB::connection();
$lockKey = 1_124_986_531;

$exitCode = $connection->transaction(function () use ($connection, $lockKey): int {
    $connection->select('select pg_advisory_xact_lock(?)', [$lockKey]);

    return Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
});

fwrite(STDOUT, Artisan::output());

if ($exitCode !== 0) {
    throw new RuntimeException("Database migration failed with exit code {$exitCode}.");
}

exit(0);
