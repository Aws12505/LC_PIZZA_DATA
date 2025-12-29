<?php
use Illuminate\Support\Str;
return [
    'default' => env('DB_CONNECTION', 'operational'),

    'connections' => [

        // OPERATIONAL DATABASE - Hot data (last 90 days)
        'operational' => [
            'driver' => 'mysql',
            'host' => env('DB_OPERATIONAL_HOST', '127.0.0.1'),
            'port' => env('DB_OPERATIONAL_PORT', '3306'),
            'database' => env('DB_OPERATIONAL_DATABASE', 'pizza_data_operational'),
            'username' => env('DB_OPERATIONAL_USERNAME', 'root'),
            'password' => env('DB_OPERATIONAL_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'archive' => [
            'driver' => 'mysql',
            'host' => env('DB_ARCHIVE_HOST', '127.0.0.1'),
            'port' => env('DB_ARCHIVE_PORT', '3306'),
            'database' => env('DB_ARCHIVE_DATABASE', 'pizza_data_archive'),
            'username' => env('DB_ARCHIVE_USERNAME', 'root'),
            'password' => env('DB_ARCHIVE_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'aggregation' => [
            'driver' => 'mysql',
            'host' => env('DB_AGGREGATION_HOST', '127.0.0.1'),
            'port' => env('DB_AGGREGATION_PORT', '3306'),
            'database' => env('DB_AGGREGATION_DATABASE', 'pizza_data_aggregation'),
            'username' => env('DB_AGGREGATION_USERNAME', 'root'),
            'password' => env('DB_AGGREGATION_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        // OLD DATABASE - For migration purposes (temporary)
        'old' => [
            'driver' => 'mysql',
            'host' => env('DB_OLD_HOST', '127.0.0.1'),
            'port' => env('DB_OLD_PORT', '3306'),
            'database' => env('DB_OLD_DATABASE', 'APIDBUpdate'),
            'username' => env('DB_OLD_USERNAME', 'root'),
            'password' => env('DB_OLD_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],
        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];
