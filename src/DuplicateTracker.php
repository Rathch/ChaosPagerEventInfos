<?php

namespace ChaosPagerEventInfos;

/**
 * DuplicateTracker - Prevents duplicate notifications
 *
 * Uses hash list in temporary file with timestamps.
 * Format: hash|timestamp (one per line)
 * Hash = md5(talkId + startTime + room)
 * Entries older than 3 hours are automatically removed.
 */
class DuplicateTracker
{
    private string $hashFile;
    /** @var array<string, int> Format: ['hash' => timestamp] */
    private array $hashes = [];
    private bool $loaded = false;
    private const CLEANUP_HOURS = 3;

    public function __construct(?string $hashFile = null)
    {
        $this->hashFile = $hashFile ?? Config::get('SENT_HASHES_FILE', '/tmp/event-pager-sent-hashes.txt');
    }

    /**
     * Loads hash list from file
     *
     * @return void
     */
    private function loadHashes(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->hashes = [];

        if (! file_exists($this->hashFile)) {
            $this->loaded = true;

            return;
        }

        $content = @file_get_contents($this->hashFile);
        if ($content === false) {
            Logger::warning("Could not read hash file: {$this->hashFile}");
            $this->loaded = true;

            return;
        }

        $now = time();
        $cutoffTime = $now - (self::CLEANUP_HOURS * 3600);
        $lines = explode("\n", trim($content));
        $hasChanges = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Parse hash|timestamp format
            $parts = explode('|', $line, 2);
            $hash = $parts[0];

            if (isset($parts[1])) {
                $timestamp = (int)$parts[1];

                // Skip entries older than 3 hours
                if ($timestamp < $cutoffTime) {
                    $hasChanges = true;

                    continue;
                }

                $this->hashes[$hash] = $timestamp;
            } else {
                // Legacy format (no timestamp) - treat as old and skip
                $hasChanges = true;
            }
        }

        // Rewrite file if old entries were removed
        if ($hasChanges) {
            $this->saveHashes();
        }

        $this->loaded = true;
    }

    /**
     * Saves hash list to file
     *
     * @return void
     */
    private function saveHashes(): void
    {
        $dir = dirname($this->hashFile);
        if (! is_dir($dir) && $dir !== '.' && $dir !== '') {
            @mkdir($dir, 0755, true);
        }

        $content = '';
        foreach ($this->hashes as $hash => $timestamp) {
            $content .= $hash . '|' . $timestamp . PHP_EOL;
        }

        $result = @file_put_contents($this->hashFile, $content, LOCK_EX);

        if ($result === false) {
            Logger::warning("Could not write hash file: {$this->hashFile}");
        }
    }

    /**
     * Creates hash for a talk
     *
     * @param array<string, mixed> $talk Talk data
     * @param int|null $ric Optional RIC for separate hash generation (for room-specific vs all-rooms)
     * @param string|null $messageType Optional message type ("ROOM_SPECIFIC" or "ALL_ROOMS") for separate hash generation
     * @return string Hash string
     */
    public function createHash(array $talk, ?int $ric = null, ?string $messageType = null): string
    {
        $id = $talk['id'] ?? '';
        $date = $talk['date'] ?? '';
        $room = $talk['room'] ?? '';

        // If RIC or messageType is provided, include it in hash for separate tracking
        if ($ric !== null) {
            return md5($id . $date . $room . $ric);
        }

        if ($messageType !== null) {
            return md5($id . $date . $room . $messageType);
        }

        // Default hash (backward compatible)
        return md5($id . $date . $room);
    }

    /**
     * Checks if hash already exists (message already sent)
     *
     * @param string $hash Hash string
     * @return bool
     */
    public function isDuplicate(string $hash): bool
    {
        $this->loadHashes();

        if (! isset($this->hashes[$hash])) {
            return false;
        }

        // Check if entry is older than 3 hours
        $timestamp = $this->hashes[$hash];
        $now = time();
        $cutoffTime = $now - (self::CLEANUP_HOURS * 3600);

        if ($timestamp < $cutoffTime) {
            // Entry is too old, remove it
            unset($this->hashes[$hash]);
            $this->saveHashes();

            return false;
        }

        return true;
    }

    /**
     * Marks hash as sent
     *
     * @param string $hash Hash string
     * @return void
     */
    public function markAsSent(string $hash): void
    {
        $this->loadHashes();

        $now = time();

        // Update timestamp if already present, or add new entry
        if (isset($this->hashes[$hash])) {
            // Already exists, just update timestamp
            $this->hashes[$hash] = $now;
            $this->saveHashes();

            return;
        }

        // Add new entry
        $this->hashes[$hash] = $now;

        // Append to file
        $dir = dirname($this->hashFile);
        if (! is_dir($dir) && $dir !== '.' && $dir !== '') {
            @mkdir($dir, 0755, true);
        }

        $result = @file_put_contents($this->hashFile, $hash . '|' . $now . PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            Logger::warning("Could not write hash to file: {$this->hashFile}");
        }
    }

    /**
     * Cleans up old hashes (older than 3 hours)
     *
     * Removes entries older than 3 hours from the hash file.
     *
     * @return void
     */
    /**
     * Cleans up old hashes (older than 3 hours)
     *
     * Removes entries older than 3 hours from the hash file.
     *
     * @return void
     */
    public function cleanup(): void
    {
        if (! file_exists($this->hashFile)) {
            return;
        }

        // Load current hashes to get count before cleanup
        $this->loadHashes();
        $initialCount = count($this->hashes);

        // Reload hashes (this already removes old entries during load)
        $this->loaded = false;
        $this->loadHashes();
        $finalCount = count($this->hashes);

        // Also check file size - if too large (>1MB), clean up completely
        if (file_exists($this->hashFile)) {
            $size = filesize($this->hashFile);
            if ($size > 1024 * 1024) {
                Logger::info("Hash file too large ({$size} bytes), cleaning up completely...");
                @unlink($this->hashFile);
                $this->hashes = [];
                $this->loaded = false;
            } elseif ($initialCount > $finalCount) {
                $removedCount = $initialCount - $finalCount;
                Logger::info("Cleaned up {$removedCount} old hash entries (older than " . self::CLEANUP_HOURS . " hours)");
            }
        }
    }
}
