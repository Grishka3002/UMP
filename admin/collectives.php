<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/collective-schedule.php';
require_once __DIR__ . '/../includes/collective-management.php';

app_config();
require_admin();
$settings = get_settings();
$spaces = get_spaces($settings);
$flash = get_flash();
$_SESSION['collective_csrf'] ??= bin2hex(random_bytes(32));
$values = array_fill_keys(['space_id', 'date_from', 'date_to', 'exceptions', 'event_name', 'description', 'responsible_name', 'responsible_phone', 'attendees_count', 'start_time', 'end_time'], '');
$weekdays = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $_) {
        $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    $weekdays = is_array($_POST['weekdays'] ?? null) ? array_values(array_filter($_POST['weekdays'], 'is_string')) : [];
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['collective_csrf'], $_POST['csrf'])) {
            throw new RuntimeException('Форма устарела. Обновите страницу и повторите попытку.');
        }
        $dates = collective_schedule_dates($values['date_from'], $values['date_to'], $weekdays, $values['exceptions']);
        $spaceId = (int) $values['space_id'];
        if (!isset($spaces[$spaceId])) {
            throw new RuntimeException('Выберите аудиторию.');
        }
        foreach (['start_time', 'end_time'] as $key) {
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $values[$key])) {
                throw new RuntimeException('Укажите корректное время начала и окончания.');
            }
        }
        foreach (['event_name' => 120, 'description' => 1000, 'responsible_name' => 150, 'responsible_phone' => 40] as $key => $limit) {
            if (mb_strlen($values[$key]) > $limit) {
                throw new RuntimeException('Превышена допустимая длина поля.');
            }
        }
        if (filter_var($values['attendees_count'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new RuntimeException('Укажите положительное целое количество человек.');
        }
        $schedule = get_space_schedule($settings, $spaceId);
        create_collective_bookings($values, $dates, $schedule);
        set_flash('success', 'Расписание добавлено. Создано броней: ' . count($dates) . '. Все занятия отображаются в общем календаре.');
        redirect('/admin/collectives.php');
    } catch (Throwable $exception) {
        $flash = ['type' => 'error', 'message' => $exception->getMessage()];
    }
}
$dayNames = [1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Расписание коллективов</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<div class="shell">
    <div class="topbar">
        <div><h1>Расписание коллективов</h1><p class="muted">Регулярные занятия в любой аудитории.</p></div>
        <div class="pill-row"><a class="button secondary" href="/">На сайт</a><a class="button" href="/admin/logout.php">Выйти</a></div>
    </div>
    <nav class="pill-row" aria-label="Разделы админки">
        <a class="button secondary" href="/admin/dashboard.php">Брони и настройки</a>
        <a class="button" href="/admin/collectives.php" aria-current="page">РАСПИСАНИЕ КОЛЛЕКТИВОВ</a>
    </nav>
    <?php if ($flash !== null): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <p><a class="button secondary" href="/admin/collective-bookings.php">Управление бронями: удалить выбранные или всю серию</a></p>
    <section class="card admin-card" style="margin-top: 24px;">
        <h2>Забронировать занятия</h2>
        <p class="muted">Выберите период включительно и дни недели. Время одинаковое для всех занятий. При пересечении с существующей бронью расписание целиком не сохраняется.</p>
        <form method="post" accept-charset="UTF-8">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['collective_csrf']) ?>">
            <div class="field">
                <label for="space_id">Аудитория</label>
                <select name="space_id" id="space_id" required>
                    <option value="">Выберите аудиторию</option>
                    <?php foreach ($spaces as $id => $name): $hours = get_space_schedule($settings, $id); ?>
                        <option value="<?= $id ?>" <?= $values['space_id'] === (string) $id ? 'selected' : '' ?>><?= e($name . ' (' . $hours['opening_time'] . '–' . $hours['closing_time'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid-two">
                <div class="field"><label for="date_from">Период с</label><input type="date" name="date_from" id="date_from" value="<?= e($values['date_from']) ?>" required></div>
                <div class="field"><label for="date_to">Период по</label><input type="date" name="date_to" id="date_to" value="<?= e($values['date_to']) ?>" required></div>
            </div>
            <p class="muted">Максимальная продолжительность периода — 366 дней.</p>
            <fieldset class="multi-day-panel">
                <legend>Повторять каждую неделю</legend>
                <?php foreach ($dayNames as $id => $name): ?>
                    <label class="checkbox-row"><input type="checkbox" name="weekdays[]" value="<?= $id ?>" <?= in_array((string) $id, $weekdays, true) ? 'checked' : '' ?>><span><?= e($name) ?></span></label>
                <?php endforeach; ?>
            </fieldset>
            <div class="field">
                <label for="exceptions">Кроме дат (необязательно)</label>
                <textarea name="exceptions" id="exceptions" rows="3" placeholder="22.09.2026, 06.10.2026" aria-describedby="exceptions-help"><?= e($values['exceptions']) ?></textarea>
                <p class="muted" id="exceptions-help">Укажите даты через запятую или каждую с новой строки в формате ДД.ММ.ГГГГ.</p>
            </div>
            <?php foreach (['event_name' => ['Название мероприятия / коллектива', 120], 'description' => ['Краткое описание', 1000], 'responsible_name' => ['ФИО ответственного', 150], 'responsible_phone' => ['Телефон ответственного', 40]] as $key => [$label, $limit]): ?>
                <div class="field"><label for="<?= e($key) ?>"><?= e($label) ?></label><input type="text" name="<?= e($key) ?>" id="<?= e($key) ?>" maxlength="<?= $limit ?>" value="<?= e($values[$key]) ?>" required></div>
            <?php endforeach; ?>
            <div class="field"><label for="attendees_count">Количество человек</label><input type="number" min="1" step="1" name="attendees_count" id="attendees_count" value="<?= e($values['attendees_count']) ?>" required></div>
            <div class="grid-two">
                <div class="field"><label for="start_time">Начало</label><input type="time" name="start_time" id="start_time" value="<?= e($values['start_time']) ?>" required></div>
                <div class="field"><label for="end_time">Окончание</label><input type="time" name="end_time" id="end_time" value="<?= e($values['end_time']) ?>" required></div>
            </div>
            <div class="action-row"><button type="submit">Забронировать расписание</button><a class="button secondary" href="/admin/dashboard.php">Все брони</a></div>
            <p class="muted">Каждое занятие появится в общем календаре и списке броней. Изменить или удалить отдельное занятие можно во вкладке «Брони и настройки».</p>
        </form>
    </section>
</div>
</body>
</html>
