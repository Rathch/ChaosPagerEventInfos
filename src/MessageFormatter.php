<?php

namespace ChaosPagerEventInfos;

/**
 * MessageFormatter - Formats messages for pager
 *
 * Creates messages in format: "HH:MM, Room, Title"
 * JSON sanitization is done via json_encode().
 */
class MessageFormatter
{
    /**
     * Formats talk data to message text
     *
     * Format: "HH:MM, Room, Title"
     * Example: "10:30, One, Grand opening"
     *
     * @param array<string, mixed> $talk Talk data
     * @return string Message text
     */
    public static function formatMessage(array $talk): string
    {
        $title = $talk['title'] ?? '';
        $date = $talk['date'] ?? '';
        $room = $talk['room'] ?? '';

        // Parse date and extract time (HH:MM)
        $time = self::extractTime($date);

        return "{$time}, {$room}, {$title}";
    }

    /**
     * Extracts time from ISO-8601 date string
     *
     * @param string $dateString ISO-8601 format (e.g. "2025-12-27T11:00:00+01:00")
     * @return string Time in HH:MM format
     */
    private static function extractTime(string $dateString): string
    {
        try {
            $dateTime = new \DateTime($dateString);

            return $dateTime->format('H:i');
        } catch (\Exception $e) {
            Logger::warning("Could not parse date: {$dateString}");

            return '00:00';
        }
    }

}
