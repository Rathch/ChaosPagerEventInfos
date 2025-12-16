<?php

namespace ChaosPagerEventInfos\Tests\Integration;

use ChaosPagerEventInfos\ApiClient;
use ChaosPagerEventInfos\Config;
use ChaosPagerEventInfos\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ApiClient
 *
 * Tests API request, JSON parsing, and error handling.
 */
class ApiClientTest extends TestCase
{
    private string $testEnvFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary .env file for testing
        $this->testEnvFile = sys_get_temp_dir() . '/test-env-' . uniqid() . '.env';
        file_put_contents($this->testEnvFile, "API_URL=https://api.events.ccc.de/congress/2025/schedule.json\nLOG_FILE=/tmp/test.log\n");

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
     * Test successful API fetch
     */
    public function testFetchEventsSuccess(): void
    {
        $client = new ApiClient();

        try {
            $events = $client->fetchEvents();

            // Should return an array
            $this->assertIsArray($events);

            // If events exist, check structure
            if (! empty($events)) {
                $firstEvent = $events[0];
                $this->assertIsArray($firstEvent);
                $this->assertArrayHasKey('id', $firstEvent);
                $this->assertArrayHasKey('title', $firstEvent);
                $this->assertArrayHasKey('date', $firstEvent);
            }
        } catch (\Exception $e) {
            // If API is not available, skip test
            $this->markTestSkipped("API not available: " . $e->getMessage());
        }
    }

    /**
     * Test API client with invalid URL
     */
    public function testFetchEventsInvalidUrl(): void
    {
        $client = new ApiClient('https://invalid-url-that-does-not-exist-12345.com/api.json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API request failed');

        $client->fetchEvents();
    }

    /**
     * Test API client with custom URL
     */
    public function testFetchEventsCustomUrl(): void
    {
        $customUrl = 'https://api.events.ccc.de/congress/2025/schedule.json';
        $client = new ApiClient($customUrl);

        $this->assertEquals($customUrl, $this->getPrivateProperty($client, 'apiUrl'));
    }

    /**
     * Helper method to access private properties for testing
     */
    private function getPrivateProperty(object $object, string $propertyName)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
