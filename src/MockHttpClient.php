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
        
        return true;
    }
}
