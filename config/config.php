<?php
declare(strict_types=1);

return [
    'app_name' => 'SI SMART POS',
    'timezone' => 'Asia/Jakarta',
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'si_smart_pos',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
];
