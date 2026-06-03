<?php

declare(strict_types=1);

$databaseUrl = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: getenv('DB_URL') ?: '';
$databaseUrlConfig = [];

if ($databaseUrl !== '') {
    $databaseParts = parse_url($databaseUrl);

    if (is_array($databaseParts)) {
        $databaseUrlConfig = [
            'host' => $databaseParts['host'] ?? '',
            'port' => isset($databaseParts['port']) ? (int) $databaseParts['port'] : 3306,
            'name' => isset($databaseParts['path']) ? ltrim($databaseParts['path'], '/') : '',
            'user' => isset($databaseParts['user']) ? urldecode((string) $databaseParts['user']) : '',
            'pass' => isset($databaseParts['pass']) ? urldecode((string) $databaseParts['pass']) : '',
        ];
    }
}

return [
    'db' => [
        'host' => getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: ($databaseUrlConfig['host'] ?? ''),
        'port' => (int) (getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: ($databaseUrlConfig['port'] ?? 3306)),
        'name' => getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: ($databaseUrlConfig['name'] ?? ''),
        'user' => getenv('MYSQLUSER') ?: getenv('DB_USER') ?: ($databaseUrlConfig['user'] ?? ''),
        'pass' => getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: ($databaseUrlConfig['pass'] ?? ''),
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
