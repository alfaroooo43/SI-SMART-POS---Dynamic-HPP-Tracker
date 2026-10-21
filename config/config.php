<?php
declare(strict_types=1);

return [
    'app_name' => 'SI SMART UMKM',
    'timezone' => 'Asia/Jakarta',
    'database' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'si_smart_pos',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
];
