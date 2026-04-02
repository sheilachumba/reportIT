<?php

declare(strict_types=1);

$localOverrides = [];
$localPath = __DIR__ . '/local.php';
if (is_file($localPath)) {
    $tmp = require $localPath;
    if (is_array($tmp)) {
        $localOverrides = $tmp;
    }
}

$smtpEnabledRaw = getenv('SMTP_ENABLED');
$smtpEnabled = $smtpEnabledRaw === false ? true : filter_var((string)$smtpEnabledRaw, FILTER_VALIDATE_BOOLEAN);
$smtpUsername = (string)(getenv('SMTP_USERNAME') ?: '');
$smtpPassword = (string)(getenv('SMTP_PASSWORD') ?: '');
$smtpHost = (string)(getenv('SMTP_HOST') ?: 'smtp.gmail.com');
$smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
$smtpEncryption = (string)(getenv('SMTP_ENCRYPTION') ?: 'tls');
$smtpFromEmail = (string)(getenv('SMTP_FROM_EMAIL') ?: $smtpUsername);
$smtpFromName = (string)(getenv('SMTP_FROM_NAME') ?: 'KSGITREPORT');
$smtpEhloHost = (string)(getenv('SMTP_EHLO_HOST') ?: 'localhost');

$config = [
    'app' => [
        'name' => 'ReportIT',
        'base_url' => '',
        'timezone' => 'Africa/Nairobi',
        'campuses' => ['HQ', 'Nairobi', 'Embu', 'Matuga', 'Mombasa', 'Baringo'],
        'admin_emails' => ['admin@ksg.ac.ke'],
        'it_staff' => [
            ['name' => 'IT Admin', 'email' => 'admin@ksg.ac.ke'],
            ['name' => 'Denis Kiplagat', 'email' => 'denis.kiplagat@ksg.ac.ke'],
            ['name' => 'Paul Nzoka', 'email' => 'paul.nzoka@ksg.ac.ke'],
            ['name' => 'Sheila Cherop', 'email' => 'sheila.cherop@ksg.ac.ke'],
        ],
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
        'from' => $smtpUsername !== '' ? $smtpUsername : 'no-reply@ksg.ac.ke',
        'from_name' => 'KSGITREPORT',
    ],
    'smtp' => [
        'enabled' => $smtpEnabled && $smtpUsername !== '' && $smtpPassword !== '' && $smtpFromEmail !== '',
        'host' => $smtpHost,
        'port' => $smtpPort,
        'encryption' => $smtpEncryption,
        'username' => $smtpUsername,
        'password' => $smtpPassword,
        'from_email' => $smtpFromEmail,
        'from_name' => $smtpFromName,
        'ehlo_host' => $smtpEhloHost,
    ],
    'reports' => [
        'management' => [
            'enabled' => true,
            'recipients' => ['admin@ksg.ac.ke'],
            'default_period' => 'weekly',
            'subject_prefix' => 'ReportIT Management Report',
        ],
    ],
];

if (is_array($localOverrides) && count($localOverrides) > 0) {
    $config = array_replace_recursive($config, $localOverrides);
}

return $config;
