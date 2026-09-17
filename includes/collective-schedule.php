<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function collective_schedule_dates(string $from, string $to, array $weekdays, string $exceptions): array
{
    foreach ([$from, $to] as $value) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException('Укажите корректный период расписания.');
        }
    }
    if ($from > $to || (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 366) {
        throw new RuntimeException('Период должен идти от начала к окончанию и быть не длиннее 366 дней.');
    }
    if ($weekdays === [] || array_diff($weekdays, ['1', '2', '3', '4', '5', '6', '7']) !== []) {
        throw new RuntimeException('Выберите хотя бы один день недели.');
    }
    $excluded = [];
    foreach (preg_split('/[\s,;]+/u', trim($exceptions), -1, PREG_SPLIT_NO_EMPTY) as $value) {
        $format = str_contains($value, '.') ? '!d.m.Y' : '!Y-m-d';
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date === false || $date->format(substr($format, 1)) !== $value) {
            throw new RuntimeException('Дата исключения «' . $value . '» неверна. Используйте ДД.ММ.ГГГГ.');
        }
        $key = $date->format('Y-m-d');
        if ($key < $from || $key > $to) {
            throw new RuntimeException('Дата исключения «' . $value . '» находится вне периода.');
        }
        $excluded[$key] = true;
    }
    $dates = array_values(array_filter(iterate_dates_in_range($from, $to), static function (string $date) use ($weekdays, $excluded): bool {
        return in_array((new DateTimeImmutable($date))->format('N'), $weekdays, true) && !isset($excluded[$date]);
    }));
    if ($dates === []) {
        throw new RuntimeException('В выбранном периоде не осталось занятий. Проверьте дни недели и исключения.');
    }
    return $dates;
}
