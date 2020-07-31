<?php

return [
    // diff between sql script and database
    [
        [
            'dbname' => 'database_name', // Database name
            'user' => 'username', // user
            'password' => 'password', // password
            'host' => 'hostname', // host or ip address
            'driver' => 'pdo_mysql', // mysql => pdo_mysql, oracle => pdo_oci, ms sql => pdo_dblib, etc. see https://www.php.net/manual/de/pdo.drivers.php
        ],
        [
            'dbname' => 'database_name',
            'path' => 'path/to/file.sql',
            'driver' => 'file',
        ],
    ],
    // diff between two databases
    [
        [
            'dbname' => 'database_name',
            'user' => 'username',
            'password' => 'password',
            'host' => 'hostname',
            'driver' => 'pdo_mysql',
        ],
        [
            'dbname' => 'database_name',
            'user' => 'username',
            'password' => 'password',
            'host' => 'hostname',
            'driver' => 'pdo_mysql',
        ],
    ],
];