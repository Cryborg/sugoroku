<?php

return [
    'driver' => getenv('DB_DRIVER') ?: 'sqlite',
    'sqlite' => [
        'path' => __DIR__ . '/../database/trapped.db'
    ],
    'mysql' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: 3306,
        'database' => getenv('DB_NAME') ?: 'trapped',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ]
];
