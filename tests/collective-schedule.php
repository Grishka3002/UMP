<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/collective-schedule.php';

function check_dates(string $label, array $expected, string $from, string $to, array $days, string $exceptions = ''): void
{
    $actual = collective_schedule_dates($from, $to, $days, $exceptions);
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': ' . json_encode($actual));
    }
    echo "PASS: $label\n";
}

check_dates('Tuesdays with exceptions', ['2026-09-01', '2026-09-15', '2026-09-29'], '2026-09-01', '2026-09-30', ['2'], "08.09.2026, 2026-09-22\n08.09.2026");
check_dates('Multiple weekdays and inclusive boundaries', ['2026-09-01', '2026-09-03', '2026-09-08'], '2026-09-01', '2026-09-08', ['2', '4']);
check_dates('Leap day', ['2028-02-29'], '2028-02-28', '2028-03-01', ['2']);
check_dates('Year boundary', ['2026-12-29', '2027-01-05'], '2026-12-28', '2027-01-05', ['2']);
check_dates('Single day', ['2026-09-01'], '2026-09-01', '2026-09-01', ['2']);
check_dates('Duplicate weekdays do not duplicate bookings', ['2026-09-01'], '2026-09-01', '2026-09-01', ['2', '2']);

$invalid = [
    ['2026-02-30', '2026-03-10', ['2'], ''],
    ['2026-09-10', '2026-09-01', ['2'], ''],
    ['2026-01-01', '2028-01-01', ['2'], ''],
    ['2026-09-01', '2026-09-30', [], ''],
    ['2026-09-01', '2026-09-30', ['8'], ''],
    ['2026-09-01', '2026-09-30', ['2'], '31.09.2026'],
    ['2026-09-01', '2026-09-30', ['2'], '01.10.2026'],
    ['2026-09-01', '2026-09-01', ['2'], '01.09.2026'],
    ['2026-09-01', '2026-09-01', ['3'], ''],
];
foreach ($invalid as $index => $arguments) {
    try {
        collective_schedule_dates(...$arguments);
    } catch (RuntimeException $exception) {
        echo "PASS: invalid case $index\n";
        continue;
    }
    throw new RuntimeException("Invalid case $index was accepted");
}
