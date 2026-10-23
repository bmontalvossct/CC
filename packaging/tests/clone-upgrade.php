<?php

// Read the installed database only; every upgrade operation runs on its clone.
$sourceRoot = $argv[1];
$cloneRoot = realpath($argv[2]);
if (! $cloneRoot || ! str_contains(str_replace('\\', '/', $cloneRoot), '/output/installed-clone-')) {
    throw new RuntimeException('An isolated installed-clone test folder is required.');
}
function fingerprints(SQLite3 $db, ?array $previous = null): array
{
    $tables = [];
    if ($previous === null) {
        $query = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations' ORDER BY name");
        while ($row = $query->fetchArray(SQLITE3_NUM)) {
            $tables[] = $row[0];
        }
    } else {
        $tables = array_keys($previous);
    }
    $result = [];
    $quote = fn ($value) => '"'.str_replace('"', '""', $value).'"';
    foreach ($tables as $table) {
        $columns = $previous[$table]['columns'] ?? [];
        if ($columns === []) {
            $query = $db->query('PRAGMA table_info('.$quote($table).')');
            while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $row['name'];
            }
        }
        $query = $db->query('SELECT '.implode(',', array_map($quote, $columns)).' FROM '.$quote($table));
        $hashes = [];
        while ($row = $query->fetchArray(SQLITE3_NUM)) {
            $hashes[] = hash('sha256', serialize($row));
        }
        sort($hashes, SORT_STRING);
        $result[$table] = ['columns' => $columns, 'count' => count($hashes), 'hash' => hash('sha256', implode('', $hashes))];
    }

    return $result;
}
if (($argv[3] ?? '') === 'before') {
    $clone = new SQLite3($cloneRoot.'/database/database.sqlite', SQLITE3_OPEN_READWRITE);
    $clone->enableExceptions(true);
    if ($clone->querySingle('PRAGMA integrity_check') !== 'ok') {
        throw new RuntimeException('Read-only file clone failed integrity validation.');
    }
    $before = fingerprints($clone);
    file_put_contents($cloneRoot.'/original-records.json', json_encode($before, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents($cloneRoot.'/original-env-sha256.txt', hash_file('sha256', $sourceRoot.'/.env'));
    echo 'Installed database/WAL cloned with read-only file locks; clone integrity ok.'.PHP_EOL;
} else {
    $before = json_decode(file_get_contents($cloneRoot.'/original-records.json'), true, flags: JSON_THROW_ON_ERROR);
    $clone = new SQLite3($cloneRoot.'/database/database.sqlite', SQLITE3_OPEN_READONLY);
    if (fingerprints($clone, $before) !== $before || hash_file('sha256', $sourceRoot.'/.env') !== file_get_contents($cloneRoot.'/original-env-sha256.txt')) {
        throw new RuntimeException('Original or cloned records/configuration changed unexpectedly.');
    }
    $sourceFiles = json_decode(file_get_contents($cloneRoot.'/source-files.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($sourceFiles as $file => $hash) {
        if (hash_file('sha256', $sourceRoot.'/database/'.$file) !== $hash) {
            throw new RuntimeException('The installed source database files changed during the test.');
        }
    }
    if ($clone->querySingle('PRAGMA integrity_check') !== 'ok') {
        throw new RuntimeException('Clone integrity failed.');
    }
    echo 'PASS: Upgraded clone preserves all original records across '.count($before).' tables; installed database and .env remain unchanged.'.PHP_EOL;
}
