<?php

namespace ChaosPagerEventInfos;

/**
 * RealHttpClient - Real HTTP POST implementation using native PHP
 * 
 * Uses file_get_contents with stream_context for HTTP POST requests.
 */
class RealHttpClient implements HttpClientInterface
{
    /**
     * Sends HTTP POST request with JSON payload
     * 
     * @param string $endpoint HTTP URL
     * @param array $payload Message payload
     * @return bool true on success, false on error
     */
    public function sendPost(string $endpoint, array $payload): bool
    {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($jsonPayload === false) {
            Logger::error("JSON encoding failed for HTTP payload");
            return false;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonPayload),
                    'User-Agent: ChaosPagerEventInfos/1.0'
                ],
                'content' => $jsonPayload,
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($endpoint, false, $context);

        if ($response === false) {
            $error = error_get_last();
            Logger::error("HTTP POST request failed: {$endpoint} - " . ($error['message'] ?? 'Unknown error'));
            return false;
        }

        // Check HTTP status code
        if (isset($http_response_header)) {
            $statusLine = $http_response_header[0];
            if (preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $statusLine, $matches)) {
                $statusCode = (int)$matches[1];
                
                if ($statusCode >= 200 && $statusCode < 300) {
                    Logger::info("HTTP POST request successful: {$endpoint} (Status: {$statusCode})");
                    return true;
                } else {
                    Logger::error("HTTP POST request failed: {$endpoint} (Status: {$statusCode})");
                    return false;
                }
            }
        }

        Logger::info("HTTP POST request completed: {$endpoint}");
        return true;
    }
}
