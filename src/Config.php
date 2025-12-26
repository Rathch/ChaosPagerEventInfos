<?php

namespace ChaosPagerEventInfos;

/**
 * Config - Loads configuration from .env file
 *
 * Simple parsing without external library per Constitution.
 */
class Config
{
    /** @var array<string, string> */
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

        if (! file_exists($envFile)) {
            throw new \RuntimeException("Configuration file not found: {$envFile}");
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new \RuntimeException("Could not read configuration file: {$envFile}");
        }

        foreach ($lines as $line) {
            // Skip comments
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Parse KEY=VALUE
            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
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
        if (! self::$loaded) {
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
        if (! self::$loaded) {
            self::load();
        }

        return isset(self::$config[$key]);
    }

    /**
     * Returns all configuration values
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (! self::$loaded) {
            self::load();
        }

        return self::$config;
    }

    /**
     * Resets configuration (for testing purposes)
     *
     * Clears loaded configuration and allows reloading from a different file.
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$config = [];
        self::$loaded = false;
    }

    // DAPNET API Configuration Methods

    /**
     * Gets DAPNET API URL
     *
     * @return string|null DAPNET API URL or null if not configured
     */
    public static function getDapnetApiUrl(): ?string
    {
        return self::get('DAPNET_API_URL');
    }

    /**
     * Gets DAPNET API Username
     *
     * @return string|null DAPNET API Username or null if not configured
     */
    public static function getDapnetApiUsername(): ?string
    {
        return self::get('DAPNET_API_USERNAME');
    }

    /**
     * Gets DAPNET API Password
     *
     * @return string|null DAPNET API Password or null if not configured
     */
    public static function getDapnetApiPassword(): ?string
    {
        return self::get('DAPNET_API_PASSWORD');
    }

    /**
     * Gets DAPNET Call Priority
     *
     * @param int $default Default priority if not configured (default: 3)
     * @return int DAPNET Call Priority (0-7)
     */
    public static function getDapnetPriority(int $default = 3): int
    {
        $priority = self::get('DAPNET_PRIORITY', $default);
        $priorityInt = filter_var($priority, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 7]]);

        return $priorityInt !== false ? $priorityInt : $default;
    }

    /**
     * Gets DAPNET Call Expiration in seconds
     *
     * @param int $default Default expiration if not configured (default: 86400 = 24 hours)
     * @return int DAPNET Call Expiration in seconds (60-86400)
     */
    public static function getDapnetExpiration(int $default = 86400): int
    {
        $expiration = self::get('DAPNET_EXPIRATION', $default);
        $expirationInt = filter_var($expiration, FILTER_VALIDATE_INT, ['options' => ['min_range' => 60, 'max_range' => 86400]]);

        return $expirationInt !== false ? $expirationInt : $default;
    }

    /**
     * Gets DAPNET Call Local flag
     *
     * @param bool $default Default local flag if not configured (default: false)
     * @return bool DAPNET Call Local flag
     */
    public static function getDapnetLocal(bool $default = false): bool
    {
        $local = self::get('DAPNET_LOCAL');
        if ($local === null) {
            return $default;
        }

        return filter_var($local, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]) ?? $default;
    }

    /**
     * Gets DAPNET Call Use Home Info flag
     *
     * @param bool $default Default use home info flag if not configured (default: false)
     * @return bool DAPNET Call Use Home Info flag
     */
    public static function getDapnetUseHomeInfo(bool $default = false): bool
    {
        $useHomeInfo = self::get('DAPNET_USE_HOME_INFO');
        if ($useHomeInfo === null) {
            return $default;
        }

        return filter_var($useHomeInfo, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]) ?? $default;
    }

    /**
     * Gets DAPNET Transmitter Groups
     *
     * @param string $default Default transmitter groups if not configured (default: "all")
     * @return array<string> List of transmitter group names
     */
    public static function getDapnetTransmitterGroups(string $default = 'all'): array
    {
        $transmitterGroups = self::get('DAPNET_TRANSMITTER_GROUPS', $default);

        if ($transmitterGroups === 'all' || $transmitterGroups === '') {
            return ['all'];
        }

        // Split comma-separated list
        $groups = array_map('trim', explode(',', $transmitterGroups));

        return array_filter($groups, function ($group) {
            return ! empty($group);
        });
    }

    /**
     * Gets DAPNET Subscriber ID for a given RIC
     *
     * @param int $ric Radio Identification Code (e.g., 1140, 1141, 1142, 1143, 1150)
     * @return string|null DAPNET Subscriber ID or null if not configured
     */
    public static function getSubscriberForRic(int $ric): ?string
    {
        $key = "RIC_{$ric}_SUBSCRIBER";
        $subscriber = self::get($key);

        return $subscriber !== null ? (string)$subscriber : null;
    }

    /**
     * Gets notification minutes before talk (when to send notification)
     *
     * @param int $default Default value if not configured (default: 15 minutes)
     * @return int Number of minutes before talk to send notification
     */
    public static function getNotificationMinutes(int $default = 15): int
    {
        $minutes = self::get('NOTIFICATION_MINUTES', $default);

        if (! is_numeric($minutes)) {
            return $default;
        }

        $minutesInt = (int)$minutes;

        // Validate: must be positive integer
        if ($minutesInt < 1) {
            return $default;
        }

        return $minutesInt;
    }
}
