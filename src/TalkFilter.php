<?php

namespace ChaosPagerEventInfos;

/**
 * TalkFilter - Filters talks by large rooms
 *
 * Large rooms: One, Ground, Zero, Fuse
 */
class TalkFilter
{
    private const LARGE_ROOMS = ['One', 'Ground', 'Zero', 'Fuse'];

    /**
     * Checks if a talk takes place in a large room
     *
     * @param array<string, mixed> $talk Talk data from API
     * @return bool
     */
    public static function isLargeRoom(array $talk): bool
    {
        if (! isset($talk['room']) || empty($talk['room'])) {
            return false;
        }

        $room = trim($talk['room']);

        return in_array($room, self::LARGE_ROOMS, true);
    }

    /**
     * Filters talks by large rooms
     *
     * @param array<int, array<string, mixed>> $talks Array of talk data
     * @return array<int, array<string, mixed>> Filtered talks (large rooms only)
     */
    public static function filterLargeRooms(array $talks): array
    {
        return array_filter($talks, [self::class, 'isLargeRoom']);
    }

    /**
     * Returns list of large rooms
     *
     * @return array<int, string>
     */
    public static function getLargeRooms(): array
    {
        return self::LARGE_ROOMS;
    }
}
