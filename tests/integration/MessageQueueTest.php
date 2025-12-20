<?php

namespace ChaosPagerEventInfos\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ChaosPagerEventInfos\MessageQueue;
use ChaosPagerEventInfos\QueuedMessage;
use ChaosPagerEventInfos\MockHttpClient;
use ChaosPagerEventInfos\RealHttpClient;
use ChaosPagerEventInfos\Config;
use ChaosPagerEventInfos\Logger;

/**
 * Integration tests for MessageQueue
 */
class MessageQueueTest extends TestCase
{
    private string $testEnvFile;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create temporary .env file for testing
        $this->testEnvFile = sys_get_temp_dir() . '/test-env-' . uniqid() . '.env';
        file_put_contents($this->testEnvFile, "LOG_FILE=/tmp/test.log\nQUEUE_DELAY_SECONDS=1\nQUEUE_MAX_RETRIES=3\nQUEUE_RETRY_DELAY_SECONDS=1\n");
        
        // Initialize Config with test file
        Config::load($this->testEnvFile);
        Logger::init();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testEnvFile)) {
            @unlink($this->testEnvFile);
        }
        parent::tearDown();
    }

    /**
     * Test enqueue() adds messages to queue
     */
    public function testEnqueueAddsMessagesToQueue(): void
    {
        $mockHttpClient = new MockHttpClient();
        $queue = new MessageQueue($mockHttpClient);
        
        $payload1 = ['RIC' => 1141, 'MSG' => 'Test 1', 'm_type' => 'AlphaNum', 'm_func' => 'Func3'];
        $payload2 = ['RIC' => 1142, 'MSG' => 'Test 2', 'm_type' => 'AlphaNum', 'm_func' => 'Func3'];
        
        $queue->enqueue($payload1, 'http://test.endpoint/send');
        $this->assertEquals(1, $queue->getQueueSize());
        
        $queue->enqueue($payload2, 'http://test.endpoint/send');
        $this->assertEquals(2, $queue->getQueueSize());
    }

    /**
     * Test sequential sending of messages
     */
    public function testSequentialSending(): void
    {
        $mockHttpClient = new MockHttpClient();
        $queue = new MessageQueue($mockHttpClient);
        
        $payload1 = ['RIC' => 1141, 'MSG' => 'Test 1', 'm_type' => 'AlphaNum', 'm_func' => 'Func3'];
        $payload2 = ['RIC' => 1142, 'MSG' => 'Test 2', 'm_type' => 'AlphaNum', 'm_func' => 'Func3'];
        $payload3 = ['RIC' => 1143, 'MSG' => 'Test 3', 'm_type' => 'AlphaNum', 'm_func' => 'Func3'];
        
        $queue->enqueue($payload1, 'http://test.endpoint/send');
        $queue->enqueue($payload2, 'http://test.endpoint/send');
        $queue->enqueue($payload3, 'http://test.endpoint/send');
        
        $this->assertEquals(3, $queue->getQueueSize());
        
        // Process queue
        $queue->process();
        
        // All messages should be sent
        $this->assertEquals(0, $queue->getQueueSize());
        $this->assertFalse($queue->isProcessing());
        
        // Verify messages were sent (check MockHttpClient)
        $sentMessages = $mockHttpClient->getSentMessages();
        $this->assertCount(3, $sentMessages);
    }

    /**
     * Test HTTP 429 handling with retry
     */
    public function testHttp429Handling(): void
    {
        // Create a mock client that simulates HTTP 429
        $mockClient = new class extends MockHttpClient {
            private int $callCount = 0;
            
            public function sendPostWithStatus(string $endpoint, array $payload): array
            {
                $this->callCount++;
                // First call returns 429, second call succeeds
                if ($this->callCount === 1) {
                    return ['success' => false, 'statusCode' => 429];
                }
                return ['success' => true, 'statusCode' => 200];
            }
        };
        
        $queue = new MessageQueue($mockClient);
        
        $payload = ['RIC' => 1141, 'MSG' => 'Test 429', 'm_type' => 'AlphaNum', 'm_func' => 'Func3'];
        $queue->enqueue($payload, 'http://test.endpoint/send');
        
        // Process queue - should retry on 429
        $queue->process();
        
        // Message should eventually be sent (after retry)
        $this->assertEquals(0, $queue->getQueueSize());
    }

    /**
     * Test retry mechanism for other errors (HTTP 500)
     */
    public function testRetryMechanismForOtherErrors(): void
    {
        // Create a mock client that simulates HTTP 500
        $mockClient = new class extends MockHttpClient {
            private int $callCount = 0;
            
            public function sendPostWithStatus(string $endpoint, array $payload): array
            {
                $this->callCount++;
                // First call returns 500, second call succeeds
                if ($this->callCount === 1) {
                    return ['success' => false, 'statusCode' => 500];
                }
                return ['success' => true, 'statusCode' => 200];
            }
        };
        
        $queue = new MessageQueue($mockClient);
        
        $payload = ['RIC' => 1141, 'MSG' => 'Test 500', 'm_type' => 'AlphaNum', 'm_func' => 'Func3'];
        $queue->enqueue($payload, 'http://test.endpoint/send');
        
        // Process queue - should retry on 500
        $queue->process();
        
        // Message should eventually be sent (after retry)
        $this->assertEquals(0, $queue->getQueueSize());
    }

    /**
     * Test max retry attempts
     */
    public function testMaxRetryAttempts(): void
    {
        // Create a mock client that always fails
        $mockClient = new class extends MockHttpClient {
            public function sendPostWithStatus(string $endpoint, array $payload): array
            {
                // Always return 429
                return ['success' => false, 'statusCode' => 429];
            }
        };
        
        $queue = new MessageQueue($mockClient);
        
        $payload = ['RIC' => 1141, 'MSG' => 'Test Max Retry', 'm_type' => 'AlphaNum', 'm_func' => 'Func3'];
        $queue->enqueue($payload, 'http://test.endpoint/send');
        
        // Process queue - should retry up to max retries, then fail
        $queue->process();
        
        // Queue should be empty (message failed after max retries)
        $this->assertEquals(0, $queue->getQueueSize());
    }

    /**
     * Test configuration from .env file
     */
    public function testConfigurationFromEnv(): void
    {
        // Create temporary .env file with custom values
        $testEnvFile = sys_get_temp_dir() . '/test-env-' . uniqid() . '.env';
        file_put_contents($testEnvFile, "QUEUE_DELAY_SECONDS=10\nQUEUE_MAX_RETRIES=5\nQUEUE_RETRY_DELAY_SECONDS=2\n");
        
        // Load config
        Config::load($testEnvFile);
        
        $mockHttpClient = new MockHttpClient();
        $queue = new MessageQueue($mockHttpClient);
        
        // Queue should use configured values (we can't directly test delay, but we can verify config is loaded)
        // The configuration is loaded in constructor, so queue is created with correct values
        $this->assertNotNull($queue);
        
        // Cleanup
        @unlink($testEnvFile);
    }

    /**
     * Test default values when configuration is missing
     */
    public function testDefaultValuesWhenConfigurationMissing(): void
    {
        // Create temporary .env file without queue configuration
        $testEnvFile = sys_get_temp_dir() . '/test-env-' . uniqid() . '.env';
        file_put_contents($testEnvFile, "LOG_FILE=/tmp/test.log\n");
        
        // Load config
        Config::load($testEnvFile);
        
        $mockHttpClient = new MockHttpClient();
        $queue = new MessageQueue($mockHttpClient);
        
        // Queue should use default values (we can't directly test, but queue should work)
        $this->assertNotNull($queue);
        
        // Cleanup
        @unlink($testEnvFile);
    }

    /**
     * Test delay between messages
     * 
     * Verifies that messages are sent with the configured delay between them.
     * For 3 messages, there should be 2 delays (between msg1-msg2 and msg2-msg3).
     */
    public function testDelayBetweenMessages(): void
    {
        // Create temporary .env file with short delay for testing (0.5 seconds)
        $testEnvFile = sys_get_temp_dir() . '/test-env-' . uniqid() . '.env';
        file_put_contents($testEnvFile, "LOG_FILE=/tmp/test.log\nQUEUE_DELAY_SECONDS=1\nQUEUE_MAX_RETRIES=3\nQUEUE_RETRY_DELAY_SECONDS=1\n");
        
        // Load config
        Config::load($testEnvFile);
        
        $mockHttpClient = new MockHttpClient();
        $queue = new MessageQueue($mockHttpClient);
        
        // Enqueue 3 messages
        $payload1 = ['RIC' => 1141, 'MSG' => 'Test Delay 1', 'm_type' => 'AlphaNum', 'm_func' => 'Func3'];
        $payload2 = ['RIC' => 1142, 'MSG' => 'Test Delay 2', 'm_type' => 'AlphaNum', 'm_func' => 'Func3'];
        $payload3 = ['RIC' => 1143, 'MSG' => 'Test Delay 3', 'm_type' => 'AlphaNum', 'm_func' => 'Func3'];
        
        $queue->enqueue($payload1, 'http://test.endpoint/send');
        $queue->enqueue($payload2, 'http://test.endpoint/send');
        $queue->enqueue($payload3, 'http://test.endpoint/send');
        
        $this->assertEquals(3, $queue->getQueueSize());
        
        // Measure time before processing
        $startTime = microtime(true);
        
        // Process queue - should take at least 2 seconds (2 delays of 1 second each)
        $queue->process();
        
        // Measure time after processing
        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;
        
        // Verify all messages were sent
        $this->assertEquals(0, $queue->getQueueSize());
        $this->assertFalse($queue->isProcessing());
        
        // Verify messages were sent
        $sentMessages = $mockHttpClient->getSentMessages();
        $this->assertCount(3, $sentMessages);
        
        // Verify delay was applied: For 3 messages, there should be 2 delays
        // Expected time: at least 2 seconds (2 delays × 1 second each)
        // We allow some tolerance (0.5 seconds) for execution overhead
        $expectedMinTime = 1.5; // 2 delays × 1 second - 0.5 tolerance
        $this->assertGreaterThanOrEqual(
            $expectedMinTime,
            $elapsedTime,
            "Processing should take at least {$expectedMinTime} seconds (2 delays of 1 second each), but took {$elapsedTime} seconds"
        );
        
        // Cleanup
        @unlink($testEnvFile);
    }
}
