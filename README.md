# PHP database diff tool 

This tool is written in PHP and is using doctrine to create diffs between database schemes.
You can also make a diff between a sql schema dump and a database.

## Installation

Use composer
```shell script
composer install gared/database-structure-diff
```

or clone this repository

```shell script
git clone https://github.com/gared/database-structure-diff.git
```

## Configuration

Copy the file config.example.php or copy this example in a file
```php
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
];
```

You can also define multiple groups to make diff

```php
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
```

For more database configuration read the doctrine configuration:
https://www.doctrine-project.org/projects/doctrine-dbal/en/2.9/reference/configuration.html

## Usage 

If you cloned this repository execute

```shell script
php bin/console database:calculate-diff config.php
```

or if you installed it with composer

```shell script
php vendor/gared/database-structure-diff/bin/console database:calculate-diff config.php
```

or use the option "output-file" to store an ALTER script to a file

```shell script
php bin/console database:calculate-diff config.php --output-file alter.sql
```

### Example output

```shell script
Database: example@10.10.1.1 => File: strcture.sql
-------------------------------------------------

New tables
==========

 * user: user_id, name

Removed tables
==============

 * player

Changed tables
==============

team
----

Added columns
 * team_short_name: String 10

Changed columns
path
 * length: 100 => 255

Removed columns
 * user_id

Removed indexes
 * FK_team_user

Added foreign keys
 * FK_C4E0A61F3A35FDA4: (team_type_id) => team_type (team_type_id)

group
-----

Renamed indexes
 * fk_group => fk_group_idx
```

## Supported Platforms

* Doctrine supported databases (MySQL, MariaDB, Oracle, etc.)
* You need at least PHP 7.1