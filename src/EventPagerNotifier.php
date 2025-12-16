<?php

namespace ChaosPagerEventInfos;

/**
 * EventPagerNotifier - Main class for event pager notifications
 * 
 * Orchestrates the entire process:
 * 1. API fetch
 * 2. Filter by large rooms
 * 3. Time check (15 minutes before start)
 * 4. Duplicate check
 * 5. Message creation
 * 6. Send via WebSocket
 */
class EventPagerNotifier
{
    private ApiClient $apiClient;
    private DuplicateTracker $duplicateTracker;
    private WebSocketClientInterface $webSocketClient;
    private int $notificationMinutes;
    private bool $testMode;

    public function __construct(
        ?ApiClient $apiClient = null,
        ?DuplicateTracker $duplicateTracker = null,
        ?WebSocketClientInterface $webSocketClient = null,
        int $notificationMinutes = 15
    ) {
        $this->apiClient = $apiClient ?? new ApiClient();
        $this->duplicateTracker = $duplicateTracker ?? new DuplicateTracker();
        $this->webSocketClient = $webSocketClient ?? WebSocketClient::create();
        $this->notificationMinutes = $notificationMinutes;
        $this->testMode = filter_var(Config::get('TEST_MODE', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Executes notification process
     * 
     * @return int Number of messages sent
     */
    public function run(): int
    {
        Logger::info("Starting event pager notification process" . ($this->testMode ? " (TEST MODE)" : ""));

        try {
            // 1. API fetch
            $events = $this->apiClient->fetchEvents();
            Logger::info("API fetch successful: " . count($events) . " events loaded");

            // 2. Filter by large rooms
            $largeRoomEvents = TalkFilter::filterLargeRooms($events);
            Logger::info("Filtered: " . count($largeRoomEvents) . " talks in large rooms");

            // 3. Time check and sending
            $sentCount = 0;
            $now = new \DateTime();

            // In test mode, send notification for first talk regardless of time
            if ($this->testMode && !empty($largeRoomEvents)) {
                $firstTalk = reset($largeRoomEvents);
                Logger::info("TEST MODE: Sending notification for first talk: " . ($firstTalk['title'] ?? 'unknown'));
                
                if ($this->sendNotification($firstTalk)) {
                    $sentCount++;
                }
            } else {
                // Normal mode: check time for each talk
                foreach ($largeRoomEvents as $talk) {
                    if ($this->shouldSendNotification($talk, $now)) {
                        if ($this->sendNotification($talk)) {
                            $sentCount++;
                        }
                    }
                }
            }

            // Cleanup
            $this->duplicateTracker->cleanup();

            Logger::info("Notification process completed: {$sentCount} messages sent");
            return $sentCount;

        } catch (\Exception $e) {
            Logger::error("Error in notification process: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Checks if notification should be sent
     * 
     * @param array $talk Talk data
     * @param \DateTime $now Current time
     * @return bool
     */
    private function shouldSendNotification(array $talk, \DateTime $now): bool
    {
        // Validation: Title present?
        if (empty($talk['title'])) {
            Logger::warning("Talk without title ignored: " . ($talk['id'] ?? 'unknown'));
            return false;
        }

        // Validation: Room present?
        if (empty($talk['room'])) {
            Logger::warning("Talk without room ignored: " . ($talk['id'] ?? 'unknown'));
            return false;
        }

        // Validation: Date present and valid?
        if (empty($talk['date'])) {
            Logger::warning("Talk without date ignored: " . ($talk['id'] ?? 'unknown'));
            return false;
        }

        try {
            $talkStart = new \DateTime($talk['date']);
        } catch (\Exception $e) {
            Logger::warning("Invalid date format ignored: " . ($talk['date'] ?? 'unknown'));
            return false;
        }

        // Check if talk is in the future
        if ($talkStart <= $now) {
            // Ignore past talks (no log)
            return false;
        }

        // Check if talk starts exactly in notificationMinutes
        $notificationTime = clone $talkStart;
        $notificationTime->modify("-{$this->notificationMinutes} minutes");

        // Tolerance: ±30 seconds (per Success Criteria SC-003)
        $diff = abs($now->getTimestamp() - $notificationTime->getTimestamp());
        
        if ($diff > 30) {
            // Too early or too late
            return false;
        }

        // Check duplicate
        $hash = $this->duplicateTracker->createHash($talk);
        if ($this->duplicateTracker->isDuplicate($hash)) {
            Logger::info("Duplicate detected, message not sent: " . ($talk['title'] ?? 'unknown'));
            return false;
        }

        return true;
    }

    /**
     * Sends notification for a talk
     * 
     * @param array $talk Talk data
     * @return bool true on success
     */
    private function sendNotification(array $talk): bool
    {
        try {
            // Create message
            $message = MessageFormatter::createWebSocketMessage($talk);

            // Connect to WebSocket (first endpoint from list)
            $endpoints = $this->getWebSocketEndpoints();
            $connected = false;

            foreach ($endpoints as $endpoint) {
                if ($this->webSocketClient->connect($endpoint)) {
                    $connected = true;
                    break;
                }
            }

            if (!$connected) {
                Logger::error("Could not establish WebSocket connection");
                return false;
            }

            // Send message
            $success = $this->webSocketClient->send($message);

            if ($success) {
                // Mark as sent
                $hash = $this->duplicateTracker->createHash($talk);
                $this->duplicateTracker->markAsSent($hash);
                
                Logger::info("Notification sent: " . ($talk['title'] ?? 'unknown'));
            } else {
                Logger::error("Message could not be sent: " . ($talk['title'] ?? 'unknown'));
            }

            // Disconnect
            $this->webSocketClient->disconnect();

            return $success;

        } catch (\Exception $e) {
            Logger::error("Error sending notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Returns list of WebSocket endpoints
     * 
     * @return array
     */
    private function getWebSocketEndpoints(): array
    {
        $endpointsStr = Config::get('WEBSOCKET_ENDPOINTS', 'ws://localhost:8055');
        $endpoints = explode(',', $endpointsStr);
        
        return array_map('trim', $endpoints);
    }
}
