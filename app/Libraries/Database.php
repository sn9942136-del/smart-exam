<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Config\Database as DbConfig;
use PDO;
use PDOException;

/**
 * Database Connector Library
 * Singleton PDO connection manager with transaction and logging support.
 */
class Database
{
    private static ?PDO $instance = null;

    /**
     * Dapatkan instance aktif PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            DbConfig::load();
            try {
                self::$instance = new PDO(
                    DbConfig::getDsn(),
                    DbConfig::$username,
                    DbConfig::$password,
                    DbConfig::getOptions()
                );
            } catch (PDOException $e) {
                throw new \RuntimeException("Koneksi Database Gagal: " . $e->getMessage(), (int)$e->getCode(), $e);
            }
        }

        return self::$instance;
    }

    /**
     * Memulai Database Transaction
     */
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    /**
     * Commit Database Transaction
     */
    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    /**
     * Rollback Database Transaction
     */
    public static function rollBack(): bool
    {
        return self::getConnection()->rollBack();
    }

    /**
     * Cek apakah status koneksi database terhubung (non-blocking)
     */
    public static function isConnected(): bool
    {
        DbConfig::load();
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen(DbConfig::$host, (int)DbConfig::$port, $errno, $errstr, 0.15);
        if (!$socket) {
            return false;
        }

        stream_set_timeout($socket, 0, 100000); // 100ms
        $greeting = @fread($socket, 10);
        fclose($socket);

        if (empty($greeting)) {
            return false;
        }

        try {
            self::getConnection();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
