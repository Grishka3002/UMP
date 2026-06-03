<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

app_config();

if (is_admin_logged_in()) {
    redirect('/admin/dashboard.php');
}

redirect('/admin/login.php');
