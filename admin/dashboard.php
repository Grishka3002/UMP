<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

app_config();
require_admin();

$flash = get_flash();
$settings = get_settings();
$spaces = get_spaces($settings);
$spaceVisibility = get_space_visibility($settings);
$spaceImages = get_space_images($settings);
$spaceOpeningTimes = get_space_opening_times($settings);
$spaceClosingTimes = get_space_closing_times($settings);
$spaceDescriptions = get_space_descriptions($settings);
$editBookingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingBooking = $editBookingId > 0 ? get_booking_by_id($editBookingId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_settings') {
            $spaceSettings = [];
            $spaceSettings['booking_contact_name'] = mb_substr(trim((string) ($_POST['booking_contact_name'] ?? '')), 0, 150);
            $spaceSettings['booking_contact_phone'] = mb_substr(trim((string) ($_POST['booking_contact_phone'] ?? '')), 0, 60);

            for ($space = 1; $space <= 4; $space++) {
                $name = trim((string) ($_POST['space_' . $space . '_name'] ?? ''));
                $openingTime = trim((string) ($_POST['space_' . $space . '_opening_time'] ?? ''));
                $closingTime = trim((string) ($_POST['space_' . $space . '_closing_time'] ?? ''));
                $description = trim((string) ($_POST['space_' . $space . '_description'] ?? ''));
                $visible = isset($_POST['space_' . $space . '_visible']);

                if ($name === '' || $openingTime === '' || $closingTime === '') {
                    throw new RuntimeException('Укажите название и часы работы для каждого пространства.');
                }

                if ($openingTime >= $closingTime) {
                    throw new RuntimeException('В каждом пространстве время открытия должно быть раньше времени закрытия.');
                }

                $spaceSettings['space_' . $space . '_name'] = mb_substr($name, 0, 150);
                $spaceSettings['space_' . $space . '_opening_time'] = $openingTime;
                $spaceSettings['space_' . $space . '_closing_time'] = $closingTime;
                $spaceSettings['space_' . $space . '_description'] = mb_substr($description, 0, 1000);
                $spaceSettings['space_' . $space . '_visible'] = $visible ? '1' : '0';
            }

            save_settings($spaceSettings);

            for ($space = 1; $space <= 4; $space++) {
                $fileKey = 'space_' . $space . '_image';
                $uploadedPath = upload_space_image($space, $_FILES[$fileKey] ?? [], $spaceImages[$space] ?? '');
                if ($uploadedPath !== ($spaceImages[$space] ?? '')) {
                    save_setting_value($fileKey, $uploadedPath);
                }
            }

            set_flash('success', 'Настройки пространств обновлены.');
            redirect('/admin/dashboard.php');
        }

        if ($action === 'update_booking') {
            $bookingId = (int) ($_POST['booking_id'] ?? 0);
            $spaceId = normalize_space_id((int) ($_POST['space_id'] ?? 1));
            $date = trim((string) ($_POST['booking_date'] ?? ''));
            $eventName = mb_substr(trim((string) ($_POST['event_name'] ?? '')), 0, 120);
            $description = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 1000);
            $responsibleName = mb_substr(trim((string) ($_POST['responsible_name'] ?? '')), 0, 150);
            $responsiblePhone = mb_substr(trim((string) ($_POST['responsible_phone'] ?? '')), 0, 40);
            $attendeesCount = max(0, (int) ($_POST['attendees_count'] ?? 0));
            $startTime = trim((string) ($_POST['start_time'] ?? ''));
            $endTime = trim((string) ($_POST['end_time'] ?? ''));

            if (get_booking_by_id($bookingId) === null) {
                throw new RuntimeException('Бронь для редактирования не найдена.');
            }

            $schedule = get_space_schedule($settings, $spaceId);

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
                $schedule['opening_time'],
                $schedule['closing_time'],
                $bookingId
            );

            update_booking(
                $bookingId,
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

            set_flash('success', 'Бронирование обновлено.');
            redirect('/admin/dashboard.php?edit=' . $bookingId);
        }

        if ($action === 'delete_booking') {
            $bookingId = (int) ($_POST['booking_id'] ?? 0);
            delete_booking($bookingId);
            set_flash('success', 'Бронирование удалено.');
            redirect('/admin/dashboard.php');
        }
    } catch (Throwable $exception) {
        $flash = ['type' => 'error', 'message' => $exception->getMessage()];
    }
}

$settings = get_settings();
$spaces = get_spaces($settings);
$spaceVisibility = get_space_visibility($settings);
$spaceImages = get_space_images($settings);
$spaceOpeningTimes = get_space_opening_times($settings);
$spaceClosingTimes = get_space_closing_times($settings);
$spaceDescriptions = get_space_descriptions($settings);
$editingBooking = $editBookingId > 0 ? get_booking_by_id($editBookingId) : null;

$filters = [
    'date_from' => trim((string) ($_GET['filter_date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['filter_date_to'] ?? '')),
    'space_id' => max(0, (int) ($_GET['filter_space_id'] ?? 0)),
    'attendees_count_from' => max(0, (int) ($_GET['filter_attendees_count_from'] ?? 0)),
    'attendees_count_to' => max(0, (int) ($_GET['filter_attendees_count_to'] ?? 0)),
    'status' => trim((string) ($_GET['filter_status'] ?? '')),
];

if (!in_array($filters['status'], ['', 'today', 'past', 'upcoming'], true)) {
    $filters['status'] = '';
}

$month = isset($_GET['month']) ? max(1, min(12, (int) $_GET['month'])) : (int) date('n');
$year = isset($_GET['year']) ? max(2024, min(2100, (int) ($_GET['year'] ?? date('Y')))) : (int) date('Y');
[$statsFirstDay, $statsLastDay] = get_month_bounds($year, $month);
$statsPrevMonth = $statsFirstDay->modify('-1 month');
$statsNextMonth = $statsFirstDay->modify('+1 month');
$statsSpaceId = $filters['space_id'] > 0 ? $filters['space_id'] : null;
$statsBookings = get_bookings_for_range($statsFirstDay, $statsLastDay, $statsSpaceId);
$statsBookingsByDate = group_bookings_by_date($statsBookings);
$occupiedDays = 0;

foreach ($statsBookingsByDate as $dayBookings) {
    $dayOccupied = false;

    foreach ($dayBookings as $booking) {
        $bookingSpaceId = (int) ($booking['space_id'] ?? 0);
        $schedule = get_space_schedule($settings, $bookingSpaceId);

        if (date_is_occupied([$booking], $schedule['opening_time'], $schedule['closing_time'])) {
            $dayOccupied = true;
            break;
        }
    }

    if ($dayOccupied) {
        $occupiedDays++;
    }
}

$partialDays = count($statsBookingsByDate) - $occupiedDays;
$bookings = get_admin_bookings_filtered($filters);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка бронирований</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div>
            <h1>Админка</h1>
            <p class="muted">Управление пространствами, фильтрами и всеми бронями.</p>
        </div>
        <div class="pill-row">
            <a class="button secondary" href="/">На сайт</a>
            <a class="button" href="/admin/logout.php">Выйти</a>
        </div>
    </div>

    <?php if ($flash !== null): ?>
        <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <section class="card admin-card" style="margin-top: 24px;">
        <div class="calendar-header">
            <div>
                <h2><?= e(month_label($statsFirstDay)) ?></h2>
                <p class="muted">
                    Месячная сводка по бронированиям<?= $statsSpaceId !== null ? ': ' . e($spaces[$statsSpaceId] ?? ('Пространство #' . $statsSpaceId)) : ' по всем пространствам' ?>.
                </p>
            </div>
            <nav class="calendar-nav">
                <a class="button secondary" href="/admin/dashboard.php?<?= e(http_build_query([
                    'month' => $statsPrevMonth->format('n'),
                    'year' => $statsPrevMonth->format('Y'),
                    'filter_date_from' => $filters['date_from'],
                    'filter_date_to' => $filters['date_to'],
                    'filter_space_id' => $filters['space_id'] > 0 ? (string) $filters['space_id'] : '',
                    'filter_attendees_count_from' => $filters['attendees_count_from'] > 0 ? (string) $filters['attendees_count_from'] : '',
                    'filter_attendees_count_to' => $filters['attendees_count_to'] > 0 ? (string) $filters['attendees_count_to'] : '',
                    'filter_status' => $filters['status'],
                ])) ?>">Назад</a>
                <a class="button" href="/admin/dashboard.php?<?= e(http_build_query([
                    'month' => $statsNextMonth->format('n'),
                    'year' => $statsNextMonth->format('Y'),
                    'filter_date_from' => $filters['date_from'],
                    'filter_date_to' => $filters['date_to'],
                    'filter_space_id' => $filters['space_id'] > 0 ? (string) $filters['space_id'] : '',
                    'filter_attendees_count_from' => $filters['attendees_count_from'] > 0 ? (string) $filters['attendees_count_from'] : '',
                    'filter_attendees_count_to' => $filters['attendees_count_to'] > 0 ? (string) $filters['attendees_count_to'] : '',
                    'filter_status' => $filters['status'],
                ])) ?>">Вперёд</a>
            </nav>
        </div>

        <div class="hero-stats">
            <div class="stat-tile">
                <span class="stat-value"><?= e((string) $occupiedDays) ?></span>
                <span class="stat-label">Дней занято полностью</span>
            </div>
            <div class="stat-tile">
                <span class="stat-value"><?= e((string) $partialDays) ?></span>
                <span class="stat-label">Дней с событиями</span>
            </div>
            <div class="stat-tile">
                <span class="stat-value"><?= e((string) count($statsBookings)) ?></span>
                <span class="stat-label">Броней в месяце</span>
            </div>
        </div>
    </section>

    <section class="card admin-card" style="margin-top: 24px;">
        <h2>Список броней</h2>
        <p class="muted">Можно фильтровать по периоду, пространству, количеству человек и статусу.</p>

        <form method="get" class="filter-grid">
            <div class="field">
                <label for="filter_date_from">Период с</label>
                <input id="filter_date_from" name="filter_date_from" type="date" value="<?= e($filters['date_from']) ?>">
            </div>

            <div class="field">
                <label for="filter_date_to">Период по</label>
                <input id="filter_date_to" name="filter_date_to" type="date" value="<?= e($filters['date_to']) ?>">
            </div>

            <div class="field">
                <label for="filter_space_id">Пространство</label>
                <select id="filter_space_id" name="filter_space_id">
                    <option value="0">Все пространства</option>
                    <?php foreach ($spaces as $id => $spaceName): ?>
                        <option value="<?= e((string) $id) ?>" <?= $filters['space_id'] === $id ? 'selected' : '' ?>><?= e($spaceName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="filter_attendees_count_from">Количество человек от</label>
                <input id="filter_attendees_count_from" name="filter_attendees_count_from" type="number" min="1" step="1" value="<?= $filters['attendees_count_from'] > 0 ? e((string) $filters['attendees_count_from']) : '' ?>">
            </div>

            <div class="field">
                <label for="filter_attendees_count_to">Количество человек до</label>
                <input id="filter_attendees_count_to" name="filter_attendees_count_to" type="number" min="1" step="1" value="<?= $filters['attendees_count_to'] > 0 ? e((string) $filters['attendees_count_to']) : '' ?>">
            </div>

            <div class="field">
                <label for="filter_status">Статус</label>
                <select id="filter_status" name="filter_status">
                    <option value="" <?= $filters['status'] === '' ? 'selected' : '' ?>>Все</option>
                    <option value="today" <?= $filters['status'] === 'today' ? 'selected' : '' ?>>На сегодня</option>
                    <option value="upcoming" <?= $filters['status'] === 'upcoming' ? 'selected' : '' ?>>Предстоящие</option>
                    <option value="past" <?= $filters['status'] === 'past' ? 'selected' : '' ?>>Прошедшие</option>
                </select>
            </div>

            <div class="action-row">
                <button type="submit">Применить фильтр</button>
                <a class="button secondary" href="/admin/dashboard.php">Сбросить</a>
                <button type="button" class="button secondary" id="open-export-dialog">Скачать за период</button>
            </div>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Пространство</th>
                        <th>Время</th>
                        <th>Мероприятие</th>
                        <th>Описание</th>
                        <th>Ответственный</th>
                        <th>Телефон</th>
                        <th>Людей</th>
                        <th>Создано</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($bookings === []): ?>
                    <tr>
                        <td colspan="10" class="muted">Бронирований по выбранному фильтру нет.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?= e((new DateTimeImmutable($booking['booking_date']))->format('d.m.Y')) ?></td>
                            <td><?= e($spaces[(int) $booking['space_id']] ?? ('Пространство #' . $booking['space_id'])) ?></td>
                            <td><?= e($booking['start_time']) ?>-<?= e($booking['end_time']) ?></td>
                            <td><?= e(display_event_name($booking['event_name'])) ?></td>
                            <td><?= e(display_optional_text($booking['description'])) ?></td>
                            <td><?= e(display_optional_text($booking['responsible_name'])) ?></td>
                            <td><?= e(display_optional_text($booking['responsible_phone'])) ?></td>
                            <td><?= e((string) ((int) ($booking['attendees_count'] ?? 0))) ?></td>
                            <td><?= e((new DateTimeImmutable($booking['created_at']))->format('d.m.Y H:i')) ?></td>
                            <td>
                                <a class="button secondary small-button" href="/admin/dashboard.php?edit=<?= e((string) $booking['id']) ?>">Редактировать</a>
                                <form method="post" class="inline-form" onsubmit="return confirm('Удалить бронирование?');">
                                    <input type="hidden" name="action" value="delete_booking">
                                    <input type="hidden" name="booking_id" value="<?= e((string) $booking['id']) ?>">
                                    <button type="submit" class="danger-button">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <dialog class="export-dialog" id="export-dialog">
        <form method="dialog" class="export-dialog-close">
            <button type="submit" class="dialog-close-button" aria-label="Закрыть">×</button>
        </form>
        <div class="export-dialog-body">
            <h2>Выгрузка CSV</h2>
            <p class="muted">Укажите период дат, за который нужно скачать все мероприятия.</p>

            <form method="get" action="/admin/export.php">
                <div class="grid-two">
                    <div class="field">
                        <label for="export_date_from">Период с</label>
                        <input id="export_date_from" name="date_from" type="date" value="<?= e($filters['date_from']) ?>" required>
                    </div>

                    <div class="field">
                        <label for="export_date_to">Период по</label>
                        <input id="export_date_to" name="date_to" type="date" value="<?= e($filters['date_to']) ?>" required>
                    </div>
                </div>

                <div class="action-row">
                    <button type="submit">Скачать CSV</button>
                    <button type="button" class="button secondary" id="close-export-dialog">Отмена</button>
                </div>
            </form>
        </div>
    </dialog>

    <section class="layout" style="margin-top: 24px;">
        <?php if ($editingBooking !== null): ?>
            <aside class="card admin-card">
                <h2>Редактирование брони</h2>
                <p class="muted">Проверка времени выполняется по графику выбранного пространства.</p>

                <form method="post" accept-charset="UTF-8">
                    <input type="hidden" name="action" value="update_booking">
                    <input type="hidden" name="booking_id" value="<?= e((string) $editingBooking['id']) ?>">

                    <div class="field">
                        <label for="space_id">Пространство</label>
                        <select id="space_id" name="space_id" required>
                            <?php foreach ($spaces as $id => $spaceName): ?>
                                <option value="<?= e((string) $id) ?>" <?= (int) $editingBooking['space_id'] === $id ? 'selected' : '' ?>><?= e($spaceName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="booking_date">Дата</label>
                        <input id="booking_date" name="booking_date" type="date" value="<?= e($editingBooking['booking_date']) ?>" required>
                    </div>

                    <div class="field">
                        <label for="event_name">Название мероприятия</label>
                        <input id="event_name" name="event_name" type="text" maxlength="120" value="<?= e(display_event_name($editingBooking['event_name'])) ?>" required>
                    </div>

                    <div class="field">
                        <label for="description">Краткое описание</label>
                        <input id="description" name="description" type="text" maxlength="1000" value="<?= e(display_optional_text($editingBooking['description'], '')) ?>" required>
                    </div>

                    <div class="field">
                        <label for="responsible_name">ФИО ответственного</label>
                        <input id="responsible_name" name="responsible_name" type="text" maxlength="150" value="<?= e(display_optional_text($editingBooking['responsible_name'], '')) ?>" required>
                    </div>

                    <div class="field">
                        <label for="responsible_phone">Номер телефона ответственного</label>
                        <input id="responsible_phone" name="responsible_phone" type="text" maxlength="40" value="<?= e(display_optional_text($editingBooking['responsible_phone'], '')) ?>" required>
                    </div>

                    <div class="field">
                        <label for="attendees_count">Количество человек</label>
                        <input id="attendees_count" name="attendees_count" type="number" min="1" step="1" value="<?= e((string) ((int) ($editingBooking['attendees_count'] ?? 1))) ?>" required>
                    </div>

                    <div class="grid-two">
                        <div class="field">
                            <label for="start_time">Начало</label>
                            <input id="start_time" name="start_time" type="time" value="<?= e($editingBooking['start_time']) ?>" required>
                        </div>

                        <div class="field">
                            <label for="end_time">Окончание</label>
                            <input id="end_time" name="end_time" type="time" value="<?= e($editingBooking['end_time']) ?>" required>
                        </div>
                    </div>

                    <div class="action-row">
                        <button type="submit">Сохранить изменения</button>
                        <a class="button secondary" href="/admin/dashboard.php">Отмена</a>
                    </div>
                </form>
            </aside>
        <?php endif; ?>

        <article class="card admin-card">
            <h2>Настройки пространств</h2>
            <p class="muted">Для каждого пространства можно отдельно задать название, видимость, описание, часы работы и фото. Здесь же настраиваются контактные данные для изменений брони.</p>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_settings">

                <section class="multi-day-panel" style="margin-bottom: 18px;">
                    <h3 style="margin-bottom: 14px;">Контакты для изменений брони</h3>

                    <div class="field">
                        <label for="booking_contact_name">ФИО сотрудника Управления молодежной политики</label>
                        <input id="booking_contact_name" name="booking_contact_name" type="text" maxlength="150" value="<?= e((string) ($settings['booking_contact_name'] ?? '')) ?>">
                    </div>

                    <div class="field">
                        <label for="booking_contact_phone">Телефон сотрудника</label>
                        <input id="booking_contact_phone" name="booking_contact_phone" type="text" maxlength="60" value="<?= e((string) ($settings['booking_contact_phone'] ?? '')) ?>">
                    </div>
                </section>

                <?php for ($space = 1; $space <= 4; $space++): ?>
                    <section class="multi-day-panel" style="margin-bottom: 18px;">
                        <h3 style="margin-bottom: 14px;">Пространство <?= e((string) $space) ?></h3>

                        <div class="field">
                            <label for="space_<?= e((string) $space) ?>_name">Название пространства</label>
                            <input id="space_<?= e((string) $space) ?>_name" name="space_<?= e((string) $space) ?>_name" type="text" maxlength="150" value="<?= e($spaces[$space]) ?>" required>
                        </div>

                        <label class="checkbox-row admin-checkbox-row">
                            <input id="space_<?= e((string) $space) ?>_visible" name="space_<?= e((string) $space) ?>_visible" type="checkbox" value="1" <?= ($spaceVisibility[$space] ?? true) ? 'checked' : '' ?>>
                            <span>Показывать это пространство пользователям</span>
                        </label>

                        <div class="grid-two">
                            <div class="field">
                                <label for="space_<?= e((string) $space) ?>_opening_time">Открытие</label>
                                <input id="space_<?= e((string) $space) ?>_opening_time" name="space_<?= e((string) $space) ?>_opening_time" type="time" value="<?= e($spaceOpeningTimes[$space]) ?>" required>
                            </div>

                            <div class="field">
                                <label for="space_<?= e((string) $space) ?>_closing_time">Закрытие</label>
                                <input id="space_<?= e((string) $space) ?>_closing_time" name="space_<?= e((string) $space) ?>_closing_time" type="time" value="<?= e($spaceClosingTimes[$space]) ?>" required>
                            </div>
                        </div>

                        <div class="field">
                            <label for="space_<?= e((string) $space) ?>_description">Описание пространства</label>
                            <textarea id="space_<?= e((string) $space) ?>_description" name="space_<?= e((string) $space) ?>_description" maxlength="1000" rows="5" placeholder="Короткое описание пространства"><?= e($spaceDescriptions[$space]) ?></textarea>
                        </div>

                        <div class="field">
                            <label for="space_<?= e((string) $space) ?>_image">Фото пространства</label>
                            <?php if (($spaceImages[$space] ?? '') !== ''): ?>
                                <div class="admin-image-preview">
                                    <img src="<?= e($spaceImages[$space]) ?>" alt="<?= e($spaces[$space]) ?>">
                                </div>
                            <?php endif; ?>
                            <input id="space_<?= e((string) $space) ?>_image" name="space_<?= e((string) $space) ?>_image" type="file" accept=".webp,.jpg,.jpeg,.png">
                        </div>
                    </section>
                <?php endfor; ?>

                <button type="submit">Сохранить настройки</button>
            </form>
        </article>
    </section>
</div>
<script>
(() => {
    const dialog = document.getElementById('export-dialog');
    const openButton = document.getElementById('open-export-dialog');
    const closeButton = document.getElementById('close-export-dialog');

    if (!dialog || !openButton || !closeButton) {
        return;
    }

    openButton.addEventListener('click', () => {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
            return;
        }

        dialog.setAttribute('open', 'open');
    });

    closeButton.addEventListener('click', () => {
        dialog.close();
    });
})();
</script>
</body>
</html>
