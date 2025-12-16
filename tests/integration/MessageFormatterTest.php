<?php

namespace ChaosPagerEventInfos\Tests\Integration;

use ChaosPagerEventInfos\Config;
use ChaosPagerEventInfos\Logger;
use ChaosPagerEventInfos\MessageFormatter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for MessageFormatter
 *
 * Tests message formatting and JSON sanitization.
 */
class MessageFormatterTest extends TestCase
{
    private string $testEnvFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary .env file for testing
        $this->testEnvFile = sys_get_temp_dir() . '/test-env-' . uniqid() . '.env';
        file_put_contents($this->testEnvFile, "RIC=1142\nLOG_FILE=/tmp/test.log\n");

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
     * Test message formatting
     */
    public function testFormatMessage(): void
    {
        $talk = [
            'title' => 'Test Talk',
            'date' => '2025-12-27T14:00:00+01:00',
            'room' => 'One',
        ];

        $message = MessageFormatter::formatMessage($talk);

        $this->assertStringContainsString('Test Talk', $message);
        $this->assertStringContainsString('14:00', $message);
        $this->assertStringContainsString('One', $message);
        $this->assertStringContainsString('|', $message);
    }

    /**
     * Test WebSocket message creation
     */
    public function testCreateWebSocketMessage(): void
    {
        $talk = [
            'title' => 'Test Talk',
            'date' => '2025-12-27T14:00:00+01:00',
            'room' => 'One',
        ];

        $message = MessageFormatter::createWebSocketMessage($talk);

        $this->assertIsArray($message);
        $this->assertArrayHasKey('SendMessage', $message);
        $this->assertEquals(1142, $message['SendMessage']['addr']);
        $this->assertEquals('AlphaNum', $message['SendMessage']['mtype']);
        $this->assertEquals('Func3', $message['SendMessage']['func']);
        $this->assertArrayHasKey('data', $message['SendMessage']);
        $this->assertStringContainsString('Test Talk', $message['SendMessage']['data']);
    }

    /**
     * Test WebSocket message with custom RIC
     */
    public function testCreateWebSocketMessageCustomRic(): void
    {
        $talk = [
            'title' => 'Test Talk',
            'date' => '2025-12-27T14:00:00+01:00',
            'room' => 'One',
        ];

        $message = MessageFormatter::createWebSocketMessage($talk, 9999);

        $this->assertEquals(9999, $message['SendMessage']['addr']);
    }

    /**
     * Test message format with special characters
     */
    public function testFormatMessageSpecialCharacters(): void
    {
        $talk = [
            'title' => 'Test Talk with "quotes" & special chars',
            'date' => '2025-12-27T14:00:00+01:00',
            'room' => 'One',
        ];

        $message = MessageFormatter::formatMessage($talk);

        // Should handle special characters
        $this->assertIsString($message);
        $this->assertNotEmpty($message);
    }

    /**
     * Test WebSocket message JSON encoding
     */
    public function testCreateWebSocketMessageJsonEncoding(): void
    {
        $talk = [
            'title' => 'Test Talk',
            'date' => '2025-12-27T14:00:00+01:00',
            'room' => 'One',
        ];

        $message = MessageFormatter::createWebSocketMessage($talk);

        // Should be JSON-encodable
        $json = json_encode($message);
        $this->assertNotFalse($json);

        // Should be valid JSON
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertEquals($message, $decoded);
    }

    /**
     * Test time extraction from ISO-8601 format
     */
    public function testTimeExtraction(): void
    {
        $talk = [
            'title' => 'Test Talk',
            'date' => '2025-12-27T14:30:00+01:00',
            'room' => 'One',
        ];

        $message = MessageFormatter::formatMessage($talk);

        $this->assertStringContainsString('14:30', $message);
    }

    /**
     * Test with invalid date format
     */
    public function testInvalidDateFormat(): void
    {
        $talk = [
            'title' => 'Test Talk',
            'date' => 'invalid-date',
            'room' => 'One',
        ];

        // Should not throw exception, but return default time
        $message = MessageFormatter::formatMessage($talk);
        $this->assertIsString($message);
        $this->assertStringContainsString('Test Talk', $message);
    }
}
