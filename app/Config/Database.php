<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Database Configuration Class
 * Central database credentials and connection options.
 */
class Database
{
    public static string $host = '127.0.0.1';
    public static int $port = 3306;
    public static string $database = 'ujian_dual_kamera';
    public static string $username = 'root';
    public static string $password = '';
    public static string $charset = 'utf8mb4';

    /**
     * Memuat konfigurasi database dari environment
     */
    public static function load(): void
    {
        self::$host = App::env('DB_HOST', self::$host);
        self::$port = (int) App::env('DB_PORT', self::$port);
        self::$database = App::env('DB_DATABASE', self::$database);
        self::$username = App::env('DB_USERNAME', self::$username);
        self::$password = App::env('DB_PASSWORD', self::$password);
        self::$charset = App::env('DB_CHARSET', self::$charset);
    }

    /**
     * Mengembalikan Data Source Name (DSN) untuk PDO MySQL
     */
    public static function getDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            self::$host,
            self::$port,
            self::$database,
            self::$charset
        );
    }

    /**
     * Opsi default PDO untuk keandalan dan keamanan
     */
    public static function getOptions(): array
    {
        return [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_TIMEOUT            => 2,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . self::$charset . " COLLATE " . self::$charset . "_unicode_ci"
        ];
    }
}
