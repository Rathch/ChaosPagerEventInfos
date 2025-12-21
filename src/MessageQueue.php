<?php

namespace ChaosPagerEventInfos;

/**
 * MessageQueue - In-memory queue for sequential message sending
 * 
 * Processes messages one at a time with configurable delay between messages.
 * Handles retry logic for failed messages.
 */
class MessageQueue
{
    private array $messages = [];
    private bool $isProcessing = false;
    private int $delaySeconds;
    private int $maxRetries;
    private int $retryDelaySeconds;
    private HttpClientInterface $httpClient;

    // Default values
    private const DEFAULT_DELAY_SECONDS = 5;
    private const DEFAULT_MAX_RETRIES = 3;
    private const DEFAULT_RETRY_DELAY_SECONDS = 5;

    /**
     * Creates a new message queue
     * 
     * @param HttpClientInterface|null $httpClient HTTP client for sending messages
     */
    public function __construct(?HttpClientInterface $httpClient = null)
    {
        $this->httpClient = $httpClient ?? HttpClient::create();
        
        // Load configuration from .env
        $this->delaySeconds = $this->getConfigInt('QUEUE_DELAY_SECONDS', self::DEFAULT_DELAY_SECONDS);
        $this->maxRetries = $this->getConfigInt('QUEUE_MAX_RETRIES', self::DEFAULT_MAX_RETRIES);
        $this->retryDelaySeconds = $this->getConfigInt('QUEUE_RETRY_DELAY_SECONDS', self::DEFAULT_RETRY_DELAY_SECONDS);
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
     * Adds a message to the queue
     * 
     * @param array $payload HTTP POST payload
     * @param string $endpoint HTTP endpoint URL
     * @param string|null $duplicateHash Optional duplicate tracker hash for marking as sent after successful delivery
     * @return void
     */
    public function enqueue(array $payload, string $endpoint, ?string $duplicateHash = null): void
    {
        $message = new QueuedMessage($payload, $endpoint, 0, QueuedMessage::STATUS_PENDING, $duplicateHash);
        $this->messages[] = $message;
        
        Logger::info("Message enqueued: " . ($payload['MSG'] ?? 'unknown') . " (Queue size: " . count($this->messages) . ")");
    }

    /**
     * Processes the queue sequentially
     * 
     * Sends messages one at a time with delay between successful sends.
     * Processes all messages in the queue synchronously.
     * 
     * @return array Array of duplicate tracker hashes for successfully sent messages
     */
    public function process(): array
    {
        if ($this->isProcessing) {
            return []; // Already processing
        }

        $this->isProcessing = true;
        $successfulHashes = [];

        while (!empty($this->messages)) {
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
            if ($message->getStatus() === QueuedMessage::STATUS_SENT && !empty($this->messages)) {
                sleep($this->delaySeconds);
            }
        }

        $this->isProcessing = false;
        return $successfulHashes;
    }

    /**
     * Sends the next message from the queue
     * 
     * @param QueuedMessage $message Message to send
     * @return void
     */
    private function sendNext(QueuedMessage $message): void
    {
        $message->setStatus(QueuedMessage::STATUS_SENDING);
        
        $payload = $message->getPayload();
        $endpoint = $message->getEndpoint();

        // Use sendPostWithStatus if available (RealHttpClient or MockHttpClient), otherwise use sendPost
        $success = false;
        $statusCode = null;
        
        if (method_exists($this->httpClient, 'sendPostWithStatus')) {
            $result = $this->httpClient->sendPostWithStatus($endpoint, $payload);
            $success = $result['success'];
            $statusCode = $result['statusCode'] ?? null;
        } else {
            $success = $this->httpClient->sendPost($endpoint, $payload);
        }

        if ($success && ($statusCode === null || ($statusCode >= 200 && $statusCode < 300))) {
            // Success
            $message->setStatus(QueuedMessage::STATUS_SENT);
            Logger::info("Message sent successfully: " . ($payload['MSG'] ?? 'unknown') . " (RIC: " . ($payload['RIC'] ?? 'unknown') . ")");
        } else {
            // Failed - check if we should retry
            $this->handleFailedMessage($message, $statusCode);
        }
    }

    /**
     * Handles a failed message (retry or mark as failed)
     * 
     * @param QueuedMessage $message Failed message
     * @param int|null $statusCode HTTP status code (if available)
     * @return void
     */
    private function handleFailedMessage(QueuedMessage $message, ?int $statusCode): void
    {
        $retryCount = $message->getRetryCount();
        
        if ($retryCount < $this->maxRetries) {
            // Retry
            $message->incrementRetry();
            $message->setStatus(QueuedMessage::STATUS_RETRYING);
            
            $payload = $message->getPayload();
            Logger::info("Message failed, retrying ({$retryCount}/{$this->maxRetries}): " . ($payload['MSG'] ?? 'unknown') . 
                        ($statusCode !== null ? " (Status: {$statusCode})" : ""));
            
            // Wait before retry
            sleep($this->retryDelaySeconds);
            
            // Re-queue for retry
            array_unshift($this->messages, $message);
        } else {
            // Max retries reached - mark as failed
            $message->setStatus(QueuedMessage::STATUS_FAILED);
            $payload = $message->getPayload();
            Logger::error("Message failed after {$this->maxRetries} retry attempts: " . ($payload['MSG'] ?? 'unknown') . 
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
}
