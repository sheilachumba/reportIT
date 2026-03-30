<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'ReportIT',
        'base_url' => '',
        'timezone' => 'Africa/Nairobi',
        'campuses' => ['HQ', 'Nairobi', 'Embu', 'Matuga', 'Mombasa', 'Baringo'],
        'admin_emails' => ['admin@ksg.ac.ke'],
    ],
    'db' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'reportit',
        'username' => 'root',
        'password' => '123456',
        'charset' => 'utf8mb4',
    ],
    'uploads' => [
        'max_bytes' => 10 * 1024 * 1024,
        'allowed_mime' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
        ],
        'base_path' => __DIR__ . '/../storage/uploads',
    ],
    'notifications' => [
        'critical_recipients' => ['admin@ksg.ac.ke'],
        'from' => 'no-reply@ksg.ac.ke',
    ],
];
