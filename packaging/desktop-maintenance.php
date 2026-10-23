<?php

// Desktop startup only. Back up SQLite before upgrades and verify every existing
// column/row before committing migrations. Never seed, reset, or regenerate keys.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function quoteIdentifier(string $name): string
{
    return '"'.str_replace('"', '""', $name).'"';
}

function snapshot(PDO $pdo, ?array $original = null): array
{
    $result = [];
    $tables = $original === null
        ? $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN)
        : array_keys($original);
    foreach ($tables as $table) {
        $columns = $original[$table]['columns'] ?? array_column($pdo->query('PRAGMA table_info('.quoteIdentifier($table).')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        $rows = $pdo->query('SELECT '.implode(',', array_map('quoteIdentifier', $columns)).' FROM '.quoteIdentifier($table));
        $hashes = [];
        while ($row = $rows->fetch(PDO::FETCH_NUM)) {
            $hashes[] = hash('sha256', serialize($row));
        }
        sort($hashes, SORT_STRING);
        $result[$table] = ['columns' => $columns, 'count' => count($hashes), 'hash' => hash('sha256', implode('', $hashes))];
    }

    return $result;
}

try {
    $connection = DB::connection();
    if ($connection->getDriverName() !== 'sqlite') {
        throw new RuntimeException('Desktop updates require a local SQLite database. Your database configuration was preserved.');
    }
    $migrator = $app->make('migrator');
    $files = $migrator->getMigrationFiles(database_path('migrations'));
    $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
    if (array_diff(array_keys($files), $ran) === []) {
        echo "Database is up to date.\n";
        exit(0);
    }

    $pdo = $connection->getPdo();
    if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
        throw new RuntimeException('Database integrity check failed. No migration was run.');
    }
    $backupDir = __DIR__.'/update-backups/migration-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
    if (! mkdir($backupDir, 0700, true) && ! is_dir($backupDir)) {
        throw new RuntimeException('Could not create the pre-migration backup.');
    }
    $backup = $backupDir.'/database.sqlite';
    // SQLite makes a consistent snapshot, including committed WAL contents.
    $pdo->exec('VACUUM INTO '.$pdo->quote($backup));
    $backupPdo = new PDO('sqlite:'.$backup);
    if ($backupPdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
        throw new RuntimeException('Backup verification failed. No migration was run.');
    }
    $before = snapshot($backupPdo);
    file_put_contents($backupDir.'/records-before.json', json_encode($before, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $backupPdo = null;
    if (is_file(__DIR__.'/.env') && ! copy(__DIR__.'/.env', $backupDir.'/.env')) {
        throw new RuntimeException('Could not back up the existing configuration.');
    }

    // SQLite DDL is transactional. If any old data changes, roll back the whole upgrade.
    $connection->beginTransaction();
    try {
        $status = Artisan::call('migrate', ['--force' => true]);
        if ($status !== 0) {
            throw new RuntimeException('Migration failed: '.Artisan::output());
        }
        if (snapshot($pdo, $before) !== $before || $pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
            throw new RuntimeException('Upgrade would change existing records. The database upgrade was rolled back.');
        }
        $connection->commit();
    } catch (Throwable $error) {
        while ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
        throw $error;
    }
    echo 'Database updated; existing records verified. Backup: '.$backupDir.PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
