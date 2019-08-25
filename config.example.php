<?php

return [
    'diff between sql script and database' => [
        [
            'dbname' => 'database_name',
            'user' => 'username',
            'password' => 'password',
            'host' => 'hostname',
            'driver' => 'pdo_mysql',
        ],
        [
            'dbname' => 'database_name',
            'path' => 'path/to/file.sql',
            'driver' => 'file',
        ],
    ],
    'diff between two databases' => [
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