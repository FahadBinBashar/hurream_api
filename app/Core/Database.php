<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    protected static ?PDO $connection = null;
    public static function connection(): PDO
    {
        if (static::$connection === null) {
            $connectionName = config('database.default', env('DB_CONNECTION', 'mysql'));
            $config = config("database.connections.{$connectionName}");

            if ($config === null) {
                throw new RuntimeException("Database connection configuration '{$connectionName}' is not defined.");
            }

            $driver = $config['driver'] ?? $connectionName;
            $database = $config['database'] ?? null;
            $username = $config['username'] ?? null;
            $password = $config['password'] ?? null;
            $options = $config['options'] ?? [];

            try {
                if ($driver === 'sqlite') {
                    static::$connection = new PDO('sqlite:' . $database);
                } else {
                    $host = $config['host'] ?? '127.0.0.1';
                    $port = $config['port'] ?? null;
                    $dsn = sprintf(
                        '%s:host=%s%s;dbname=%s',
                        $driver,
                        $host,
                        $port ? ";port={$port}" : '',
                        $database
                    );
                    static::$connection = new PDO($dsn, $username, $password, $options);
                }

                static::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                static::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $exception) {
                $context = [
                    'driver' => $driver,
                    'database' => $database,
                ];

                if ($driver !== 'sqlite') {
                    $context['host'] = $host ?? 'unknown';
                    $context['port'] = $port ?? 'unknown';
                    $context['username'] = $username ?? 'not-set';
                }

                $contextMessage = http_build_query($context, '', ', ');

                throw new \RuntimeException(
                    'Database connection failed: ' . $exception->getMessage() . ' (' . $contextMessage . ')',
                    0,
                    $exception
                );
            }
        }

        return static::$connection;
    }
}
