<?php
return [
    // Set to 'prod' to use the production database credentials below.
    // Set to 'dev' (default) to use the development credentials defined here.
    // You can add additional environments (e.g. 'staging') by appending new keys to this array.
    'environment' => 'dev',

    'dev' => [
        'db_type' => 'mssql',
        'server' => 'name',
        'database' => 'db',
        'username' => 'sa',
        'password' => 'password',
    ],

    'prod' => [
        'db_type' => 'mssql',
        'server' => 'name',
        'database' => 'db',
        'username' => 'sa',
        'password' => 'password',
    ],
];
