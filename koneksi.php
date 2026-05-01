<?php
$_envConfig = require __DIR__ . '/env.php';
$_env = $_envConfig['environment'];
$dbConnectionConfig = $_envConfig[$_env];

$serverName = $dbConnectionConfig['server'];
$connectionInfo = array(
    'Database'          => $dbConnectionConfig['database'],
    'UID'               => $dbConnectionConfig['username'],
    'PWD'               => $dbConnectionConfig['password'],
    'CharacterSet'      => 'UTF-8',
    'ReturnDatesAsStrings' => true
);

$conn = sqlsrv_connect($serverName, $connectionInfo);
if ($conn === false) {
    die('Database connection failed: ' . print_r(sqlsrv_errors(), true));
}
