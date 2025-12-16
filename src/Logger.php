<?php

namespace ChaosPagerEventInfos;

/**
 * Logger - Simple logging with file_put_contents
 *
 * Per Constitution: Minimal dependencies, native PHP functions.
 */
class Logger
{
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';

    private static ?string $logFile = null;

    /**
     * Initializes logger with log file from Config
     *
     * @return void
     */
    public static function init(): void
    {
        self::$logFile = Config::get('LOG_FILE', 'logs/event-pager.log');

        // Create log directory if it doesn't exist
        $logDir = dirname(self::$logFile);
        if (! is_dir($logDir) && $logDir !== '.' && $logDir !== '') {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Logs a message
     *
     * @param string $level Log level (INFO, WARNING, ERROR)
     * @param string $message Message
     * @return void
     */
    public static function log(string $level, string $message): void
    {
        if (self::$logFile === null) {
            self::init();
        }

        // Ensure logFile is set after init
        $logFile = self::$logFile ?? 'logs/event-pager.log';

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$level}: {$message}" . PHP_EOL;

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Logs INFO message
     *
     * @param string $message Message
     * @return void
     */
    public static function info(string $message): void
    {
        self::log(self::LEVEL_INFO, $message);
    }

    /**
     * Logs WARNING message
     *
     * @param string $message Message
     * @return void
     */
    public static function warning(string $message): void
    {
        self::log(self::LEVEL_WARNING, $message);
    }

    /**
     * Logs ERROR message
     *
     * @param string $message Message
     * @return void
     */
    public static function error(string $message): void
    {
        self::log(self::LEVEL_ERROR, $message);
    }
}
