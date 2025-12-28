<?php

namespace ChaosPagerEventInfos;

/**
 * MessageQueue - In-memory queue for sequential DAPNET call sending
 *
 * Processes DAPNET calls one at a time with configurable delay between calls.
 * Handles retry logic for failed calls.
 */
class MessageQueue
{
    /** @var array<int, QueuedMessage> */
    private array $messages = [];
    private bool $isProcessing = false;
    private int $delaySeconds;
    private int $maxRetries;
    private int $retryDelaySeconds;
    private int $allRoomsDelaySeconds;

    // Default values
    private const DEFAULT_DELAY_SECONDS = 5;
    private const DEFAULT_MAX_RETRIES = 3;
    private const DEFAULT_RETRY_DELAY_SECONDS = 5;
    private const DEFAULT_ALL_ROOMS_DELAY_SECONDS = 3; // Additional delay between all-rooms messages

    /**
     * Creates a new message queue
     */
    public function __construct()
    {
        // Load configuration from .env
        $this->delaySeconds = $this->getConfigInt('QUEUE_DELAY_SECONDS', self::DEFAULT_DELAY_SECONDS);
        $this->maxRetries = $this->getConfigInt('QUEUE_MAX_RETRIES', self::DEFAULT_MAX_RETRIES);
        $this->retryDelaySeconds = $this->getConfigInt('QUEUE_RETRY_DELAY_SECONDS', self::DEFAULT_RETRY_DELAY_SECONDS);
        $this->allRoomsDelaySeconds = $this->getConfigInt('QUEUE_ALL_ROOMS_DELAY_SECONDS', self::DEFAULT_ALL_ROOMS_DELAY_SECONDS);
    }

    /**
     * Gets integer configuration value with validation
     *
     * @param string $key Configuration key
     * @param int $default Default value
     * @return int Validated integer value
     */
    private function getConfigInt(string $key, int $default): int
    {
        $value = Config::get($key);

        if ($value === null) {
            return $default;
        }

        $intValue = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($intValue === false) {
            Logger::warning("Invalid configuration value for '{$key}': '{$value}'. Using default value {$default}.");

            return $default;
        }

        return $intValue;
    }

    /**
     * Adds a DAPNET call to the queue
     *
     * @param array<string, mixed> $payload DAPNET Call format (must contain 'data', 'subscribers', 'transmitter_groups')
     * @param string|null $duplicateHash Optional duplicate tracker hash for marking as sent after successful delivery
     * @return void
     */
    public function enqueue(array $payload, ?string $duplicateHash = null): void
    {
        // Dummy endpoint (not used, DAPNET API URL is used instead)
        $message = new QueuedMessage($payload, '', 0, QueuedMessage::STATUS_PENDING, $duplicateHash);
        $this->messages[] = $message;

        Logger::info("DAPNET call enqueued: " . ($payload['data'] ?? 'unknown') . " (Queue size: " . count($this->messages) . ")");
    }

    /**
     * Processes the queue sequentially
     *
     * Sends messages one at a time with delay between successful sends.
     * Processes all messages in the queue synchronously.
     *
     * @return array<int, string> Array of duplicate tracker hashes for successfully sent messages
     */
    public function process(): array
    {
        if ($this->isProcessing) {
            return []; // Already processing
        }

        $this->isProcessing = true;
        $successfulHashes = [];

        while (! empty($this->messages)) {
            /** @var QueuedMessage|null $message */
            $message = array_shift($this->messages);

            if ($message === null) {
                break;
            }

            $this->sendNext($message);

            // If message was successfully sent, collect its hash for duplicate tracking
            if ($message->getStatus() === QueuedMessage::STATUS_SENT) {
                $hash = $message->getDuplicateHash();
                if ($hash !== null) {
                    $successfulHashes[] = $hash;
                }
            }

            // Delay between messages (only if message was successfully sent and there are more messages)
            if ($message->getStatus() === QueuedMessage::STATUS_SENT && ! empty($this->messages)) {
                $delay = $this->delaySeconds;
                
                // Check if current message is for all-rooms subscriber (1150)
                $payload = $message->getPayload();
                $isAllRoomsMessage = $this->isAllRoomsMessage($payload);
                
                // If current message is for all-rooms, check if next message is also for all-rooms
                if ($isAllRoomsMessage) {
                    $nextMessage = $this->messages[0] ?? null;
                    if ($nextMessage !== null) {
                        $nextPayload = $nextMessage->getPayload();
                        if ($this->isAllRoomsMessage($nextPayload)) {
                            // Both messages are for all-rooms subscriber - add extra delay
                            $delay += $this->allRoomsDelaySeconds;
                            Logger::info("Adding extra delay ({$this->allRoomsDelaySeconds}s) between all-rooms messages");
                        }
                    }
                }
                
                sleep($delay);
            }
        }

        $this->isProcessing = false;

        return $successfulHashes;
    }

    /**
     * Sends the next DAPNET call from the queue
     *
     * @param QueuedMessage $message Message to send
     * @return void
     */
    private function sendNext(QueuedMessage $message): void
    {
        $message->setStatus(QueuedMessage::STATUS_SENDING);

        $payload = $message->getPayload();

        // Validate that this is a DAPNET call
        if (! isset($payload['data']) || ! isset($payload['subscribers']) || ! isset($payload['transmitter_groups'])) {
            Logger::error("Invalid DAPNET call format in queue");
            $message->setStatus(QueuedMessage::STATUS_FAILED);

            return;
        }

        // Use DapnetApiClient for DAPNET calls
        try {
            $dapnetClient = new DapnetApiClient();
            $result = $dapnetClient->sendCall($payload);
            $success = $result['success'];
            $statusCode = $result['statusCode'] ?? null;
        } catch (\Exception $e) {
            Logger::error("DAPNET API client error: " . $e->getMessage());
            $success = false;
            $statusCode = null;
        }

        if ($success && ($statusCode === null || ($statusCode >= 200 && $statusCode < 300))) {
            // Success
            $message->setStatus(QueuedMessage::STATUS_SENT);
            // $payload['data'] is validated above
            Logger::info("DAPNET call sent successfully: " . $payload['data']);
        } else {
            // Failed - check if we should retry
            $this->handleFailedMessage($message, $statusCode);
        }
    }

    /**
     * Handles a failed DAPNET call (retry or mark as failed)
     *
     * @param QueuedMessage $message Failed message
     * @param int|null $statusCode HTTP status code (if available)
     * @return void
     */
    private function handleFailedMessage(QueuedMessage $message, ?int $statusCode): void
    {
        $payload = $message->getPayload();

        // HTTP 423 (Resource Conflict) - no retry, mark as failed immediately
        if ($statusCode === 423) {
            $message->setStatus(QueuedMessage::STATUS_FAILED);
            Logger::error("DAPNET call failed (HTTP 423 - Resource Conflict, no retry): " . ($payload['data'] ?? 'unknown'));

            return;
        }

        // HTTP 429 (Rate Limiting) - retry with longer delay
        if ($statusCode === 429) {
            $retryCount = $message->getRetryCount();

            if ($retryCount < $this->maxRetries) {
                // Retry with longer delay for rate limiting
                $message->incrementRetry();
                $message->setStatus(QueuedMessage::STATUS_RETRYING);

                Logger::warning("DAPNET call rate limited, retrying ({$retryCount}/{$this->maxRetries}): " . ($payload['data'] ?? 'unknown'));

                // Wait longer before retry for rate limiting (double the normal delay)
                sleep($this->retryDelaySeconds * 2);

                // Re-queue for retry
                array_unshift($this->messages, $message);

                return;
            }
        }

        // Other errors - standard retry logic
        $retryCount = $message->getRetryCount();

        if ($retryCount < $this->maxRetries) {
            // Retry
            $message->incrementRetry();
            $message->setStatus(QueuedMessage::STATUS_RETRYING);

            Logger::info("DAPNET call failed, retrying ({$retryCount}/{$this->maxRetries}): " . ($payload['data'] ?? 'unknown') .
                        ($statusCode !== null ? " (Status: {$statusCode})" : ""));

            // Wait before retry
            sleep($this->retryDelaySeconds);

            // Re-queue for retry
            array_unshift($this->messages, $message);
        } else {
            // Max retries reached - mark as failed
            $message->setStatus(QueuedMessage::STATUS_FAILED);
            Logger::error("DAPNET call failed after {$this->maxRetries} retry attempts: " . ($payload['data'] ?? 'unknown') .
                         ($statusCode !== null ? " (Status: {$statusCode})" : ""));
        }
    }

    /**
     * Gets the current queue size
     *
     * @return int Number of messages in queue
     */
    public function getQueueSize(): int
    {
        return count($this->messages);
    }

    /**
     * Checks if queue is currently processing
     *
     * @return bool True if processing, false otherwise
     */
    public function isProcessing(): bool
    {
        return $this->isProcessing;
    }

    /**
     * Checks if a message is for the all-rooms subscriber (1150)
     *
     * @param array<string, mixed> $payload DAPNET call payload
     * @return bool True if message is for all-rooms subscriber
     */
    private function isAllRoomsMessage(array $payload): bool
    {
        $subscribers = $payload['subscribers'] ?? [];
        
        // Check if subscriber 1150 is in the subscribers array (as string or integer)
        return in_array('1150', $subscribers, true) || in_array(1150, $subscribers, true);
    }

}
