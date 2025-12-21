<?php

namespace ChaosPagerEventInfos;

/**
 * MockHttpClient - Simulates HTTP POST request for MVP
 * 
 * Logs messages instead of actually sending them.
 * Activated via HTTP_MODE=simulate.
 */
class MockHttpClient implements HttpClientInterface
{
    private array $sentMessages = [];

    /**
     * Simulates HTTP POST request (only logs)
     * 
     * @param string $endpoint HTTP URL
     * @param array $payload Message payload
     * @return bool Always true (simulation)
     */
    public function sendPost(string $endpoint, array $payload): bool
    {
        $jsonPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        Logger::info("HTTP POST request (simulated):\nEndpoint: {$endpoint}\nPayload:\n{$jsonPayload}");
        
        // Store sent message for testing
        $this->sentMessages[] = $payload;
        
        return true;
    }

    /**
     * Simulates HTTP POST request with status code (for testing)
     * 
     * @param string $endpoint HTTP URL
     * @param array $payload Message payload
     * @return array Result array with 'success' and 'statusCode'
     */
    public function sendPostWithStatus(string $endpoint, array $payload): array
    {
        $jsonPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        Logger::info("HTTP POST request (simulated):\nEndpoint: {$endpoint}\nPayload:\n{$jsonPayload}");
        
        // Store sent message for testing
        $this->sentMessages[] = $payload;
        
        // Always return success in mock
        return ['success' => true, 'statusCode' => 200];
    }

    /**
     * Gets all sent messages (for testing)
     * 
     * @return array Array of sent message payloads
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * Clears sent messages (for testing)
     * 
     * @return void
     */
    public function clearSentMessages(): void
    {
        $this->sentMessages = [];
    }
}
