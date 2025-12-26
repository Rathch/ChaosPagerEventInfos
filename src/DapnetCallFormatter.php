<?php

namespace ChaosPagerEventInfos;

/**
 * DapnetCallFormatter - Formats messages as DAPNET Call Format
 *
 * Converts message text to DAPNET Call Format with all required fields.
 * Handles ASCII sanitization, message truncation, and validation.
 */
class DapnetCallFormatter
{
    /**
     * Formats a message as a DAPNET Call
     *
     * @param string $messageText Message text (e.g., "10:30, One, Talk Title")
     * @param string $subscriberId DAPNET Subscriber ID
     * @return array<string, mixed> DAPNET Call array with all required fields
     * @throws \RuntimeException If validation fails or subscriber ID is missing
     */
    public static function formatCall(string $messageText, string $subscriberId): array
    {
        // Sanitize message to ASCII-only
        $sanitizedText = AsciiSanitizer::sanitize($messageText);

        // Truncate to max 160 characters with intelligent shortening
        $truncatedText = self::truncateMessage($sanitizedText, 160);

        // Validate subscriber ID
        if (empty($subscriberId)) {
            Logger::error("Subscriber ID is empty");

            throw new \RuntimeException("Subscriber ID is required");
        }

        // Get transmitter groups from config
        $transmitterGroups = Config::getDapnetTransmitterGroups();
        if (empty($transmitterGroups)) {
            Logger::error("No transmitter groups configured");

            throw new \RuntimeException("No transmitter groups configured");
        }

        // Build DAPNET Call
        $call = [
            'data' => $truncatedText,
            'expiration' => Config::getDapnetExpiration(),
            'local' => Config::getDapnetLocal(),
            'priority' => Config::getDapnetPriority(),
            'subscriber_groups' => [], // Empty, using individual subscribers
            'subscribers' => [$subscriberId], // Single subscriber
            'transmitter_groups' => $transmitterGroups,
            'transmitters' => [], // Empty, using transmitter groups
            'use_home_info' => Config::getDapnetUseHomeInfo(),
        ];

        // Validate call format
        self::validateCall($call);

        return $call;
    }

    /**
     * Truncates message to maximum length with intelligent shortening
     *
     * If message exceeds maxLength, truncates to (maxLength - 3) and adds "..."
     *
     * @param string $message Message text
     * @param int $maxLength Maximum length (default: 160)
     * @return string Truncated message
     */
    private static function truncateMessage(string $message, int $maxLength = 160): string
    {
        if (strlen($message) <= $maxLength) {
            return $message;
        }

        // Truncate to leave room for "..."
        $truncated = substr($message, 0, $maxLength - 3);

        // Try to truncate at word boundary if possible
        $lastSpace = strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > $maxLength - 20) {
            // If we find a space near the end, truncate there
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return $truncated . '...';
    }

    /**
     * Validates DAPNET Call format
     *
     * @param array<string, mixed> $call DAPNET Call array
     * @return void
     * @throws \RuntimeException If validation fails
     */
    private static function validateCall(array $call): void
    {
        // Validate data field
        if (! isset($call['data']) || ! is_string($call['data'])) {
            throw new \RuntimeException('DAPNET Call: data field is required and must be a string');
        }

        if (strlen($call['data']) > 160) {
            throw new \RuntimeException('DAPNET Call: data field must not exceed 160 characters');
        }

        // Check ASCII-only
        if (! preg_match('/^[\x00-\x7F]*$/', $call['data'])) {
            throw new \RuntimeException('DAPNET Call: data field must contain only ASCII characters');
        }

        // Validate expiration
        if (! isset($call['expiration']) || ! is_int($call['expiration'])) {
            throw new \RuntimeException('DAPNET Call: expiration field is required and must be an integer');
        }

        if ($call['expiration'] < 60 || $call['expiration'] > 86400) {
            throw new \RuntimeException('DAPNET Call: expiration must be between 60 and 86400 seconds');
        }

        // Validate local
        if (! isset($call['local']) || ! is_bool($call['local'])) {
            throw new \RuntimeException('DAPNET Call: local field is required and must be a boolean');
        }

        // Validate priority
        if (! isset($call['priority']) || ! is_int($call['priority'])) {
            throw new \RuntimeException('DAPNET Call: priority field is required and must be an integer');
        }

        if ($call['priority'] < 0 || $call['priority'] > 7) {
            throw new \RuntimeException('DAPNET Call: priority must be between 0 and 7');
        }

        // Validate subscriber_groups
        if (! isset($call['subscriber_groups']) || ! is_array($call['subscriber_groups'])) {
            throw new \RuntimeException('DAPNET Call: subscriber_groups field is required and must be an array');
        }

        // Validate subscribers
        if (! isset($call['subscribers']) || ! is_array($call['subscribers'])) {
            throw new \RuntimeException('DAPNET Call: subscribers field is required and must be an array');
        }

        // At least one subscriber or subscriber group required
        if (empty($call['subscribers']) && empty($call['subscriber_groups'])) {
            throw new \RuntimeException('DAPNET Call: at least one subscriber or subscriber_group is required');
        }

        // Validate all subscriber IDs are strings
        foreach ($call['subscribers'] as $subscriber) {
            if (! is_string($subscriber)) {
                throw new \RuntimeException('DAPNET Call: all subscribers must be strings');
            }
        }

        // Validate transmitter_groups
        if (! isset($call['transmitter_groups']) || ! is_array($call['transmitter_groups'])) {
            throw new \RuntimeException('DAPNET Call: transmitter_groups field is required and must be an array');
        }

        // Validate transmitters
        if (! isset($call['transmitters']) || ! is_array($call['transmitters'])) {
            throw new \RuntimeException('DAPNET Call: transmitters field is required and must be an array');
        }

        // At least one transmitter or transmitter group required
        if (empty($call['transmitters']) && empty($call['transmitter_groups'])) {
            throw new \RuntimeException('DAPNET Call: at least one transmitter or transmitter_group is required');
        }

        // Validate all transmitter group names are strings
        foreach ($call['transmitter_groups'] as $group) {
            if (! is_string($group)) {
                throw new \RuntimeException('DAPNET Call: all transmitter_groups must be strings');
            }
        }

        // Validate use_home_info
        if (! isset($call['use_home_info']) || ! is_bool($call['use_home_info'])) {
            throw new \RuntimeException('DAPNET Call: use_home_info field is required and must be a boolean');
        }
    }
}
