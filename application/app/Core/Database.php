<?php

declare(strict_types=1);

namespace Barama\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $driver = (string) config('database.driver', 'mysql');

        if ($driver !== 'mysql') {
            throw new RuntimeException('Only the MySQL/MariaDB PDO driver is currently supported.');
        }

        $host = (string) config('database.host', '127.0.0.1');
        $port = (int) config('database.port', 3306);
        $database = (string) config('database.database', '');
        $username = (string) config('database.username', '');
        $password = (string) config('database.password', '');
        $charset = (string) config('database.charset', 'utf8mb4');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed.', 0, $exception);
        }

        return self::$connection;
    }

    private function __construct()
    {
    }
}
