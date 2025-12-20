<?php

namespace ChaosPagerEventInfos\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ChaosPagerEventInfos\EventPagerNotifier;
use ChaosPagerEventInfos\ApiClient;
use ChaosPagerEventInfos\DuplicateTracker;
use ChaosPagerEventInfos\MockHttpClient;
use ChaosPagerEventInfos\MessageQueue;
use ChaosPagerEventInfos\Config;
use ChaosPagerEventInfos\Logger;

/**
 * Integration tests for EventPagerNotifier
 * 
 * Tests the double message sending (room-specific + all-rooms)
 */
class EventPagerNotifierTest extends TestCase
{
    private string $testEnvFile;
    private string $testHashFile;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary .env file for testing
        $this->testEnvFile = sys_get_temp_dir() . '/test-env-' . uniqid() . '.env';
        file_put_contents($this->testEnvFile, "API_URL=https://api.example.com\nROOM_RIC_ZERO=1140\nROOM_RIC_ONE=1141\nROOM_RIC_GROUND=1142\nROOM_RIC_FUSE=1143\nROOM_RIC_ALL_ROOMS=1150\nLOG_FILE=/tmp/test.log\nHTTP_MODE=simulate\n");
        
        // Create temporary hash file
        $this->testHashFile = sys_get_temp_dir() . '/test-hashes-' . uniqid() . '.txt';
        
        // Initialize Config with test file
        Config::load($this->testEnvFile);
        Logger::init();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testEnvFile)) {
            @unlink($this->testEnvFile);
        }
        if (file_exists($this->testHashFile)) {
            @unlink($this->testHashFile);
        }
        parent::tearDown();
    }

    /**
     * Test that both room-specific and all-rooms notifications are sent
     * 
     * This test verifies that for a single talk, two messages are sent:
     * 1. Room-specific message (e.g., RIC 1141 for Room "One")
     * 2. All-rooms message (RIC 1150)
     */
    public function testDoubleMessageSending(): void
    {
        // Create mock HTTP client to capture sent messages
        $mockHttpClient = new MockHttpClient();
        
        // Create notifier with mock client
        $duplicateTracker = new DuplicateTracker($this->testHashFile);
        $messageQueue = new MessageQueue($mockHttpClient);
        $notifier = new EventPagerNotifier(
            null, // ApiClient (not used in this test)
            $duplicateTracker,
            $mockHttpClient,
            $messageQueue, // MessageQueue
            15 // notificationMinutes
        );

        // Create a test talk in Room "One" starting in 10 minutes
        $futureTime = new \DateTime('+10 minutes');
        $talk = [
            'id' => 'test-talk-1',
            'title' => 'Test Talk',
            'date' => $futureTime->format('c'),
            'room' => 'One'
        ];

        // Use reflection to call sendNotification (private method)
        $reflection = new \ReflectionClass($notifier);
        $method = $reflection->getMethod('sendNotification');
        $method->setAccessible(true);
        
        $result = $method->invoke($notifier, $talk);

        // Verify that sendNotification returned true (both messages sent)
        $this->assertTrue($result, 'Both notifications should be sent successfully');
        
        // Process the queue to actually send the messages
        $queueMethod = $reflection->getProperty('messageQueue');
        $queueMethod->setAccessible(true);
        $messageQueue = $queueMethod->getValue($notifier);
        $messageQueue->process();

        // Get sent messages from mock client
        $sentMessages = $mockHttpClient->getSentMessages();
        
        // Verify that exactly 2 messages were sent
        $this->assertCount(2, $sentMessages, 'Exactly 2 messages should be sent (room-specific + all-rooms)');

        // Verify first message is room-specific (RIC 1141 for Room "One")
        $roomSpecificMessage = $sentMessages[0];
        $this->assertArrayHasKey('RIC', $roomSpecificMessage);
        $this->assertEquals(1141, $roomSpecificMessage['RIC'], 'First message should be room-specific with RIC 1141');
        $this->assertArrayHasKey('MSG', $roomSpecificMessage);
        $this->assertStringContainsString('One', $roomSpecificMessage['MSG']);

        // Verify second message is all-rooms (RIC 1150)
        $allRoomsMessage = $sentMessages[1];
        $this->assertArrayHasKey('RIC', $allRoomsMessage);
        $this->assertEquals(1150, $allRoomsMessage['RIC'], 'Second message should be all-rooms with RIC 1150');
        $this->assertArrayHasKey('MSG', $allRoomsMessage);
        $this->assertStringContainsString('One', $allRoomsMessage['MSG']);
    }

    /**
     * Test that messages are only marked as sent after successful delivery
     * 
     * This test verifies that if a message fails to send (after all retries),
     * it is NOT marked as sent in the duplicate tracker, allowing it to be
     * retried on the next script run.
     */
    public function testFailedMessagesNotMarkedAsSent(): void
    {
        // Create a mock HTTP client that always fails
        $failingHttpClient = new class extends MockHttpClient {
            public function sendPostWithStatus(string $endpoint, array $payload): array
            {
                // Always return failure (simulating network error or server failure)
                return ['success' => false, 'statusCode' => 500];
            }
        };
        
        $duplicateTracker = new DuplicateTracker($this->testHashFile);
        $messageQueue = new MessageQueue($failingHttpClient);
        $notifier = new EventPagerNotifier(
            null,
            $duplicateTracker,
            $failingHttpClient,
            $messageQueue,
            15
        );

        // Create a test talk
        $futureTime = new \DateTime('+10 minutes');
        $talk = [
            'id' => 'test-failed-talk',
            'title' => 'Failed Talk',
            'date' => $futureTime->format('c'),
            'room' => 'One'
        ];

        // Use reflection to call sendNotification
        $reflection = new \ReflectionClass($notifier);
        $method = $reflection->getMethod('sendNotification');
        $method->setAccessible(true);
        
        $result = $method->invoke($notifier, $talk);
        
        // sendNotification should return true (messages were enqueued)
        $this->assertTrue($result);
        
        // Process the queue - all messages will fail after retries
        $queueProperty = $reflection->getProperty('messageQueue');
        $queueProperty->setAccessible(true);
        $messageQueue = $queueProperty->getValue($notifier);
        $successfulHashes = $messageQueue->process();
        
        // No messages should be marked as sent (all failed)
        $this->assertEmpty($successfulHashes, 'No messages should be marked as sent if they failed');
        
        // Verify that the message is NOT in the duplicate tracker
        // (so it can be retried on next script run)
        $roomHash = $duplicateTracker->createHash($talk, 1141); // RIC for Room "One"
        $allRoomsHash = $duplicateTracker->createHash($talk, null, 'ALL_ROOMS');
        
        $this->assertFalse(
            $duplicateTracker->isDuplicate($roomHash),
            'Room-specific message should NOT be marked as sent if delivery failed'
        );
            $this->assertFalse(
                $duplicateTracker->isDuplicate($allRoomsHash),
                'All-rooms message should NOT be marked as sent if delivery failed'
            );
    }

    /**
     * Test that run() returns actual number of successfully sent messages, not enqueued talks
     * 
     * This test verifies that if messages are enqueued but fail to send,
     * the return value reflects the actual number of successfully sent messages (0),
     * not the number of talks for which messages were enqueued.
     */
    public function testRunReturnsActualSentMessageCount(): void
    {
        // Create a mock HTTP client that always fails
        $failingHttpClient = new class extends MockHttpClient {
            public function sendPostWithStatus(string $endpoint, array $payload): array
            {
                // Always return failure
                return ['success' => false, 'statusCode' => 500];
            }
        };
        
        // Create a mock API client that extends ApiClient
        $mockApiClient = new class extends ApiClient {
            public function fetchEvents(): array
            {
                $futureTime = new \DateTime('+10 minutes');
                return [
                    [
                        'id' => 'test-talk-1',
                        'title' => 'Test Talk 1',
                        'date' => $futureTime->format('c'),
                        'room' => 'One'
                    ],
                    [
                        'id' => 'test-talk-2',
                        'title' => 'Test Talk 2',
                        'date' => $futureTime->format('c'),
                        'room' => 'Zero'
                    ]
                ];
            }
        };
        
        $duplicateTracker = new DuplicateTracker($this->testHashFile);
        $messageQueue = new MessageQueue($failingHttpClient);
        $notifier = new EventPagerNotifier(
            $mockApiClient,
            $duplicateTracker,
            $failingHttpClient,
            $messageQueue,
            15
        );

        // Enable test mode to send notifications regardless of time
        $reflection = new \ReflectionClass($notifier);
        $testModeProperty = $reflection->getProperty('testMode');
        $testModeProperty->setAccessible(true);
        $testModeProperty->setValue($notifier, true);
        
        // Run the notification process
        $result = $notifier->run();
        
        // Result should be 0 (no messages successfully sent), not 2 (number of talks enqueued)
        // Each talk would send 2 messages (room-specific + all-rooms), so 2 talks = 4 messages enqueued
        // But all fail, so result should be 0
        $this->assertEquals(0, $result, 'run() should return 0 when no messages are successfully sent, even if messages were enqueued');
    }
}
