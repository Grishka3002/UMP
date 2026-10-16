<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function ensure_collective_members(): void
{
    db()->exec('CREATE TABLE IF NOT EXISTS collective_booking_members (
        booking_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        series_id CHAR(32) NOT NULL,
        INDEX collective_series (series_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function create_collective_bookings(array $values, array $dates, array $schedule): void
{
    ensure_collective_members();
    $pdo = db();
    $series = bin2hex(random_bytes(16));
    $pdo->beginTransaction();
    try {
        $member = $pdo->prepare('INSERT INTO collective_booking_members (booking_id, series_id) VALUES (?, ?)');
        foreach ($dates as $date) {
            $args = [(int) $values['space_id'], $date, $values['event_name'], $values['description'],
                $values['responsible_name'], $values['responsible_phone'], (int) $values['attendees_count'],
                $values['start_time'], $values['end_time']];
            validate_booking_data(...array_merge($args, [$schedule['opening_time'], $schedule['closing_time']]));
            create_booking(...$args);
            $member->execute([(int) $pdo->lastInsertId(), $series]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function collective_selected_ids($input): array
{
    if (!is_array($input) || $input === [] || count($input) > 500) {
        throw new RuntimeException('Выберите от 1 до 500 броней.');
    }
    $ids = [];
    foreach ($input as $value) {
        if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new RuntimeException('Некорректный номер брони.');
        }
        $ids[] = (int) $value;
    }
    $ids = array_values(array_unique($ids));
    sort($ids);
    return $ids;
}

function collective_deletion_rows(array $ids, bool $lock = false): array
{
    $ids = collective_selected_ids($ids);
    $stmt = db()->prepare('SELECT * FROM bookings WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute($ids);
    return $stmt->fetchAll();
}

function delete_confirmed_collective_rows(array $snapshot): int
{
    $ids = collective_selected_ids(array_column($snapshot, 'id'));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if (collective_deletion_rows($ids, true) !== $snapshot) {
            throw new RuntimeException('Список броней изменился. Выберите занятия и подтвердите удаление заново.');
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare('DELETE FROM bookings WHERE id IN (' . $placeholders . ')');
        $stmt->execute($ids);
        $count = $stmt->rowCount();
        $stmt = $pdo->prepare('DELETE FROM collective_booking_members WHERE booking_id IN (' . $placeholders . ')');
        $stmt->execute($ids);
        $pdo->commit();
        return $count;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
