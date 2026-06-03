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
        'host' => ($databaseUrlConfig['host'] ?? '') ?: getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '',
        'port' => (int) (($databaseUrlConfig['port'] ?? 0) ?: getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: 3306),
        'name' => ($databaseUrlConfig['name'] ?? '') ?: getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: '',
        'user' => ($databaseUrlConfig['user'] ?? '') ?: getenv('MYSQLUSER') ?: getenv('DB_USER') ?: '',
        'pass' => ($databaseUrlConfig['pass'] ?? '') ?: getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '',
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
