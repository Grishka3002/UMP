<?php
declare(strict_types=1);

// Isolated SQLite database; only MySQL DDL and row-lock syntax are translated.
// No connection to the application's configured database is made.
class TestDatabase extends PDO
{
    public function exec(string $statement): int|false
    {
        if (str_starts_with($statement, 'CREATE TABLE IF NOT EXISTS collective_booking_members')) {
            $statement = 'CREATE TABLE IF NOT EXISTS collective_booking_members (booking_id INTEGER PRIMARY KEY, series_id TEXT NOT NULL)';
        }
        return parent::exec($statement);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
$testPdo = new TestDatabase('sqlite::memory:');
$testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$testPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$testPdo->sqliteCreateFunction('NOW', static fn () => '2026-10-16 12:00:00');
$testPdo->exec('CREATE TABLE bookings (id INTEGER PRIMARY KEY AUTOINCREMENT, space_id INTEGER, booking_date TEXT, event_name TEXT, description TEXT, responsible_name TEXT, responsible_phone TEXT, attendees_count INTEGER, start_time TEXT, end_time TEXT, created_at TEXT)');
$fixture = sys_get_temp_dir() . '/ump-management-' . bin2hex(random_bytes(8));
mkdir($fixture);
file_put_contents($fixture . '/db.php', '<?php function db(): PDO { return $GLOBALS["testPdo"]; }');
copy(__DIR__ . '/../includes/functions.php', $fixture . '/functions.php');
copy(__DIR__ . '/../includes/collective-management.php', $fixture . '/collective-management.php');
require $fixture . '/collective-management.php';
function check(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
    echo 'PASS: ' . $label . PHP_EOL;
}
function rejected(callable $action, string $label): void {
    try { $action(); } catch (RuntimeException $exception) { echo 'PASS: ' . $label . PHP_EOL; return; }
    throw new RuntimeException('Expected rejection: ' . $label);
}
try {
    $values = ['space_id' => 1, 'event_name' => 'Collective', 'description' => 'Rehearsal', 'responsible_name' => 'Test', 'responsible_phone' => '000', 'attendees_count' => 5, 'start_time' => '10:00', 'end_time' => '11:00'];
    $hours = ['opening_time' => '09:00', 'closing_time' => '21:00'];
    create_collective_bookings($values, ['2026-10-20', '2026-10-27'], $hours);
    check((int) db()->query('SELECT COUNT(*) FROM bookings')->fetchColumn() === 2, 'Create both occurrences');
    check((int) db()->query('SELECT COUNT(DISTINCT series_id) FROM collective_booking_members')->fetchColumn() === 1, 'Occurrences share one series');
    create_collective_bookings($values, ['2026-11-03'], $hours);
    check((int) db()->query('SELECT COUNT(DISTINCT series_id) FROM collective_booking_members')->fetchColumn() === 2, 'Same name creates distinct series');
    rejected(fn () => create_collective_bookings($values, ['2026-10-19', '2026-10-20'], $hours), 'Conflict rolls back new series');
    check((int) db()->query('SELECT COUNT(*) FROM bookings')->fetchColumn() === 3 && (int) db()->query('SELECT COUNT(*) FROM collective_booking_members')->fetchColumn() === 3, 'No partial bookings or memberships');
    $snapshot = collective_deletion_rows([1, 2]);
    db()->exec("UPDATE bookings SET event_name = 'Edited' WHERE id = 2");
    rejected(fn () => delete_confirmed_collective_rows($snapshot), 'Changed booking invalidates confirmation');
    check((int) db()->query('SELECT COUNT(*) FROM bookings')->fetchColumn() === 3, 'Stale confirmation deletes nothing');
    $snapshot = collective_deletion_rows([1, 2]);
    db()->exec("CREATE TRIGGER fail_member_delete BEFORE DELETE ON collective_booking_members BEGIN SELECT RAISE(ABORT, 'Simulated failure'); END");
    rejected(fn () => delete_confirmed_collective_rows($snapshot), 'Storage failure aborts deletion');
    check((int) db()->query('SELECT COUNT(*) FROM bookings')->fetchColumn() === 3, 'Deletion failure rolls back all bookings');
    db()->exec('DROP TRIGGER fail_member_delete');
    check(delete_confirmed_collective_rows($snapshot) === 2, 'Delete entire confirmed series');
    check((int) db()->query('SELECT COUNT(*) FROM bookings')->fetchColumn() === 1 && (int) db()->query('SELECT COUNT(*) FROM collective_booking_members')->fetchColumn() === 1, 'Other series is preserved');
    create_booking(1, '2026-12-01', 'Legacy', 'Old', 'Test', '000', 2, '12:00', '13:00');
    $legacy = (int) db()->lastInsertId();
    check(delete_confirmed_collective_rows(collective_deletion_rows([$legacy])) === 1, 'Delete legacy booking without membership');
    rejected(fn () => delete_confirmed_collective_rows($snapshot), 'Repeated deletion is rejected');
    check(collective_selected_ids(['3', '3', '1']) === [1, 3], 'Deduplicate and sort selection');
    foreach ([[], ['0'], ['1 OR 1=1'], [['1']], array_fill(0, 501, '1')] as $bad) {
        rejected(fn () => collective_selected_ids($bad), 'Invalid selection rejected');
    }
} finally {
    foreach (['db.php', 'functions.php', 'collective-management.php'] as $file) unlink($fixture . '/' . $file);
    rmdir($fixture);
}
