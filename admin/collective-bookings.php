<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/collective-management.php';
app_config();
require_admin();
$spaces = get_spaces(get_settings());
$flash = get_flash();
$_SESSION['collective_manage_csrf'] ??= bin2hex(random_bytes(32));
$preview = [];
$ready = false;
try {
    ensure_collective_members();
    $ready = true;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['collective_manage_csrf'], $_POST['csrf'])) {
            throw new RuntimeException('Форма устарела. Обновите страницу.');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'confirm') {
            $pending = $_SESSION['collective_delete_pending'] ?? null;
            if (!is_array($pending) || !is_string($_POST['confirmation'] ?? null) || !hash_equals($pending['token'], $_POST['confirmation']) || $pending['expires'] < time()) {
                throw new RuntimeException('Подтверждение устарело. Выберите брони заново.');
            }
            unset($_SESSION['collective_delete_pending']);
            $count = delete_confirmed_collective_rows($pending['rows']);
            set_flash('success', 'Удалено броней: ' . $count . '. Общий календарь обновлён.');
            redirect('/admin/collective-bookings.php');
        } elseif ($action === 'preview') {
            $ids = collective_selected_ids($_POST['booking_ids'] ?? []);
            $preview = collective_deletion_rows($ids);
        } elseif ($action === 'series') {
            $seriesId = $_POST['series_id'] ?? '';
            if (!is_string($seriesId) || !preg_match('/^[a-f0-9]{32}$/', $seriesId)) {
                throw new RuntimeException('Некорректная серия.');
            }
            $stmt = db()->prepare('SELECT b.id FROM bookings b JOIN collective_booking_members m ON m.booking_id = b.id WHERE m.series_id = ? ORDER BY b.id');
            $stmt->execute([$seriesId]);
            $preview = collective_deletion_rows($stmt->fetchAll(PDO::FETCH_COLUMN));
        } else {
            throw new RuntimeException('Неизвестное действие.');
        }
        if ($preview === []) {
            throw new RuntimeException('Выбранные брони уже удалены.');
        }
        $_SESSION['collective_delete_pending'] = ['rows' => $preview, 'token' => bin2hex(random_bytes(32)), 'expires' => time() + 900];
    }
} catch (Throwable $exception) {
    $flash = ['type' => 'error', 'message' => $exception->getMessage()];
}
$filters = [];
foreach (['date_from', 'date_to', 'space_id', 'event_name'] as $key) {
    $filters[$key] = is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
}
$rows = [];
$series = [];
if ($ready) {
    $where = [];
    $params = [];
    foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
        if ($filters[$key] !== '') {
            $where[] = 'b.booking_date ' . $operator . ' ?';
            $params[] = $filters[$key];
        }
    }
    if ((int) $filters['space_id'] > 0) {
        $where[] = 'b.space_id = ?';
        $params[] = (int) $filters['space_id'];
    }
    if ($filters['event_name'] !== '') {
        $where[] = 'b.event_name LIKE ?';
        $params[] = '%' . $filters['event_name'] . '%';
    }
    $stmt = db()->prepare('SELECT b.*, m.series_id FROM bookings b LEFT JOIN collective_booking_members m ON m.booking_id = b.id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY b.booking_date DESC, b.start_time, b.id LIMIT 501');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $series = db()->query('SELECT m.series_id, MIN(b.event_name) AS event_name, MIN(b.booking_date) AS date_from, MAX(b.booking_date) AS date_to, COUNT(*) AS total FROM collective_booking_members m JOIN bookings b ON b.id = m.booking_id GROUP BY m.series_id ORDER BY date_to DESC')->fetchAll();
}
function render_deletion_table(array $rows, array $spaces, bool $selectable): void { ?>
<div class="table-wrap"><table><thead><tr>
<?php if ($selectable): ?><th><input type="checkbox" id="select-all" aria-label="Выбрать все показанные брони"></th><?php endif; ?>
<th>Дата</th><th>Аудитория</th><th>Время</th><th>Мероприятие</th><th>Ответственный</th>
</tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr>
<?php if ($selectable): ?><td><input type="checkbox" name="booking_ids[]" value="<?= (int) $row['id'] ?>" aria-label="Выбрать бронь <?= (int) $row['id'] ?>"></td><?php endif; ?>
<td><?= e($row['booking_date']) ?></td><td><?= e($spaces[(int) $row['space_id']] ?? ('#' . $row['space_id'])) ?></td>
<td><?= e($row['start_time'] . '–' . $row['end_time']) ?></td><td><?= e($row['event_name']) ?></td><td><?= e($row['responsible_name']) ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php } ?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Управление бронями коллективов</title><link rel="stylesheet" href="/assets/styles.css"></head><body><main class="shell">
<div class="topbar"><h1>Управление бронями</h1><div class="pill-row"><a class="button secondary" href="/admin/collectives.php">Расписание коллективов</a><a class="button secondary" href="/admin/dashboard.php">Все брони</a></div></div>
<?php if ($flash): ?><div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($preview): ?>
<section class="card admin-card"><h2>Подтвердите удаление <?= count($preview) ?> броней</h2><p>Перечисленные занятия будут удалены из общего календаря. Это действие нельзя отменить.</p>
<?php render_deletion_table($preview, $spaces, false); ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['collective_manage_csrf']) ?>"><input type="hidden" name="action" value="confirm"><input type="hidden" name="confirmation" value="<?= e($_SESSION['collective_delete_pending']['token']) ?>"><div class="action-row"><button class="danger-button" type="submit">Удалить <?= count($preview) ?> броней</button><a class="button secondary" href="/admin/collective-bookings.php">Отмена</a></div></form></section>
<?php elseif ($ready): ?>
<section class="card admin-card"><h2>Удаление выбранных занятий</h2><p>Здесь доступны все брони, включая старые занятия коллективов. Отфильтруйте список, отметьте нужные строки и проверьте их перед удалением.</p>
<form method="get" class="filter-grid">
<div class="field"><label for="event_name">Название мероприятия</label><input id="event_name" name="event_name" value="<?= e($filters['event_name']) ?>"></div>
<div class="field"><label for="space_id">Аудитория</label><select id="space_id" name="space_id"><option value="">Все</option><?php foreach ($spaces as $id => $name): ?><option value="<?= (int) $id ?>" <?= (string) $id === $filters['space_id'] ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
<?php foreach (['date_from' => 'С даты', 'date_to' => 'По дату'] as $key => $label): ?><div class="field"><label for="<?= $key ?>"><?= $label ?></label><input type="date" id="<?= $key ?>" name="<?= $key ?>" value="<?= e($filters[$key]) ?>"></div><?php endforeach; ?>
<div class="action-row"><button>Найти</button><a href="/admin/collective-bookings.php">Сбросить</a></div></form>
<?php if (count($rows) > 500): ?><p>Показаны первые 500 броней. Уточните фильтр для остальных занятий.</p><?php endif; ?>
<?php if (!$rows): ?><p>Броней по выбранному фильтру нет.</p><?php else: ?>
<form method="post" id="selection-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['collective_manage_csrf']) ?>"><input type="hidden" name="action" value="preview">
<?php render_deletion_table(array_slice($rows, 0, 500), $spaces, true); ?>
<button type="submit">Проверить выбранные перед удалением <span id="selected-count"></span></button></form><?php endif; ?></section>
<section class="card admin-card" style="margin-top:24px"><h2>Удаление всей серии</h2><p>Серии доступны для расписаний, созданных после этого обновления. Здесь перечислены все оставшиеся занятия каждой серии, включая прошедшие; фильтр выше к сериям не применяется. Старые брони выбирайте в таблице выше.</p>
<?php if (!$series): ?><p>Новых серий пока нет.</p><?php endif; ?>
<?php foreach ($series as $item): ?><form method="post" class="multi-day-panel"><p><strong><?= e($item['event_name']) ?></strong> · <?= e($item['date_from']) ?> — <?= e($item['date_to']) ?> · занятий: <?= (int) $item['total'] ?></p><input type="hidden" name="csrf" value="<?= e($_SESSION['collective_manage_csrf']) ?>"><input type="hidden" name="action" value="series"><input type="hidden" name="series_id" value="<?= e($item['series_id']) ?>"><button class="danger-button">Проверить всю серию перед удалением</button></form><?php endforeach; ?></section>
<?php endif; ?>
</main><script>
(() => {
    const form = document.getElementById('selection-form');
    if (!form) return;
    const boxes = [...form.querySelectorAll('input[name="booking_ids[]"]')];
    const all = document.getElementById('select-all');
    const update = () => {
        const count = boxes.filter(box => box.checked).length;
        document.getElementById('selected-count').textContent = '(' + count + ')';
        form.querySelector('button[type="submit"]').disabled = count === 0;
        all.checked = count === boxes.length;
        all.indeterminate = count > 0 && count < boxes.length;
    };
    all.addEventListener('change', () => { boxes.forEach(box => { box.checked = all.checked; }); update(); });
    boxes.forEach(box => box.addEventListener('change', update));
    update();
})();
</script></body></html>
