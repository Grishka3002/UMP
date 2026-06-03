<?php

declare(strict_types=1);

return [
    'db' => [
        'host' => getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '',
        'port' => (int) (getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: 3306),
        'name' => getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: '',
        'user' => getenv('MYSQLUSER') ?: getenv('DB_USER') ?: '',
        'pass' => getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
    'admin' => [
        'username' => getenv('ADMIN_USERNAME') ?: 'admin',
        'password' => getenv('ADMIN_PASSWORD') ?: 'change-me',
    ],
    'app' => [
        'name' => 'BronUMP',
        'url' => getenv('APP_URL') ?: '',
        'timezone' => 'Asia/Vladivostok',
    ],
];
