<?php

namespace OptiPubWP;

/**
 * Troubleshooting Log class for OptiPubWP
 *
 * This class provides a base class for logging messages to a file with the class name as the filename.
 * It includes methods for logging messages with timestamps and optional file paths.
 *
 * @package optipub
 */
class Log
{
    protected string $log_file;

    private static array $instances = [];

    private function __construct(string $className)
    {
        $this->log_file = '/bitnami/wordpress/wp-content/' . strtolower($className) . '.log';
    }

    /**
     * Get singleton instance for a specific class
     */
    public static function getInstance(string $className = 'Log'): Log
    {
        if ($className === null) {
            $className = (new \ReflectionClass(static::class))->getShortName();
        }

        if (!isset(self::$instances[$className])) {
            self::$instances[$className] = new self($className);
        }

        return self::$instances[$className];
    }

    /**
     * Log a message to the class-specific log file
     *
     * @param string $message The message to log
     * @param bool $include_timestamp Whether to include a timestamp in the log entry
     * @return void
     */
    public function log(string $message, bool $include_timestamp = true): void
    {
        $log_entry = '';

        if ($include_timestamp) {
            $log_entry .= gmdate('Y-m-d H:i:s') . ' - ';
        }
        $log_entry .= $message . "\n";

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log($log_entry, 3, $this->log_file);
    }

    /**
     * Static method to log a message
     *
     * @param string $message The message to log
     * @param bool $include_timestamp Whether to include a timestamp in the log entry
     * @return void
     */
    public static function staticLog(string $message, bool $include_timestamp = true): void
    {
        self::getInstance()->log($message, $include_timestamp);
    }
}
