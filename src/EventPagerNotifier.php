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
 * 6. Send via HTTP POST
 */
class EventPagerNotifier
{
    private ApiClient $apiClient;
    private DuplicateTracker $duplicateTracker;
    private HttpClientInterface $httpClient;
    private MessageQueue $messageQueue;
    private int $notificationMinutes;
    private bool $testMode;

    public function __construct(
        ?ApiClient $apiClient = null,
        ?DuplicateTracker $duplicateTracker = null,
        ?HttpClientInterface $httpClient = null,
        ?MessageQueue $messageQueue = null,
        int $notificationMinutes = 15
    ) {
        $this->apiClient = $apiClient ?? new ApiClient();
        $this->duplicateTracker = $duplicateTracker ?? new DuplicateTracker();
        $this->httpClient = $httpClient ?? HttpClient::create();
        $this->messageQueue = $messageQueue ?? new MessageQueue($this->httpClient);
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
        $simulatedTime = $this->getCurrentTime();
        $timeInfo = $simulatedTime !== null 
            ? " (SIMULATED TIME: " . $simulatedTime->format('Y-m-d H:i:s') . ")" 
            : "";
        Logger::info("Starting event pager notification process" . ($this->testMode ? " (TEST MODE)" : "") . $timeInfo);

        try {
            // 1. API fetch
            $events = $this->apiClient->fetchEvents();
            Logger::info("API fetch successful: " . count($events) . " events loaded");

            // 2. Filter by large rooms
            $largeRoomEvents = TalkFilter::filterLargeRooms($events);
            Logger::info("Filtered: " . count($largeRoomEvents) . " talks in large rooms");

            // 3. Time check and sending
            $enqueuedCount = 0; // Count of talks for which messages were enqueued
            $successfulHashes = []; // Will contain hashes of successfully sent messages
            $now = $this->getCurrentTime() ?? new \DateTime();
            $isSimulatedTime = $this->getCurrentTime() !== null;

            // In test mode WITHOUT simulated time, send notification for first talk regardless of time
            // If simulated time is set, use time-based logic even in test mode
            if ($this->testMode && !$isSimulatedTime && !empty($largeRoomEvents)) {
                $firstTalk = reset($largeRoomEvents);
                Logger::info("TEST MODE: Sending notification for first talk: " . ($firstTalk['title'] ?? 'unknown'));
                
                if ($this->sendNotification($firstTalk)) {
                    $enqueuedCount++;
                }
                
                // Process all enqueued messages together after all talks are processed
                // This ensures sequential sending with delay between ALL messages (not just per talk)
                // Mark messages as sent only after successful delivery
                $successfulHashes = $this->messageQueue->process();
                foreach ($successfulHashes as $hash) {
                    $this->duplicateTracker->markAsSent($hash);
                }
            } else {
                // Normal mode or simulated time mode: check time for each talk
                $modeInfo = $isSimulatedTime ? " (SIMULATION MODE)" : "";
                Logger::info("Checking " . count($largeRoomEvents) . " talks for time-based notifications{$modeInfo} (current time: " . $now->format('Y-m-d H:i:s') . ")");
                
                $checkedCount = 0;
                foreach ($largeRoomEvents as $talk) {
                    $checkedCount++;
                    $talkTitle = $talk['title'] ?? 'unknown';
                    $talkDate = $talk['date'] ?? 'unknown';
                    
                    if ($this->shouldSendNotification($talk, $now)) {
                        Logger::info("✓ Talk matches time criteria: {$talkTitle} (starts at {$talkDate})");
                        if ($this->sendNotification($talk)) {
                            $enqueuedCount++;
                        }
                    } else {
                        // Log why talk was not sent (only in simulation mode to avoid log spam)
                        if ($isSimulatedTime) {
                            $this->logWhyTalkNotSent($talk, $now);
                        }
                    }
                }
                
                // Process all enqueued messages together after all talks are processed
                // This ensures sequential sending with delay between ALL messages (not just per talk)
                // Mark messages as sent only after successful delivery
                $successfulHashes = $this->messageQueue->process();
                foreach ($successfulHashes as $hash) {
                    $this->duplicateTracker->markAsSent($hash);
                }
                
                if ($isSimulatedTime) {
                    $successfulMessageCount = count($successfulHashes);
                    Logger::info("Simulation complete: Checked {$checkedCount} talks, {$enqueuedCount} talks enqueued, {$successfulMessageCount} messages successfully sent");
                }
            }

            // Cleanup
            $this->duplicateTracker->cleanup();

            // Return actual number of successfully sent messages (each hash represents one successfully sent message)
            $successfulMessageCount = count($successfulHashes);
            Logger::info("Notification process completed: {$successfulMessageCount} messages successfully sent (out of " . ($enqueuedCount * 2) . " enqueued)");
            return $successfulMessageCount;

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

        // Calculate time until talk starts
        $timeUntilTalk = $talkStart->getTimestamp() - $now->getTimestamp();
        $minutesUntilTalk = round($timeUntilTalk / 60);

        // Check if we're within the notification window (0 to notificationMinutes minutes before talk)
        // We want to send notifications if we're within 15 minutes before the talk
        // Even if we're a bit late (e.g., script runs at 10:31 for a 10:45 talk)
        if ($minutesUntilTalk > $this->notificationMinutes) {
            // Too early - more than 15 minutes before talk
            return false;
        }

        // Within notification window - send notification
        // Duplicate checking is now done separately for room-specific and all-rooms messages
        // This handles cases where script runs slightly late (e.g., 10:31 for a 10:45 talk)
        return true;
    }

    /**
     * Sends notification for a talk
     * 
     * Sends two notifications: one room-specific and one all-rooms.
     * 
     * @param array $talk Talk data
     * @return bool true if both notifications succeeded, false otherwise
     */
    private function sendNotification(array $talk): bool
    {
        $roomSpecificSuccess = $this->sendRoomSpecificNotification($talk);
        $allRoomsSuccess = $this->sendAllRoomsNotification($talk);
        
        // Messages are enqueued but not processed yet
        // Queue processing happens after all talks are processed (in processTalks method)
        // This ensures sequential sending with delay between ALL messages
        
        return $roomSpecificSuccess && $allRoomsSuccess;
    }

    /**
     * Sends room-specific notification for a talk
     * 
     * @param array $talk Talk data
     * @return bool true on success
     */
    private function sendRoomSpecificNotification(array $talk): bool
    {
        try {
            $room = $talk['room'] ?? '';
            
            // Check if room is valid
            if (!RoomRicMapper::isValidRoom($room)) {
                Logger::warning("Invalid room '{$room}' for talk: " . ($talk['title'] ?? 'unknown') . ". Room-specific notification not sent.");
                return false;
            }

            // Get RIC for room
            $ric = RoomRicMapper::getRicForRoom($room);
            if ($ric === null) {
                Logger::error("Could not determine RIC for room '{$room}'. Room-specific notification not sent.");
                return false;
            }

            // Create message payload with room-specific RIC
            $payload = MessageFormatter::createHttpMessage($talk, $ric);

            // Get HTTP endpoint
            $endpoint = $this->getHttpEndpoint();

            // Get current time for logging
            $sendTime = $this->getCurrentTime() ?? new \DateTime();
            $sendTimeFormatted = $sendTime->format('Y-m-d H:i:s');

            // Check duplicate for room-specific message
            $hash = $this->duplicateTracker->createHash($talk, $ric);
            if ($this->duplicateTracker->isDuplicate($hash)) {
                Logger::info("Duplicate room-specific message detected, not sent: " . ($talk['title'] ?? 'unknown') . " (Room: {$room}, RIC: {$ric})");
                return false;
            }

            // Enqueue message for sequential sending with hash for duplicate tracking
            // Hash will be marked as sent only after successful delivery
            $this->messageQueue->enqueue($payload, $endpoint, $hash);
            
            Logger::info("Room-specific notification enqueued at {$sendTimeFormatted}: " . ($talk['title'] ?? 'unknown') . " (Room: {$room}, RIC: {$ric})");
            
            return true;

        } catch (\Exception $e) {
            Logger::error("Error sending room-specific notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sends all-rooms notification for a talk
     * 
     * @param array $talk Talk data
     * @return bool true on success
     */
    private function sendAllRoomsNotification(array $talk): bool
    {
        try {
            // Get All-Rooms RIC
            $ric = RoomRicMapper::getAllRoomsRic();

            // Create message payload with All-Rooms RIC
            $payload = MessageFormatter::createHttpMessage($talk, $ric);

            // Get HTTP endpoint
            $endpoint = $this->getHttpEndpoint();

            // Get current time for logging
            $sendTime = $this->getCurrentTime() ?? new \DateTime();
            $sendTimeFormatted = $sendTime->format('Y-m-d H:i:s');

            // Check duplicate for all-rooms message (using messageType)
            $hash = $this->duplicateTracker->createHash($talk, null, 'ALL_ROOMS');
            if ($this->duplicateTracker->isDuplicate($hash)) {
                Logger::info("Duplicate all-rooms message detected, not sent: " . ($talk['title'] ?? 'unknown') . " (RIC: {$ric})");
                return false;
            }

            // Enqueue message for sequential sending with hash for duplicate tracking
            // Hash will be marked as sent only after successful delivery
            $this->messageQueue->enqueue($payload, $endpoint, $hash);
            
            Logger::info("All-rooms notification enqueued at {$sendTimeFormatted}: " . ($talk['title'] ?? 'unknown') . " (RIC: {$ric})");
            
            return true;

        } catch (\Exception $e) {
            Logger::error("Error sending all-rooms notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Returns HTTP endpoint URL
     * 
     * @return string
     */
    private function getHttpEndpoint(): string
    {
        return Config::get('HTTP_ENDPOINT', 'http://192.168.188.21:5000/send');
    }

    /**
     * Gets current time (simulated or real)
     * 
     * If SIMULATE_CURRENT_TIME is set in config, returns that DateTime.
     * Otherwise returns null (caller should use new \DateTime()).
     * 
     * @return \DateTime|null Simulated time or null for real time
     */
    private function getCurrentTime(): ?\DateTime
    {
        $simulateTime = Config::get('SIMULATE_CURRENT_TIME');
        
        if (empty($simulateTime)) {
            return null;
        }

        try {
            $simulatedDateTime = new \DateTime($simulateTime);
            return $simulatedDateTime;
        } catch (\Exception $e) {
            Logger::warning("Invalid SIMULATE_CURRENT_TIME format: {$simulateTime}. Using real time instead.");
            return null;
        }
    }

    /**
     * Logs why a talk was not sent (for debugging in simulation mode)
     * 
     * @param array $talk Talk data
     * @param \DateTime $now Current time
     * @return void
     */
    private function logWhyTalkNotSent(array $talk, \DateTime $now): void
    {
        $talkTitle = $talk['title'] ?? 'unknown';
        
        // Check each condition
        if (empty($talk['title'])) {
            Logger::info("  - {$talkTitle}: No title");
            return;
        }
        
        if (empty($talk['room'])) {
            Logger::info("  - {$talkTitle}: No room");
            return;
        }
        
        if (empty($talk['date'])) {
            Logger::info("  - {$talkTitle}: No date");
            return;
        }
        
        try {
            $talkStart = new \DateTime($talk['date']);
        } catch (\Exception $e) {
            Logger::info("  - {$talkTitle}: Invalid date format");
            return;
        }
        
        // Check if talk is in the past
        if ($talkStart <= $now) {
            $diff = $now->getTimestamp() - $talkStart->getTimestamp();
            $diffMinutes = round($diff / 60);
            Logger::info("  - {$talkTitle}: Talk already started ({$diffMinutes} minutes ago)");
            return;
        }
        
        // Check time window
        $timeUntilTalk = $talkStart->getTimestamp() - $now->getTimestamp();
        $minutesUntilTalk = round($timeUntilTalk / 60);
        
        if ($minutesUntilTalk > $this->notificationMinutes) {
            // Too early - more than 15 minutes before talk (skip logging to reduce spam)
            return;
        }
        
        if ($minutesUntilTalk < 0) {
            // Talk already started
            $diffMinutes = abs($minutesUntilTalk);
            Logger::info("  - {$talkTitle}: Talk already started ({$diffMinutes} minutes ago)");
            return;
        }
        
        // Within notification window - check why it wasn't sent
        // (should only happen if duplicate or other validation failed)
        
        // Check duplicate
        $hash = $this->duplicateTracker->createHash($talk);
        if ($this->duplicateTracker->isDuplicate($hash)) {
            Logger::info("  - {$talkTitle}: Already sent (duplicate)");
            return;
        }
        
        // Should have been sent but wasn't (shouldn't happen)
        Logger::warning("  - {$talkTitle}: Should have been sent but wasn't (unknown reason)");
    }
}
