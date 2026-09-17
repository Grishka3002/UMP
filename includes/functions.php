<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
        date_default_timezone_set($config['app']['timezone'] ?? 'UTC');
    }

    return $config;
}

function start_session_once(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function display_event_name(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return 'Название не указано';
    }

    // If old broken data was saved as question marks, show a readable fallback.
    if (preg_match('/^\?+$/', $value) === 1) {
        return 'Название повреждено';
    }

    return $value;
}

function display_optional_text(?string $value, string $fallback = 'Не указано'): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return $fallback;
    }

    if (preg_match('/^\?+$/', $value) === 1) {
        return 'Текст поврежден';
    }

    return $value;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function set_flash(string $type, string $message): void
{
    start_session_once();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    start_session_once();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
}

function get_settings(): array
{
    $stmt = db()->query('SELECT setting_key, setting_value FROM settings');
    $settings = [];

    foreach ($stmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    return [
        'space_1_name' => $settings['space_1_name'] ?? 'Студпространство 8 корпус',
        'space_2_name' => $settings['space_2_name'] ?? 'Пространство возле УМП',
        'space_3_name' => $settings['space_3_name'] ?? 'Пространство 3',
        'space_4_name' => $settings['space_4_name'] ?? 'Пространство 4',
        'space_1_opening_time' => $settings['space_1_opening_time'] ?? '09:00',
        'space_2_opening_time' => $settings['space_2_opening_time'] ?? '09:00',
        'space_3_opening_time' => $settings['space_3_opening_time'] ?? '09:00',
        'space_4_opening_time' => $settings['space_4_opening_time'] ?? '09:00',
        'space_1_closing_time' => $settings['space_1_closing_time'] ?? '21:00',
        'space_2_closing_time' => $settings['space_2_closing_time'] ?? '21:00',
        'space_3_closing_time' => $settings['space_3_closing_time'] ?? '21:00',
        'space_4_closing_time' => $settings['space_4_closing_time'] ?? '21:00',
        'space_1_description' => $settings['space_1_description'] ?? '',
        'space_2_description' => $settings['space_2_description'] ?? '',
        'space_3_description' => $settings['space_3_description'] ?? '',
        'space_4_description' => $settings['space_4_description'] ?? '',
        'space_1_visible' => $settings['space_1_visible'] ?? '1',
        'space_2_visible' => $settings['space_2_visible'] ?? '1',
        'space_3_visible' => $settings['space_3_visible'] ?? '1',
        'space_4_visible' => $settings['space_4_visible'] ?? '1',
        'space_1_image' => $settings['space_1_image'] ?? '',
        'space_2_image' => $settings['space_2_image'] ?? '',
        'space_3_image' => $settings['space_3_image'] ?? '',
        'space_4_image' => $settings['space_4_image'] ?? '',
        'booking_contact_name' => $settings['booking_contact_name'] ?? '',
        'booking_contact_phone' => $settings['booking_contact_phone'] ?? '',
    ];
}

function save_setting_value(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([
        'setting_key' => $key,
        'setting_value' => $value,
    ]);
}

function save_settings(array $spaceSettings): void
{
    foreach ($spaceSettings as $key => $value) {
        save_setting_value($key, $value);
    }
}

function get_spaces(array $settings): array
{
    return [
        1 => trim((string) ($settings['space_1_name'] ?? '')) !== '' ? (string) $settings['space_1_name'] : 'Студпространство 8 корпус',
        2 => trim((string) ($settings['space_2_name'] ?? '')) !== '' ? (string) $settings['space_2_name'] : 'Пространство возле УМП',
        3 => trim((string) ($settings['space_3_name'] ?? '')) !== '' ? (string) $settings['space_3_name'] : 'Пространство 3',
        4 => trim((string) ($settings['space_4_name'] ?? '')) !== '' ? (string) $settings['space_4_name'] : 'Пространство 4',
    ];
}

function get_space_visibility(array $settings): array
{
    return [
        1 => ($settings['space_1_visible'] ?? '1') === '1',
        2 => ($settings['space_2_visible'] ?? '1') === '1',
        3 => ($settings['space_3_visible'] ?? '1') === '1',
        4 => ($settings['space_4_visible'] ?? '1') === '1',
    ];
}

function get_public_spaces(array $settings): array
{
    $spaces = get_spaces($settings);
    $visibility = get_space_visibility($settings);

    return array_filter(
        $spaces,
        static fn (int $spaceId): bool => $visibility[$spaceId] ?? false,
        ARRAY_FILTER_USE_KEY
    );
}

function resolve_public_space_id(int $requestedSpaceId, array $visibleSpaces): int
{
    if (isset($visibleSpaces[$requestedSpaceId])) {
        return $requestedSpaceId;
    }

    $firstVisibleId = array_key_first($visibleSpaces);

    return $firstVisibleId === null ? 1 : (int) $firstVisibleId;
}

function get_space_images(array $settings): array
{
    return [
        1 => (string) ($settings['space_1_image'] ?? ''),
        2 => (string) ($settings['space_2_image'] ?? ''),
        3 => (string) ($settings['space_3_image'] ?? ''),
        4 => (string) ($settings['space_4_image'] ?? ''),
    ];
}

function get_space_opening_times(array $settings): array
{
    return [
        1 => (string) ($settings['space_1_opening_time'] ?? '09:00'),
        2 => (string) ($settings['space_2_opening_time'] ?? '09:00'),
        3 => (string) ($settings['space_3_opening_time'] ?? '09:00'),
        4 => (string) ($settings['space_4_opening_time'] ?? '09:00'),
    ];
}

function get_space_closing_times(array $settings): array
{
    return [
        1 => (string) ($settings['space_1_closing_time'] ?? '21:00'),
        2 => (string) ($settings['space_2_closing_time'] ?? '21:00'),
        3 => (string) ($settings['space_3_closing_time'] ?? '21:00'),
        4 => (string) ($settings['space_4_closing_time'] ?? '21:00'),
    ];
}

function get_space_descriptions(array $settings): array
{
    return [
        1 => (string) ($settings['space_1_description'] ?? ''),
        2 => (string) ($settings['space_2_description'] ?? ''),
        3 => (string) ($settings['space_3_description'] ?? ''),
        4 => (string) ($settings['space_4_description'] ?? ''),
    ];
}

function get_space_schedule(array $settings, int $spaceId): array
{
    $openingTimes = get_space_opening_times($settings);
    $closingTimes = get_space_closing_times($settings);
    $descriptions = get_space_descriptions($settings);

    return [
        'opening_time' => $openingTimes[$spaceId] ?? '09:00',
        'closing_time' => $closingTimes[$spaceId] ?? '21:00',
        'description' => $descriptions[$spaceId] ?? '',
    ];
}

function upload_root(): string
{
    return __DIR__ . '/../uploads/spaces';
}

function ensure_upload_dir_exists(): void
{
    $dir = upload_root();

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Не удалось создать папку для изображений.');
    }
}

function delete_uploaded_file_if_local(string $relativePath): void
{
    if ($relativePath === '' || !str_starts_with($relativePath, '/uploads/spaces/')) {
        return;
    }

    $fullPath = __DIR__ . '/..' . $relativePath;

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function upload_space_image(int $spaceId, array $file, string $currentPath = ''): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $currentPath;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Не удалось загрузить изображение пространства #' . $spaceId . '.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $originalName = (string) ($file['name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Файл изображения пространства #' . $spaceId . ' не получен.');
    }

    $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['webp', 'jpg', 'jpeg', 'png'];

    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('Разрешены только изображения webp, jpg, jpeg, png.');
    }

    ensure_upload_dir_exists();

    $filename = sprintf('space_%d_%s.%s', $spaceId, bin2hex(random_bytes(8)), $extension);
    $destination = upload_root() . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException('Не удалось сохранить изображение пространства #' . $spaceId . '.');
    }

    delete_uploaded_file_if_local($currentPath);

    return '/uploads/spaces/' . $filename;
}

function normalize_space_id(int $spaceId): int
{
    return in_array($spaceId, [1, 2, 3, 4], true) ? $spaceId : 1;
}

function iterate_dates_in_range(string $startDate, string $endDate): array
{
    $start = DateTimeImmutable::createFromFormat('Y-m-d', $startDate);
    $end = DateTimeImmutable::createFromFormat('Y-m-d', $endDate);

    if ($start === false || $end === false || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate) {
        throw new RuntimeException('Укажите корректный период мероприятия.');
    }

    if ($start > $end) {
        throw new RuntimeException('Дата окончания должна быть не раньше даты начала.');
    }

    $dates = [];

    for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
        $dates[] = $day->format('Y-m-d');
    }

    return $dates;
}

function validate_booking_data(
    int $spaceId,
    string $date,
    string $eventName,
    string $description,
    string $responsibleName,
    string $responsiblePhone,
    int $attendeesCount,
    string $startTime,
    string $endTime,
    string $openingTime,
    string $closingTime,
    ?int $ignoreId = null
): void {
    if (!in_array($spaceId, [1, 2, 3, 4], true)) {
        throw new RuntimeException('Выберите пространство.');
    }

    if (
        $date === '' ||
        $eventName === '' ||
        $description === '' ||
        $responsibleName === '' ||
        $responsiblePhone === '' ||
        $attendeesCount < 1 ||
        $startTime === '' ||
        $endTime === ''
    ) {
        throw new RuntimeException('Заполните все поля формы.');
    }

    if ($startTime >= $endTime) {
        throw new RuntimeException('Время окончания должно быть позже времени начала.');
    }

    if ($startTime < $openingTime || $endTime > $closingTime) {
        throw new RuntimeException('Бронь должна быть внутри рабочего времени площадки.');
    }

    if (booking_conflicts($spaceId, $date, $startTime, $endTime, $ignoreId)) {
        throw new RuntimeException('На ' . (new DateTimeImmutable($date))->format('d.m.Y') . ' в это время уже есть бронь. Выберите другой интервал или исключите эту дату.');
    }
}

function booking_conflicts(int $spaceId, string $date, string $startTime, string $endTime, ?int $ignoreId = null): bool
{
    $sql = 'SELECT COUNT(*) FROM bookings
            WHERE space_id = :space_id
              AND booking_date = :booking_date
              AND start_time < :end_time
              AND end_time > :start_time';

    $params = [
        'space_id' => $spaceId,
        'booking_date' => $date,
        'start_time' => $startTime,
        'end_time' => $endTime,
    ];

    if ($ignoreId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $ignoreId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() > 0;
}

function create_booking(
    int $spaceId,
    string $date,
    string $eventName,
    string $description,
    string $responsibleName,
    string $responsiblePhone,
    int $attendeesCount,
    string $startTime,
    string $endTime
): void
{
    $stmt = db()->prepare(
        'INSERT INTO bookings (
            space_id,
            booking_date,
            event_name,
            description,
            responsible_name,
            responsible_phone,
            attendees_count,
            start_time,
            end_time,
            created_at
         ) VALUES (
            :space_id,
            :booking_date,
            :event_name,
            :description,
            :responsible_name,
            :responsible_phone,
            :attendees_count,
            :start_time,
            :end_time,
            NOW()
         )'
    );
    $stmt->execute([
        'space_id' => $spaceId,
        'booking_date' => $date,
        'event_name' => $eventName,
        'description' => $description,
        'responsible_name' => $responsibleName,
        'responsible_phone' => $responsiblePhone,
        'attendees_count' => $attendeesCount,
        'start_time' => $startTime,
        'end_time' => $endTime,
    ]);
}

function create_multi_day_bookings_same_time(
    int $spaceId,
    array $dates,
    string $eventName,
    string $description,
    string $responsibleName,
    string $responsiblePhone,
    int $attendeesCount,
    string $startTime,
    string $endTime,
    string $openingTime,
    string $closingTime
): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        foreach ($dates as $date) {
            validate_booking_data(
                $spaceId,
                $date,
                $eventName,
                $description,
                $responsibleName,
                $responsiblePhone,
                $attendeesCount,
                $startTime,
                $endTime,
                $openingTime,
                $closingTime
            );
        }

        foreach ($dates as $date) {
            create_booking(
                $spaceId,
                $date,
                $eventName,
                $description,
                $responsibleName,
                $responsiblePhone,
                $attendeesCount,
                $startTime,
                $endTime
            );
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function create_multi_day_bookings_separate_time(
    int $spaceId,
    array $dates,
    string $eventName,
    string $description,
    string $responsibleName,
    string $responsiblePhone,
    int $attendeesCount,
    array $dailyStartTimes,
    array $dailyEndTimes,
    string $openingTime,
    string $closingTime
): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        foreach ($dates as $date) {
            $startTime = trim((string) ($dailyStartTimes[$date] ?? ''));
            $endTime = trim((string) ($dailyEndTimes[$date] ?? ''));

            validate_booking_data(
                $spaceId,
                $date,
                $eventName,
                $description,
                $responsibleName,
                $responsiblePhone,
                $attendeesCount,
                $startTime,
                $endTime,
                $openingTime,
                $closingTime
            );
        }

        foreach ($dates as $date) {
            create_booking(
                $spaceId,
                $date,
                $eventName,
                $description,
                $responsibleName,
                $responsiblePhone,
                $attendeesCount,
                trim((string) ($dailyStartTimes[$date] ?? '')),
                trim((string) ($dailyEndTimes[$date] ?? ''))
            );
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function update_booking(
    int $id,
    int $spaceId,
    string $date,
    string $eventName,
    string $description,
    string $responsibleName,
    string $responsiblePhone,
    int $attendeesCount,
    string $startTime,
    string $endTime
): void
{
    $stmt = db()->prepare(
        'UPDATE bookings
         SET space_id = :space_id,
             booking_date = :booking_date,
             event_name = :event_name,
             description = :description,
             responsible_name = :responsible_name,
             responsible_phone = :responsible_phone,
             attendees_count = :attendees_count,
             start_time = :start_time,
             end_time = :end_time
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'space_id' => $spaceId,
        'booking_date' => $date,
        'event_name' => $eventName,
        'description' => $description,
        'responsible_name' => $responsibleName,
        'responsible_phone' => $responsiblePhone,
        'attendees_count' => $attendeesCount,
        'start_time' => $startTime,
        'end_time' => $endTime,
    ]);
}

function get_booking_by_id(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, space_id, booking_date, event_name, description, responsible_name, responsible_phone, attendees_count, start_time, end_time, created_at
         FROM bookings
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $booking = $stmt->fetch();

    return $booking === false ? null : $booking;
}

function delete_booking(int $id): void
{
    $stmt = db()->prepare('DELETE FROM bookings WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function get_month_bounds(int $year, int $month): array
{
    $firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    $lastDay = $firstDay->modify('last day of this month');

    return [$firstDay, $lastDay];
}

function get_bookings_for_range(DateTimeImmutable $start, DateTimeImmutable $end, ?int $spaceId = null): array
{
    $sql = 'SELECT id, space_id, booking_date, event_name, description, responsible_name, responsible_phone, attendees_count, start_time, end_time, created_at
            FROM bookings
            WHERE booking_date BETWEEN :start_date AND :end_date';
    $params = [
        'start_date' => $start->format('Y-m-d'),
        'end_date' => $end->format('Y-m-d'),
    ];

    if ($spaceId !== null) {
        $sql .= ' AND space_id = :space_id';
        $params['space_id'] = $spaceId;
    }

    $sql .= ' ORDER BY booking_date DESC, start_time DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function get_bookings_for_date(string $date, ?int $spaceId = null): array
{
    $sql = 'SELECT id, space_id, booking_date, event_name, description, responsible_name, responsible_phone, attendees_count, start_time, end_time, created_at
            FROM bookings
            WHERE booking_date = :booking_date';
    $params = [
        'booking_date' => $date,
    ];

    if ($spaceId !== null) {
        $sql .= ' AND space_id = :space_id';
        $params['space_id'] = $spaceId;
    }

    $sql .= ' ORDER BY start_time ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function get_admin_bookings_filtered(array $filters = []): array
{
    $sql = 'SELECT id, space_id, booking_date, event_name, description, responsible_name, responsible_phone, attendees_count, start_time, end_time, created_at
            FROM bookings
            WHERE 1=1';
    $params = [];

    if (($filters['date_from'] ?? '') !== '') {
        $sql .= ' AND booking_date >= :date_from';
        $params['date_from'] = $filters['date_from'];
    }

    if (($filters['date_to'] ?? '') !== '') {
        $sql .= ' AND booking_date <= :date_to';
        $params['date_to'] = $filters['date_to'];
    }

    if (($filters['space_id'] ?? 0) > 0) {
        $sql .= ' AND space_id = :space_id';
        $params['space_id'] = (int) $filters['space_id'];
    }

    if (($filters['attendees_count_from'] ?? 0) > 0) {
        $sql .= ' AND attendees_count >= :attendees_count_from';
        $params['attendees_count_from'] = (int) $filters['attendees_count_from'];
    }

    if (($filters['attendees_count_to'] ?? 0) > 0) {
        $sql .= ' AND attendees_count <= :attendees_count_to';
        $params['attendees_count_to'] = (int) $filters['attendees_count_to'];
    }

    $status = (string) ($filters['status'] ?? '');

    if ($status === 'today') {
        $sql .= ' AND booking_date = :today_date';
        $params['today_date'] = date('Y-m-d');
    } elseif ($status === 'past') {
        $sql .= ' AND booking_date < :today_date';
        $params['today_date'] = date('Y-m-d');
    } elseif ($status === 'upcoming') {
        $sql .= ' AND booking_date > :today_date';
        $params['today_date'] = date('Y-m-d');
    }

    $sql .= ' ORDER BY booking_date ASC, start_time ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function group_bookings_by_date(array $bookings): array
{
    $grouped = [];

    foreach ($bookings as $booking) {
        $grouped[$booking['booking_date']][] = $booking;
    }

    return $grouped;
}

function normalize_time_for_compare(string $time): string
{
    $time = trim($time);

    if ($time === '') {
        return '';
    }

    return substr($time, 0, 5);
}

function date_is_occupied(array $dayBookings, string $openingTime, string $closingTime): bool
{
    $openingTime = normalize_time_for_compare($openingTime);
    $closingTime = normalize_time_for_compare($closingTime);

    foreach ($dayBookings as $booking) {
        $startTime = normalize_time_for_compare((string) ($booking['start_time'] ?? ''));
        $endTime = normalize_time_for_compare((string) ($booking['end_time'] ?? ''));

        if ($startTime <= $openingTime && $endTime >= $closingTime) {
            return true;
        }
    }

    return false;
}

function month_label(DateTimeImmutable $date): string
{
    $months = [
        1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
        5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
        9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
    ];

    return $months[(int) $date->format('n')] . ' ' . $date->format('Y');
}

function weekday_labels(): array
{
    return ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
}
