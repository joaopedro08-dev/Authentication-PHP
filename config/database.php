<?php

declare(strict_types=1);

/**
 * PDO singleton factory.
 */
final class Database
{
    private static ?PDO $instance = null;

    /**
     * Returns a configured PDO instance.
     *
     * @throws PDOException
     */
    public static function getInstance(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $driver = self::env('DB_DRIVER', 'mysql');
        $host = self::env('DB_HOST', '127.0.0.1');
        $port = self::env('DB_PORT', '');
        $dbName = self::env('DB_NAME', 'auth_php');
        $user = self::env('DB_USER', 'root');
        $pass = self::env('DB_PASS', '');
        $file = self::env('DB_FILE', '');

        $dsn = match ($driver) {
            'mysql' => self::buildMysqlDsn($host, $port ?: '3306', $dbName),
            'pgsql' => self::buildPgsqlDsn($host, $port ?: '5432', $dbName),
            'sqlsrv' => self::buildSqlsrvDsn($host, $port ?: '1433', $dbName),
            'sqlite' => self::buildSqliteDsn($file),
            default => throw new InvalidArgumentException(
                'Unsupported DB_DRIVER. Supported: mysql, pgsql, sqlsrv, sqlite.'
            ),
        };

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($driver !== 'sqlite') {
            $options[PDO::ATTR_EMULATE_PREPARES] = false;
        }

        self::$instance = new PDO($dsn, $user, $pass, $options);

        return self::$instance;
    }

    /**
     * Builds MySQL DSN.
     */
    private static function buildMysqlDsn(string $host, string $port, string $dbName): string
    {
        return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
    }

    /**
     * Builds PostgreSQL DSN.
     */
    private static function buildPgsqlDsn(string $host, string $port, string $dbName): string
    {
        return sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbName);
    }

    /**
     * Builds SQL Server DSN (Windows authentication or SQL auth).
     */
    private static function buildSqlsrvDsn(string $host, string $port, string $dbName): string
    {
        return sprintf('sqlsrv:Server=%s,%s;Database=%s', $host, $port, $dbName);
    }

    /**
     * Builds SQLite DSN.
     */
    private static function buildSqliteDsn(string $file): string
    {
        $filePath = $file ?: dirname(__DIR__) . '/storage/database.sqlite';
        return sprintf('sqlite:%s', $filePath);
    }

    /**
     * Reads an environment variable from $_ENV or getenv().
     */
    private static function env(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }
}