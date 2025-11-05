<?php

namespace App\Core;

use App\Support\Env;
use PDO;
use PDOException;

class Database
{
    protected static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (static::$connection === null) {
            $driver = Env::get('DB_CONNECTION', 'sqlite');
            $database = Env::get('DB_DATABASE', __DIR__ . '/../../database/database.sqlite');
            $username = Env::get('DB_USERNAME');
            $password = Env::get('DB_PASSWORD');

            try {
                if ($driver === 'sqlite') {
                    static::$connection = new PDO('sqlite:' . $database);
                    static::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                } else {
                    $host = Env::get('DB_HOST', '127.0.0.1');
                    $port = Env::get('DB_PORT', '3306');
                    $dsn = sprintf('%s:host=%s;port=%s;dbname=%s', $driver, $host, $port, $database);
                    static::$connection = new PDO($dsn, $username, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                }
            } catch (PDOException $exception) {
                throw new \RuntimeException('Database connection failed: ' . $exception->getMessage(), 0, $exception);
            }
        }

        return static::$connection;
    }
}
