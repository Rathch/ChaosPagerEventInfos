<?php

namespace ChaosPagerEventInfos;

/**
 * Config - Loads configuration from .env file
 * 
 * Simple parsing without external library per Constitution.
 */
class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    /**
     * Loads configuration from .env file
     * 
     * @param string|null $envFile Path to .env file (default: .env in project root)
     * @return void
     */
    public static function load(?string $envFile = null): void
    {
        if (self::$loaded) {
            return;
        }

        if ($envFile === null) {
            // Look for .env in project root (2 levels above src/)
            $envFile = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($envFile)) {
            throw new \RuntimeException("Configuration file not found: {$envFile}");
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            self::$config[$key] = $value;
        }

        self::$loaded = true;
    }

    /**
     * Returns configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$config[$key] ?? $default;
    }

    /**
     * Checks if configuration value exists
     * 
     * @param string $key Configuration key
     * @return bool
     */
    public static function has(string $key): bool
    {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$config[$key]);
    }

    /**
     * Returns all configuration values
     * 
     * @return array
     */
    public static function all(): array
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$config;
    }
}
