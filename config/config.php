<?php

/**
 * Application configuration.
 * Copy config/database.php.example to config/database.php for DB overrides,
 * or edit the 'db' block below directly.
 */

$db = is_file(__DIR__ . '/database.php') ? require __DIR__ . '/database.php' : [];

return [
    'app' => [
        'name'      => 'Swami Dayananda Educational Cost Management',
        'short'     => 'SECMS',
        'org'       => 'Swami Dayanandha Educational Institutions, Manjakkudi',
        'debug'     => true,
        'timezone'  => 'Asia/Kolkata',
        // Base URL path of the app. '' when served from public/ as docroot,
        // e.g. '/SDC/public' when running under htdocs/SDC.
        'base_url'  => $db['base_url'] ?? '',
        'session_timeout' => 1800, // seconds of inactivity before logout
    ],

    'db' => [
        'host'     => $db['host']     ?? '127.0.0.1',
        'port'     => $db['port']     ?? 3306,
        'database' => $db['database'] ?? 'secms',
        'username' => $db['username'] ?? 'root',
        'password' => $db['password'] ?? '',
        'charset'  => 'utf8mb4',
    ],

    'uploads' => [
        'path'          => BASE_PATH . '/storage/uploads',
        'max_size'      => 5 * 1024 * 1024, // 5 MB
        'allowed_ext'   => ['pdf', 'png', 'jpg', 'jpeg', 'xlsx', 'xls', 'csv', 'doc', 'docx'],
        'allowed_mimes' => [
            'application/pdf', 'image/png', 'image/jpeg',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel', 'text/csv', 'text/plain',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        // Sanction requests deliberately accept a narrower document set than
        // generic uploads. UploadService enforces both this profile and file
        // signatures, rather than trusting the browser-provided MIME type.
        'sanction_allowed_ext' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
    ],
];
