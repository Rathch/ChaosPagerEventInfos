<?php

namespace ChaosPagerEventInfos;

/**
 * QueuedMessage - Represents a single message in the queue
 * 
 * Stores message payload, endpoint, retry count, and status.
 */
class QueuedMessage
{
    private array $payload;
    private string $endpoint;
    private int $retryCount;
    private string $status;
    private \DateTime $createdAt;
    private ?\DateTime $lastAttemptAt;
    private ?string $duplicateHash;

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_RETRYING = 'retrying';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    /**
     * Creates a new queued message
     * 
     * @param array $payload HTTP POST payload (RIC, MSG, m_type, m_func)
     * @param string $endpoint HTTP endpoint URL
     * @param int $retryCount Initial retry count (default: 0)
     * @param string $status Initial status (default: 'pending')
     * @param string|null $duplicateHash Optional duplicate tracker hash for marking as sent after successful delivery
     */
    public function __construct(
        array $payload,
        string $endpoint,
        int $retryCount = 0,
        string $status = self::STATUS_PENDING,
        ?string $duplicateHash = null
    ) {
        $this->payload = $payload;
        $this->endpoint = $endpoint;
        $this->retryCount = $retryCount;
        $this->status = $status;
        $this->createdAt = new \DateTime();
        $this->lastAttemptAt = null;
        $this->duplicateHash = $duplicateHash;
    }

    /**
     * Gets the message payload
     * 
     * @return array HTTP POST payload
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * Gets the endpoint URL
     * 
     * @return string HTTP endpoint URL
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Gets the current retry count
     * 
     * @return int Number of retry attempts
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * Increments the retry count
     * 
     * @return void
     */
    public function incrementRetry(): void
    {
        $this->retryCount++;
        $this->lastAttemptAt = new \DateTime();
    }

    /**
     * Gets the current status
     * 
     * @return string Current status
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Sets the status
     * 
     * @param string $status New status
     * @return void
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
        if ($status === self::STATUS_SENDING || $status === self::STATUS_RETRYING) {
            $this->lastAttemptAt = new \DateTime();
        }
    }

    /**
     * Gets the creation timestamp
     * 
     * @return \DateTime Creation timestamp
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Gets the last attempt timestamp
     * 
     * @return \DateTime|null Last attempt timestamp or null if never attempted
     */
    public function getLastAttemptAt(): ?\DateTime
    {
        return $this->lastAttemptAt;
    }

    /**
     * Gets the duplicate tracker hash
     * 
     * @return string|null Duplicate tracker hash or null if not set
     */
    public function getDuplicateHash(): ?string
    {
        return $this->duplicateHash;
    }
}
