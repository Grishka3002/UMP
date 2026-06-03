<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

app_config();
require_admin();

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

if ($dateFrom === '' || $dateTo === '') {
    set_flash('error', 'Для выгрузки укажите период дат.');
    redirect('/admin/dashboard.php');
}

if ($dateFrom > $dateTo) {
    set_flash('error', 'Дата начала периода не может быть позже даты окончания.');
    redirect('/admin/dashboard.php');
}

$bookings = get_admin_bookings_filtered([
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);

$settings = get_settings();
$spaces = get_spaces($settings);
$filename = sprintf('bookings_%s_%s.csv', $dateFrom, $dateTo);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'wb');

if ($output === false) {
    exit;
}

fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, [
    'Дата',
    'Пространство',
    'Начало',
    'Окончание',
    'Название мероприятия',
    'Краткое описание',
    'ФИО ответственного',
    'Телефон ответственного',
    'Количество человек',
    'Создано',
], ';');

foreach ($bookings as $booking) {
    fputcsv($output, [
        (new DateTimeImmutable($booking['booking_date']))->format('d.m.Y'),
        $spaces[(int) $booking['space_id']] ?? ('Пространство #' . $booking['space_id']),
        (string) $booking['start_time'],
        (string) $booking['end_time'],
        display_event_name($booking['event_name']),
        display_optional_text($booking['description']),
        display_optional_text($booking['responsible_name']),
        display_optional_text($booking['responsible_phone']),
        (string) ((int) ($booking['attendees_count'] ?? 0)),
        (new DateTimeImmutable($booking['created_at']))->format('d.m.Y H:i'),
    ], ';');
}

fclose($output);
exit;
