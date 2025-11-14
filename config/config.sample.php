<?php

return [
    'app_name' => 'EchoDB',
    'app_version' => '1.0.0',
    'environment' => 'production',
    'display_errors' => false,
    'base_path' => '',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'echodb',
        'username' => 'echodb',
        'password' => 'secret',
        'charset' => 'utf8mb4',
    ],
    'logger' => [
        'path' => __DIR__ . '/../logs/app.log',
        'level' => 'debug',
    ],
    'cors' => [
        'allowed_origins' => [],
    ],
    'rate_limit' => [
        'requests' => 30,
        'per_seconds' => 60,
    ],
];
