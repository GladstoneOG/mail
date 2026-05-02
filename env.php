<?php
return [
    // Set to 'prod' to use the production database credentials below.
    // Set to 'dev' (default) to use the development credentials defined here.
    // You can add additional environments (e.g. 'staging') by appending new keys to this array.
    'environment' => 'dev',

    'dev' => [
        'db_type' => 'mssql',
        'server' => 'JOHAN\SQLEXPRESS',
        'database' => 'INBOX',
        'username' => 'sa',
        'password' => 'w@tch9u@rd',
        // For SQLite setups, override db_type to 'sqlite' and point to a file path instead:
        // 'db_type' => 'sqlite',
        // 'path'    => __DIR__ . '/inventory.sqlite',
    ],

    'prod' => [
        'db_type' => 'mssql',
        'server' => '182.168.0.118',
        'database' => 'INBOX',
        'username' => 'pikdb',
        'password' => '0riginPIK',
    ],
];
