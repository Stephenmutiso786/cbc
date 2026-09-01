<?php

$databaseUrl = env('DB_URL') ?: env('DATABASE_URL');
$mysqlCa = env('MYSQL_ATTR_SSL_CA');
if (is_string($mysqlCa) && str_starts_with(trim($mysqlCa), '-----BEGIN CERTIFICATE-----')) {
    $mysqlCa = str_replace(['\\r\\n', '\\n', '\\r'], ["\r\n", "\n", "\r"], trim($mysqlCa));
    $mysqlCaPath = '/tmp/aiven-ca.pem';
    file_put_contents($mysqlCaPath, $mysqlCa);
    chmod($mysqlCaPath, 0644);
    $mysqlCa = $mysqlCaPath;
}

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'sqlite' => ['driver' => 'sqlite', 'url' => env('DB_URL'), 'database' => env('DB_DATABASE', database_path('database.sqlite')), 'prefix' => '', 'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true)],
        'mysql'  => [
            'driver' => 'mysql',
            'url' => $databaseUrl,
            'host' => env('DB_HOST', '127.0.0.1'),
            // Aiven's public MySQL service uses its assigned public port, not 3306.
            'port' => (str_ends_with((string) env('DB_HOST', ''), '.aivencloud.com') && env('DB_PORT', '3306') === '3306')
                ? '21229'
                : env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::ATTR_TIMEOUT => (int) env('DB_CONNECT_TIMEOUT', 10),
                PDO::MYSQL_ATTR_SSL_CA => $mysqlCa,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => filter_var(
                    env('MYSQL_ATTR_SSL_VERIFY_SERVER_CERT', 'true'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ) ?? true,
            ]) : [],
        ],
        'pgsql'  => [
            'driver' => 'pgsql',
            'url' => $databaseUrl,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => env('DB_SCHEMA', 'public'),
            'sslmode' => env('PGSSLMODE', 'require'),
            'options' => extension_loaded('pdo_pgsql') ? [
                PDO::ATTR_TIMEOUT => (int) env('DB_CONNECT_TIMEOUT', 10),
            ] : [],
        ],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
    'redis' => [
        'client'  => env('REDIS_CLIENT', 'phpredis'),
        'options' => ['cluster' => env('REDIS_CLUSTER', 'redis'), 'prefix' => env('REDIS_PREFIX', \Illuminate\Support\Str::slug(env('APP_NAME', 'laravel'), '_').'_database_')],
        'default' => ['url' => env('REDIS_URL'), 'host' => env('REDIS_HOST', '127.0.0.1'), 'username' => env('REDIS_USERNAME'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', '6379'), 'database' => env('REDIS_DB', '0')],
        'cache'   => ['url' => env('REDIS_URL'), 'host' => env('REDIS_HOST', '127.0.0.1'), 'username' => env('REDIS_USERNAME'), 'password' => env('REDIS_PASSWORD'), 'port' => env('REDIS_PORT', '6379'), 'database' => env('REDIS_CACHE_DB', '1')],
    ],
];
