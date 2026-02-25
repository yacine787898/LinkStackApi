<?php

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'linkstack',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    // URL publique de LinkStack (sans slash final)
    'app_url' => 'http://localhost',
    // max 30 comme demandé
    'max_links' => 30,
    // 500 comme la logique LinkStack habituelle
    'max_bio_length' => 500,
    // Token API pour sqltest/api_create_account.php
    'api_token' => 'change-me-very-long-random-token',
];
