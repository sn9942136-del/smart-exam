<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * File Logger Library
 * Handles logging errors, exceptions, and audit trails to storage/logs/.
 */
class Logger
{
    private static string $logDir = __DIR__ . '/../../storage/logs';

    /**
     * Tulis pesan log
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0777, true);
        }

        $date = date('Y-m-d H:i:s');
        $fileName = self::$logDir . '/app-' . date('Y-m-d') . '.log';

        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $logEntry = sprintf("[%s] [%s] %s%s%s", $date, strtoupper($level), $message, $contextStr, PHP_EOL);

        @file_put_contents($fileName, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function exception(\Throwable $e): void
    {
        $message = sprintf(
            "%s: %s in %s on line %d\nStack trace:\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        self::log('EXCEPTION', $message);
    }
}
