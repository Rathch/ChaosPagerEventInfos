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
     * @param array $talk Talk data
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

    /**
     * Creates HTTP POST message payload in correct format
     * 
     * Format: {"RIC": int, "MSG": string, "m_type": "AlphaNum", "m_func": "Func3"}
     * 
     * @param array $talk Talk data
     * @param int|null $ric Radio Identification Code (should always be provided via RoomRicMapper)
     * @return array Message payload for HTTP POST
     */
    public static function createHttpMessage(array $talk, ?int $ric = null): array
    {
        // Fallback: Use All-Rooms RIC if no RIC provided (for backward compatibility)
        // In production, RIC should always be provided via RoomRicMapper
        if ($ric === null) {
            $ric = RoomRicMapper::getAllRoomsRic();
            Logger::warning("createHttpMessage called without RIC, using All-Rooms RIC as fallback: {$ric}");
        }
        $messageText = self::formatMessage($talk);

        // JSON sanitization: json_encode() automatically handles invalid characters
        // The message text is used directly as MSG field
        return [
            'RIC' => $ric,
            'MSG' => $messageText,
            'm_type' => 'AlphaNum',
            'm_func' => 'Func3'
        ];
    }

    /**
     * Creates WebSocket message in correct format (deprecated, use createHttpMessage)
     * 
     * @deprecated Use createHttpMessage() instead
     * @param array $talk Talk data
     * @param int|null $ric Radio Identification Code (should always be provided via RoomRicMapper)
     * @return array Message in WebSocket format
     */
    public static function createWebSocketMessage(array $talk, ?int $ric = null): array
    {
        // Fallback: Use All-Rooms RIC if no RIC provided (for backward compatibility)
        if ($ric === null) {
            $ric = RoomRicMapper::getAllRoomsRic();
        }
        $messageText = self::formatMessage($talk);

        // JSON sanitization: json_encode() automatically handles invalid characters
        // The message is encoded as JSON string, so we need to ensure
        // the string itself is JSON-compatible
        $jsonEncoded = json_encode($messageText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($jsonEncoded === false) {
            Logger::error("JSON encoding failed for message: {$messageText}");
            throw new \RuntimeException("JSON encoding failed");
        }

        // Remove outer quotes (json_encode adds them)
        $data = json_decode($jsonEncoded);

        return [
            'SendMessage' => [
                'addr' => $ric,
                'data' => $data,
                'mtype' => 'AlphaNum',
                'func' => 'Func3'
            ]
        ];
    }
}
