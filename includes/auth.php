<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function is_admin_logged_in(): bool
{
    start_session_once();

    return ($_SESSION['admin_logged_in'] ?? false) === true;
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        redirect('/admin/login.php');
    }
}

function login_admin(string $username, string $password): bool
{
    $config = app_config()['admin'];

    if ($username !== $config['username']) {
        return false;
    }

    $validPassword = false;

    if (isset($config['password_hash'])) {
        $validPassword = password_verify($password, $config['password_hash']);
    } elseif (isset($config['password'])) {
        $validPassword = hash_equals((string) $config['password'], $password);
    }

    if (!$validPassword) {
        return false;
    }

    start_session_once();
    $_SESSION['admin_logged_in'] = true;

    return true;
}

function logout_admin(): void
{
    start_session_once();
    unset($_SESSION['admin_logged_in']);
}
