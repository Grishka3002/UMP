<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

app_config();

if (is_admin_logged_in()) {
    redirect('/admin/dashboard.php');
}

$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (login_admin($username, $password)) {
        redirect('/admin/dashboard.php');
    }

    $flash = ['type' => 'error', 'message' => 'Неверный логин или пароль.'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в админку</title>
    <link rel="stylesheet" href="/assets/styles.css">
</head>
<body>
<div class="shell" style="max-width: 560px;">
    <section class="card admin-card">
        <h1>Вход в админку</h1>
        <p class="muted">Введите логин и пароль администратора из файла config.php.</p>

        <?php if ($flash !== null): ?>
            <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="field">
                <label for="username">Логин</label>
                <input id="username" name="username" type="text" required>
            </div>

            <div class="field">
                <label for="password">Пароль</label>
                <input id="password" name="password" type="password" required>
            </div>

            <button type="submit">Войти</button>
        </form>
    </section>
</div>
</body>
</html>
