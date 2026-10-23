<?php

$root = realpath($argv[1]);
if (! $root || ! str_contains(str_replace('\\', '/', $root), '/output/installer-check-')) {
    throw new RuntimeException('An isolated installer test folder is required.');
}
$pdo = new PDO('sqlite:'.$root.'/database/database.sqlite', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
function insertRow(PDO $pdo, string $table, array $values): void
{
    $columns = array_column($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC), 'name');
    $values = array_intersect_key($values, array_flip($columns));
    $pdo->prepare('INSERT INTO '.$table.' ('.implode(',', array_keys($values)).') VALUES ('.implode(',', array_fill(0, count($values), '?')).')')->execute(array_values($values));
}
if (($argv[2] ?? '') === 'seed') {
    insertRow($pdo, 'users', ['id' => 1, 'name' => 'Upgrade Teacher', 'email' => 'upgrade@example.test', 'username' => 'upgrade', 'password' => password_hash('upgrade-fixture-only', PASSWORD_BCRYPT)]);
    insertRow($pdo, 'academic_terms', ['id' => 1, 'user_id' => 1, 'name' => 'Preserved term', 'school_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2026-12-20']);
    insertRow($pdo, 'sections', ['id' => 1, 'user_id' => 1, 'academic_term_id' => 1, 'name' => 'Preserved class', 'subject_code' => 'SAFE', 'subject_title' => 'Update verification', 'enrollment_token' => 'installer-test-only-token']);
    foreach (range(1, 4) as $id) {
        insertRow($pdo, 'students', ['id' => $id, 'section_id' => 1, 'student_number' => 'SAFE-'.$id, 'first_name' => 'Student '.$id, 'last_name' => 'Preserved']);
    }
    insertRow($pdo, 'attendance_sessions', ['id' => 1, 'section_id' => 1, 'session_date' => '2026-10-20', 'starts_at' => '08:00', 'ends_at' => '09:00', 'duration_minutes' => 60]);
    insertRow($pdo, 'assessments', ['id' => 1, 'section_id' => 1, 'type' => 'activity', 'title' => 'Preserved scores', 'conducted_on' => '2026-10-20', 'max_points' => 100]);
    foreach (range(1, 4) as $id) {
        insertRow($pdo, 'attendance_records', ['attendance_session_id' => 1, 'student_id' => $id, 'status' => $id === 4 ? 'absent' : 'present', 'attended_minutes' => $id === 4 ? 0 : 60]);
        insertRow($pdo, 'assessment_scores', ['assessment_id' => 1, 'student_id' => $id, 'score' => 11 + $id, 'remarks' => 'Keep this score '.$id]);
    }
    $snapshot = [];
    foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $columns = array_column($pdo->query('PRAGMA table_info("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC), 'name');
        $rows = $pdo->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_NUM);
        $snapshot[$table] = ['columns' => $columns, 'rows' => $rows];
    }
    file_put_contents($root.'/fixture-records.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    echo "Fixture seeded.\n";
} else {
    $snapshot = json_decode(file_get_contents($root.'/fixture-records.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($snapshot as $table => $before) {
        $columns = implode(',', array_map(fn ($column) => '"'.$column.'"', $before['columns']));
        if ($pdo->query('SELECT '.$columns.' FROM "'.$table.'"')->fetchAll(PDO::FETCH_NUM) !== $before['rows']) {
            throw new RuntimeException('Records changed in '.$table);
        }
    }
    foreach (['users' => 'grading_formula', 'assessment_scores' => 'marked_absent'] as $table => $column) {
        $columns = array_column($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (! in_array($column, $columns, true)) {
            throw new RuntimeException('Missing new column '.$column);
        }
    }
    if ($pdo->query("SELECT count(*) FROM sqlite_master WHERE name = 'upgrade_guard_probe'")->fetchColumn() !== 0) {
        throw new RuntimeException('The blocked migration left a schema change behind.');
    }
    if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
        throw new RuntimeException('SQLite integrity failed.');
    }
    echo "All original records unchanged; formula and absence columns added; SQLite integrity ok.\n";
}
