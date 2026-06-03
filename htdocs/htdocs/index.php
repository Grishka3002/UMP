<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

app_config();
start_session_once();

$settings = get_settings();
$spaces = get_spaces($settings);
$visibleSpaces = get_public_spaces($settings);
$spaceImages = get_space_images($settings);
$flash = get_flash();

$month = isset($_GET['month']) ? max(1, min(12, (int) $_GET['month'])) : (int) date('n');
$year = isset($_GET['year']) ? max(2024, min(2100, (int) $_GET['year'])) : (int) date('Y');
$spaceId = resolve_public_space_id(normalize_space_id((int) ($_GET['space_id'] ?? 1)), $visibleSpaces);
$selectedDate = isset($_GET['selected_date']) ? trim((string) $_GET['selected_date']) : date('Y-m-d');
$today = date('Y-m-d');
$formDefaultDate = $selectedDate >= $today ? $selectedDate : $today;
$selectedSpaceName = $visibleSpaces[$spaceId] ?? ($spaces[$spaceId] ?? 'Пространство');
$selectedSpaceSchedule = get_space_schedule($settings, $spaceId);

if ($visibleSpaces === []) {
    $selectedSpaceName = 'Нет опубликованных пространств';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $spaceId = resolve_public_space_id(normalize_space_id((int) ($_POST['space_id'] ?? 1)), $visibleSpaces);
    $selectedSpaceName = $visibleSpaces[$spaceId] ?? ($spaces[$spaceId] ?? 'Пространство');
    $selectedSpaceSchedule = get_space_schedule($settings, $spaceId);

    $date = trim((string) ($_POST['booking_date'] ?? ''));
    $eventName = mb_substr(trim((string) ($_POST['event_name'] ?? '')), 0, 120);
    $description = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 1000);
    $responsibleName = mb_substr(trim((string) ($_POST['responsible_name'] ?? '')), 0, 150);
    $responsiblePhone = mb_substr(trim((string) ($_POST['responsible_phone'] ?? '')), 0, 40);
    $attendeesCount = max(0, (int) ($_POST['attendees_count'] ?? 0));
    $startTime = trim((string) ($_POST['start_time'] ?? ''));
    $endTime = trim((string) ($_POST['end_time'] ?? ''));
    $isMultiDay = isset($_POST['is_multi_day']);
    $multiStartDate = trim((string) ($_POST['multi_start_date'] ?? ''));
    $multiEndDate = trim((string) ($_POST['multi_end_date'] ?? ''));
    $multiTimeMode = trim((string) ($_POST['multi_time_mode'] ?? 'same'));
    $dailyStartTimes = is_array($_POST['daily_start_time'] ?? null) ? $_POST['daily_start_time'] : [];
    $dailyEndTimes = is_array($_POST['daily_end_time'] ?? null) ? $_POST['daily_end_time'] : [];

    try {
        if ($visibleSpaces === []) {
            throw new RuntimeException('Сейчас нет доступных для бронирования пространств.');
        }

        if ($isMultiDay) {
            $dates = iterate_dates_in_range($multiStartDate, $multiEndDate);

            if ($multiTimeMode === 'separate') {
                create_multi_day_bookings_separate_time(
                    $spaceId,
                    $dates,
                    $eventName,
                    $description,
                    $responsibleName,
                    $responsiblePhone,
                    $attendeesCount,
                    $dailyStartTimes,
                    $dailyEndTimes,
                    $selectedSpaceSchedule['opening_time'],
                    $selectedSpaceSchedule['closing_time']
                );
            } else {
                validate_booking_data(
                    $spaceId,
                    $dates[0],
                    $eventName,
                    $description,
                    $responsibleName,
                    $responsiblePhone,
                    $attendeesCount,
                    $startTime,
                    $endTime,
                    $selectedSpaceSchedule['opening_time'],
                    $selectedSpaceSchedule['closing_time']
                );

                create_multi_day_bookings_same_time(
                    $spaceId,
                    $dates,
                    $eventName,
                    $description,
                    $responsibleName,
                    $responsiblePhone,
                    $attendeesCount,
                    $startTime,
                    $endTime,
                    $selectedSpaceSchedule['opening_time'],
                    $selectedSpaceSchedule['closing_time']
                );
            }

            $selectedDate = $multiStartDate;
        } else {
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
                $selectedSpaceSchedule['opening_time'],
                $selectedSpaceSchedule['closing_time']
            );

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

            $selectedDate = $date;
        }

        set_flash('success', 'Бронирование сохранено.');
        $redirectDate = new DateTimeImmutable($selectedDate);
        redirect('/?space_id=' . $spaceId . '&month=' . $redirectDate->format('n') . '&year=' . $redirectDate->format('Y') . '&selected_date=' . $redirectDate->format('Y-m-d'));
    } catch (Throwable $exception) {
        $flash = ['type' => 'error', 'message' => $exception->getMessage()];
    }
}

[$firstDay, $lastDay] = get_month_bounds($year, $month);
$bookings = $visibleSpaces === [] ? [] : get_bookings_for_range($firstDay, $lastDay, $spaceId);
$bookingsByDate = group_bookings_by_date($bookings);
$selectedDateObject = DateTimeImmutable::createFromFormat('Y-m-d', $selectedDate) ?: new DateTimeImmutable($today);
$selectedDate = $selectedDateObject->format('Y-m-d');
$selectedDateBookings = $visibleSpaces === [] ? [] : get_bookings_for_date($selectedDate, $spaceId);
$isTodaySelected = $selectedDate === $today;

$gridStart = $firstDay->modify('-' . ((int) $firstDay->format('N') - 1) . ' days');
$gridEnd = $lastDay->modify('+' . (7 - (int) $lastDay->format('N')) . ' days');
$prevMonth = $firstDay->modify('-1 month');
$nextMonth = $firstDay->modify('+1 month');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Бронирование пространств ВВГУ</title>
    <link rel="icon" type="image/png" href="/logo.png">
    <link rel="shortcut icon" href="/logo.png">
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<div class="shell">
    <section class="hero">
        <article class="card hero-copy">
            <span class="eyebrow">Календарь пространств</span>
            <h1>Бронирование пространств ВВГУ</h1>
            <p class="lead">
                Даты в календаре отмечаются как занятые, если бронь перекрывает весь рабочий день:
                с <strong><?= e($selectedSpaceSchedule['opening_time']) ?></strong> до <strong><?= e($selectedSpaceSchedule['closing_time']) ?></strong>.
            </p>
            <div class="pill-row">
                <span class="pill">Пространство: <?= e($selectedSpaceName) ?></span>
                <span class="pill">Открытие: <?= e($selectedSpaceSchedule['opening_time']) ?></span>
                <span class="pill">Закрытие: <?= e($selectedSpaceSchedule['closing_time']) ?></span>
            </div>
            <?php if (trim((string) $selectedSpaceSchedule['description']) !== ''): ?>
                <div class="lead space-description"><?= nl2br(e($selectedSpaceSchedule['description'])) ?></div>
            <?php endif; ?>
            <?php if (($spaceImages[$spaceId] ?? '') !== '' && $visibleSpaces !== []): ?>
                <div class="space-photo">
                    <img src="<?= e($spaceImages[$spaceId]) ?>" alt="<?= e($selectedSpaceName) ?>">
                </div>
            <?php endif; ?>
        </article>

        <aside class="card form-card">
            <h2>Новая бронь</h2>
            <p class="muted">Укажите дату, название мероприятия и временной интервал.</p>

            <?php if (trim((string) ($settings['booking_contact_name'] ?? '')) !== '' || trim((string) ($settings['booking_contact_phone'] ?? '')) !== ''): ?>
                <div class="booking-help">
                    <strong>Для изменений в зарегистрированном бронировании необходимо обратиться к сотруднику Управления молодежной политики.</strong>
                    <?php if (trim((string) ($settings['booking_contact_name'] ?? '')) !== ''): ?>
                        <div>ФИО: <?= e((string) $settings['booking_contact_name']) ?></div>
                    <?php endif; ?>
                    <?php if (trim((string) ($settings['booking_contact_phone'] ?? '')) !== ''): ?>
                        <div>Телефон: <?= e((string) $settings['booking_contact_phone']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($flash !== null): ?>
                <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($visibleSpaces === []): ?>
                <div class="flash error">Все пространства сейчас скрыты. Сделайте хотя бы одно пространство видимым в админке.</div>
            <?php else: ?>
                <form method="post" accept-charset="UTF-8" id="booking-form">
                    <div class="field">
                        <label for="space_id">Пространство</label>
                        <select id="space_id" name="space_id" required>
                            <?php foreach ($visibleSpaces as $id => $spaceName): ?>
                                <option value="<?= e((string) $id) ?>" <?= $spaceId === $id ? 'selected' : '' ?>><?= e($spaceName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <label class="checkbox-row">
                        <input id="is_multi_day" name="is_multi_day" type="checkbox" value="1">
                        <span>Мероприятие длится несколько дней</span>
                    </label>

                    <div class="field js-single-day-field">
                        <label for="booking_date">Дата</label>
                        <input id="booking_date" name="booking_date" type="date" min="<?= e($today) ?>" value="<?= e($formDefaultDate) ?>" required>
                    </div>

                    <div class="multi-day-panel is-hidden" id="multi-day-panel">
                        <div class="grid-two">
                            <div class="field">
                                <label for="multi_start_date">Дата начала</label>
                                <input id="multi_start_date" name="multi_start_date" type="date" min="<?= e($today) ?>" value="<?= e($formDefaultDate) ?>">
                            </div>

                            <div class="field">
                                <label for="multi_end_date">Дата окончания</label>
                                <input id="multi_end_date" name="multi_end_date" type="date" min="<?= e($today) ?>" value="<?= e($formDefaultDate) ?>">
                            </div>
                        </div>

                        <div class="field">
                            <label for="multi_time_mode">Время на даты</label>
                            <select id="multi_time_mode" name="multi_time_mode">
                                <option value="same">Одно и то же время для всех дней</option>
                                <option value="separate">Отдельное время для каждого дня</option>
                            </select>
                        </div>

                        <div class="daily-times-panel is-hidden" id="daily-times-panel">
                            <div class="field">
                                <label>Время по дням</label>
                                <div class="daily-time-list" id="daily-time-list"></div>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="event_name">Название мероприятия</label>
                        <input id="event_name" name="event_name" type="text" maxlength="120" placeholder="Например, Лекторий" required>
                    </div>

                    <div class="field">
                        <label for="description">Краткое описание</label>
                        <input id="description" name="description" type="text" maxlength="1000" placeholder="О чём мероприятие" required>
                    </div>

                    <div class="field">
                        <label for="responsible_name">ФИО ответственного</label>
                        <input id="responsible_name" name="responsible_name" type="text" maxlength="150" placeholder="Иванов Иван Иванович" required>
                    </div>

                    <div class="field">
                        <label for="responsible_phone">Номер телефона ответственного</label>
                        <input id="responsible_phone" name="responsible_phone" type="text" maxlength="40" placeholder="+7 900 123-45-67" required>
                    </div>

                    <div class="field">
                        <label for="attendees_count">Количество человек</label>
                        <input id="attendees_count" name="attendees_count" type="number" min="1" step="1" placeholder="Например, 25" required>
                    </div>

                    <div class="grid-two js-common-time-fields">
                        <div class="field">
                            <label for="start_time">Начало</label>
                            <input id="start_time" name="start_time" type="time" min="<?= e($selectedSpaceSchedule['opening_time']) ?>" max="<?= e($selectedSpaceSchedule['closing_time']) ?>" required>
                        </div>

                        <div class="field">
                            <label for="end_time">Окончание</label>
                            <input id="end_time" name="end_time" type="time" min="<?= e($selectedSpaceSchedule['opening_time']) ?>" max="<?= e($selectedSpaceSchedule['closing_time']) ?>" required>
                        </div>
                    </div>

                    <button type="submit">Сохранить бронирование</button>
                </form>
            <?php endif; ?>
        </aside>
    </section>

    <section class="layout">
        <article class="card calendar-card">
            <div class="calendar-header">
                <div>
                    <h2><?= e(month_label($firstDay)) ?></h2>
                    <p class="muted">Занятость месяца и список событий по датам.</p>
                </div>
                <nav class="calendar-nav">
                    <a class="button secondary" href="/?space_id=<?= e((string) $spaceId) ?>&month=<?= e($prevMonth->format('n')) ?>&year=<?= e($prevMonth->format('Y')) ?>&selected_date=<?= e($selectedDate) ?>">Назад</a>
                    <a class="button" href="/?space_id=<?= e((string) $spaceId) ?>&month=<?= e($nextMonth->format('n')) ?>&year=<?= e($nextMonth->format('Y')) ?>&selected_date=<?= e($selectedDate) ?>">Вперёд</a>
                </nav>
            </div>

            <div class="legend">
                <span class="legend-item"><span class="legend-dot free"></span>Свободно</span>
                <span class="legend-item"><span class="legend-dot partial"></span>Есть события</span>
                <span class="legend-item"><span class="legend-dot occupied"></span>Занято весь день</span>
            </div>

            <?php if ($visibleSpaces !== []): ?>
                <div class="space-switcher">
                    <?php foreach ($visibleSpaces as $id => $spaceName): ?>
                        <a class="space-tab <?= $spaceId === $id ? 'active' : '' ?>" href="/?space_id=<?= e((string) $id) ?>&month=<?= e((string) $month) ?>&year=<?= e((string) $year) ?>&selected_date=<?= e($selectedDate) ?>"><?= e($spaceName) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($visibleSpaces === []): ?>
                <p class="muted">Сейчас для пользователей не опубликовано ни одного пространства.</p>
            <?php else: ?>
                <div class="calendar-grid">
                    <?php foreach (weekday_labels() as $label): ?>
                        <div class="calendar-weekday"><?= e($label) ?></div>
                    <?php endforeach; ?>

                    <?php for ($day = $gridStart; $day <= $gridEnd; $day = $day->modify('+1 day')): ?>
                        <?php
                        $dateKey = $day->format('Y-m-d');
                        $dayBookings = $bookingsByDate[$dateKey] ?? [];
                        $occupied = date_is_occupied($dayBookings, $selectedSpaceSchedule['opening_time'], $selectedSpaceSchedule['closing_time']);
                        $classes = ['calendar-day'];
                        $statusLabel = 'Свободно';

                        if ($day->format('m') !== $firstDay->format('m')) {
                            $classes[] = 'muted';
                        }

                        if ($dateKey === $selectedDate) {
                            $classes[] = 'selected';
                        }

                        if ($occupied) {
                            $classes[] = 'occupied';
                            $statusLabel = 'Занято';
                        } elseif ($dayBookings !== []) {
                            $classes[] = 'partial';
                            $statusLabel = 'Есть событие';
                        } else {
                            $classes[] = 'free';
                        }
                        ?>
                        <div
                            class="<?= e(implode(' ', $classes)) ?>"
                            onclick="window.location.href='/?space_id=<?= e((string) $spaceId) ?>&month=<?= e((string) $month) ?>&year=<?= e((string) $year) ?>&selected_date=<?= e($dateKey) ?>'"
                            role="link"
                            tabindex="0"
                            onkeydown="if(event.key==='Enter'||event.key===' '){window.location.href='/?space_id=<?= e((string) $spaceId) ?>&month=<?= e((string) $month) ?>&year=<?= e((string) $year) ?>&selected_date=<?= e($dateKey) ?>';}"
                        >
                            <div class="day-head">
                                <div class="date"><?= e($day->format('j')) ?></div>
                                <?php if ($dateKey === $today): ?>
                                    <span class="today-badge">Сегодня</span>
                                <?php endif; ?>
                            </div>
                            <div class="status status-main"><?= e($statusLabel) ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </article>

        <aside class="card form-card">
            <h2><?= $isTodaySelected ? 'События сегодня' : 'События на дату' ?></h2>
            <p class="muted">
                <?= $isTodaySelected
                    ? 'Ниже показываются события на сегодняшнюю дату.'
                    : 'Ниже показываются события на выбранную дату из календаря.' ?>
            </p>
            <div class="selected-date-badge"><?= e($selectedDateObject->format('d.m.Y')) ?></div>

            <?php if ($selectedDateBookings === []): ?>
                <p class="muted">На эту дату событий нет.</p>
            <?php else: ?>
                <ul class="bookings-list">
                    <?php foreach ($selectedDateBookings as $booking): ?>
                        <li class="booking-item">
                            <div class="booking-date"><?= e((new DateTimeImmutable($booking['booking_date']))->format('d.m.Y')) ?></div>
                            <div class="booking-meta"><?= e($booking['start_time']) ?>-<?= e($booking['end_time']) ?></div>
                            <strong class="booking-name"><?= e(display_event_name($booking['event_name'])) ?></strong>
                            <div class="booking-description"><?= e(display_optional_text($booking['description'])) ?></div>
                            <div class="booking-responsible">Ответственный: <?= e(display_optional_text($booking['responsible_name'])) ?></div>
                            <div class="booking-responsible">Количество человек: <?= e((string) ((int) ($booking['attendees_count'] ?? 0))) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    </section>
</div>

<script>
(() => {
    const form = document.getElementById('booking-form');
    if (!form) {
        return;
    }

    const multiCheckbox = document.getElementById('is_multi_day');
    const singleDayField = form.querySelector('.js-single-day-field');
    const multiPanel = document.getElementById('multi-day-panel');
    const multiStartDate = document.getElementById('multi_start_date');
    const multiEndDate = document.getElementById('multi_end_date');
    const multiTimeMode = document.getElementById('multi_time_mode');
    const dailyTimesPanel = document.getElementById('daily-times-panel');
    const dailyTimeList = document.getElementById('daily-time-list');
    const commonTimeFields = form.querySelector('.js-common-time-fields');
    const spaceSelect = document.getElementById('space_id');
    const bookingDate = document.getElementById('booking_date');
    const startTime = document.getElementById('start_time');
    const endTime = document.getElementById('end_time');
    const openingTime = <?= json_encode($selectedSpaceSchedule['opening_time'], JSON_UNESCAPED_UNICODE) ?>;
    const closingTime = <?= json_encode($selectedSpaceSchedule['closing_time'], JSON_UNESCAPED_UNICODE) ?>;
    const currentSpaceId = <?= json_encode((string) $spaceId, JSON_UNESCAPED_UNICODE) ?>;
    const currentMonth = <?= json_encode((string) $month, JSON_UNESCAPED_UNICODE) ?>;
    const currentYear = <?= json_encode((string) $year, JSON_UNESCAPED_UNICODE) ?>;
    const currentSelectedDate = <?= json_encode($selectedDate, JSON_UNESCAPED_UNICODE) ?>;

    function getDatesInRange(start, end) {
        if (!start || !end || start > end) {
            return [];
        }

        const [startYear, startMonth, startDay] = start.split('-').map(Number);
        const [endYear, endMonth, endDay] = end.split('-').map(Number);
        const dates = [];
        const current = new Date(startYear, startMonth - 1, startDay);
        const endDate = new Date(endYear, endMonth - 1, endDay);

        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        };

        while (current <= endDate) {
            dates.push(formatDate(current));
            current.setDate(current.getDate() + 1);
        }

        return dates;
    }

    function renderDailyTimeRows() {
        if (!multiCheckbox.checked || multiTimeMode.value !== 'separate') {
            dailyTimeList.innerHTML = '';
            return;
        }

        const dates = getDatesInRange(multiStartDate.value, multiEndDate.value);
        dailyTimeList.innerHTML = '';

        dates.forEach((date) => {
            const row = document.createElement('div');
            row.className = 'daily-time-row';
            row.innerHTML = `
                <div class="daily-time-date">${date}</div>
                <div class="grid-two">
                    <div class="field">
                        <label>Начало</label>
                        <input type="time" name="daily_start_time[${date}]" min="${openingTime}" max="${closingTime}" required>
                    </div>
                    <div class="field">
                        <label>Окончание</label>
                        <input type="time" name="daily_end_time[${date}]" min="${openingTime}" max="${closingTime}" required>
                    </div>
                </div>
            `;
            dailyTimeList.appendChild(row);
        });
    }

    function syncFormState() {
        const isMulti = multiCheckbox.checked;
        const separate = isMulti && multiTimeMode.value === 'separate';

        singleDayField.classList.toggle('is-hidden', isMulti);
        multiPanel.classList.toggle('is-hidden', !isMulti);
        dailyTimesPanel.classList.toggle('is-hidden', !separate);
        commonTimeFields.classList.toggle('is-hidden', separate);

        bookingDate.required = !isMulti;
        startTime.required = !separate;
        endTime.required = !separate;
        multiStartDate.required = isMulti;
        multiEndDate.required = isMulti;

        renderDailyTimeRows();
    }

    [multiCheckbox, multiStartDate, multiEndDate, multiTimeMode].forEach((element) => {
        element.addEventListener('change', syncFormState);
    });

    if (spaceSelect) {
        spaceSelect.value = currentSpaceId;
        spaceSelect.addEventListener('change', () => {
            const nextSpaceId = spaceSelect.value;
            const params = new URLSearchParams({
                space_id: nextSpaceId,
                month: currentMonth,
                year: currentYear,
                selected_date: currentSelectedDate,
            });

            window.location.href = `/?${params.toString()}`;
        });
    }

    syncFormState();
})();
</script>
</body>
</html>
