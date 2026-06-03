<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$config = app_config();
$adminPassword = (string) ($config['admin']['password'] ?? '');
$message = '';
$messageType = '';

function sql_statements(string $sql): array
{
    $statements = [];
    $current = '';
    $length = strlen($sql);
    $quote = null;
    $escaped = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($quote === null && $char === '-' && $next === '-') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if ($quote === null && $char === '#') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        if ($quote === null && $char === '/' && $next === '*') {
            $i += 2;
            while ($i + 1 < $length && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                $i++;
            }
            $i++;
            continue;
        }

        if ($quote !== null) {
            $current .= $char;

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === $quote) {
                $quote = null;
            }

            continue;
        }

        if ($char === '\'' || $char === '"') {
            $quote = $char;
            $current .= $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($current);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $current = '';
            continue;
        }

        $current .= $char;
    }

    $statement = trim($current);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

function table_exists(string $table): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );
    $stmt->execute(['table_name' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $password = (string) ($_POST['admin_password'] ?? '');

        if ($adminPassword === '' || $adminPassword === 'change-me' || !hash_equals($adminPassword, $password)) {
            throw new RuntimeException('Неверный пароль администратора.');
        }

        if (table_exists('settings') || table_exists('bookings')) {
            throw new RuntimeException('Таблицы уже есть. Импорт остановлен, чтобы случайно не перезаписать записи.');
        }

        if (!isset($_FILES['sql_file']) || ($_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Выберите SQL-файл для импорта.');
        }

        $sql = file_get_contents((string) $_FILES['sql_file']['tmp_name']);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('SQL-файл пустой или не читается.');
        }

        $pdo = db();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach (sql_statements($sql) as $statement) {
            $pdo->exec($statement);
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $message = 'Импорт завершён. Теперь откройте главную страницу сайта.';
        $messageType = 'success';
    } catch (Throwable $exception) {
        $message = $exception->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Импорт базы</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<div class="shell" style="max-width: 720px;">
    <section class="card admin-card">
        <h1>Импорт базы</h1>
        <p class="muted">Выберите экспортированный файл database.sql. Импорт запустится только если таблиц ещё нет.</p>

        <?php if ($message !== ''): ?>
            <div class="flash <?= e($messageType) ?>"><?= e($message) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="field">
                <label for="admin_password">Пароль администратора</label>
                <input id="admin_password" name="admin_password" type="password" required>
            </div>

            <div class="field">
                <label for="sql_file">SQL-файл</label>
                <input id="sql_file" name="sql_file" type="file" accept=".sql" required>
            </div>

            <button type="submit">Импортировать</button>
        </form>
    </section>
</div>
</body>
</html>
