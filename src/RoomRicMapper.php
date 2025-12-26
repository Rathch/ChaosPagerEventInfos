<?php

namespace ChaosPagerEventInfos;

/**
 * RoomRicMapper - Maps room names to RIC (Radio Identification Code)
 *
 * Provides static methods for room-RIC lookup and validation.
 * Configuration is loaded from .env file via Config class.
 * Default values are used if configuration is missing.
 */
class RoomRicMapper
{
    // Default RIC values
    private const DEFAULT_RIC_ZERO = 1140;
    private const DEFAULT_RIC_ONE = 1141;
    private const DEFAULT_RIC_GROUND = 1142;
    private const DEFAULT_RIC_FUSE = 1143;
    private const DEFAULT_RIC_ALL_ROOMS = 1150;

    // Valid room names
    private const VALID_ROOMS = ['Zero', 'One', 'Ground', 'Fuse'];

    /**
     * Gets RIC for a specific room
     *
     * @param string $room Room name (e.g. "One", "Ground", "Zero", "Fuse")
     * @return int|null RIC for the room, or null if room is invalid
     */
    public static function getRicForRoom(string $room): ?int
    {
        if (! self::isValidRoom($room)) {
            return null;
        }

        // Map room names to config keys
        $configKeyMap = [
            'Zero' => 'ROOM_RIC_ZERO',
            'One' => 'ROOM_RIC_ONE',
            'Ground' => 'ROOM_RIC_GROUND',
            'Fuse' => 'ROOM_RIC_FUSE',
        ];

        $configKey = $configKeyMap[$room] ?? null;
        if ($configKey === null) {
            return null;
        }

        // Get RIC from config or use default
        $ric = Config::get($configKey);

        if ($ric === null) {
            // Use default value
            $defaultMap = [
                'Zero' => self::DEFAULT_RIC_ZERO,
                'One' => self::DEFAULT_RIC_ONE,
                'Ground' => self::DEFAULT_RIC_GROUND,
                'Fuse' => self::DEFAULT_RIC_FUSE,
            ];

            return $defaultMap[$room] ?? null;
        }

        // Validate RIC is a positive integer
        $ricInt = filter_var($ric, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($ricInt === false) {
            Logger::warning("Invalid RIC configuration for room '{$room}': '{$ric}'. Using default value.");
            $defaultMap = [
                'Zero' => self::DEFAULT_RIC_ZERO,
                'One' => self::DEFAULT_RIC_ONE,
                'Ground' => self::DEFAULT_RIC_GROUND,
                'Fuse' => self::DEFAULT_RIC_FUSE,
            ];

            return $defaultMap[$room] ?? null;
        }

        return $ricInt;
    }

    /**
     * Gets All-Rooms RIC
     *
     * @return int All-Rooms RIC (default: 1150)
     */
    public static function getAllRoomsRic(): int
    {
        $ric = Config::get('ROOM_RIC_ALL_ROOMS');

        if ($ric === null) {
            return self::DEFAULT_RIC_ALL_ROOMS;
        }

        // Validate RIC is a positive integer
        $ricInt = filter_var($ric, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($ricInt === false) {
            Logger::warning("Invalid All-Rooms RIC configuration: '{$ric}'. Using default value " . self::DEFAULT_RIC_ALL_ROOMS);

            return self::DEFAULT_RIC_ALL_ROOMS;
        }

        return $ricInt;
    }

    /**
     * Checks if a room name is valid
     *
     * @param string $room Room name to validate
     * @return bool True if room is valid, false otherwise
     */
    public static function isValidRoom(string $room): bool
    {
        return in_array($room, self::VALID_ROOMS, true);
    }

    /**
     * Gets DAPNET Subscriber ID for a given RIC
     *
     * @param int $ric Radio Identification Code (e.g., 1140, 1141, 1142, 1143, 1150)
     * @return string|null DAPNET Subscriber ID or null if not configured
     */
    public static function getSubscriberForRic(int $ric): ?string
    {
        return Config::getSubscriberForRic($ric);
    }

    /**
     * Gets DAPNET Subscriber ID directly for a room
     *
     * @param string $room Room name (e.g. "One", "Ground", "Zero", "Fuse")
     * @return string|null DAPNET Subscriber ID or null if not configured
     */
    public static function getSubscriberForRoom(string $room): ?string
    {
        if (! self::isValidRoom($room)) {
            return null;
        }

        // Map room names to subscriber config keys
        $configKeyMap = [
            'Zero' => 'ROOM_SUBSCRIBER_ZERO',
            'One' => 'ROOM_SUBSCRIBER_ONE',
            'Ground' => 'ROOM_SUBSCRIBER_GROUND',
            'Fuse' => 'ROOM_SUBSCRIBER_FUSE',
        ];

        $configKey = $configKeyMap[$room] ?? null;
        if ($configKey === null) {
            return null;
        }

        // Get subscriber ID from config
        $subscriberId = Config::get($configKey);
        if ($subscriberId === null) {
            // Fallback: Try RIC-based mapping for backward compatibility
            $ric = self::getRicForRoom($room);
            if ($ric !== null) {
                return self::getSubscriberForRic($ric);
            }

            return null;
        }

        return (string)$subscriberId;
    }

    /**
     * Gets All-Rooms Subscriber ID
     *
     * @return string|null DAPNET Subscriber ID or null if not configured
     */
    public static function getAllRoomsSubscriber(): ?string
    {
        $subscriberId = Config::get('ROOM_SUBSCRIBER_ALL_ROOMS');
        if ($subscriberId !== null) {
            return (string)$subscriberId;
        }

        // Fallback: Try RIC-based mapping for backward compatibility
        $ric = self::getAllRoomsRic();
        return self::getSubscriberForRic($ric);
    }
}
