<?php

namespace ChaosPagerEventInfos;

/**
 * DapnetApiClient - Client for DAPNET v2 API integration
 *
 * Sends DAPNET calls (messages) to the DAPNET API using HTTP POST requests.
 * Uses native PHP file_get_contents with stream_context for HTTP requests.
 */
class DapnetApiClient
{
    private string $apiUrl;
    private string $username;
    private string $password;
    private int $timeout;

    /**
     * Creates a new DAPNET API client
     *
     * @param string|null $apiUrl DAPNET API URL (default: from Config)
     * @param string|null $username HTTP Basic Auth Username (default: from Config)
     * @param string|null $password HTTP Basic Auth Password (default: from Config)
     * @param int $timeout Request timeout in seconds (default: 5)
     */
    public function __construct(?string $apiUrl = null, ?string $username = null, ?string $password = null, int $timeout = 5)
    {
        $this->apiUrl = $apiUrl ?? Config::getDapnetApiUrl() ?? '';
        $this->username = $username ?? Config::getDapnetApiUsername() ?? '';
        $this->password = $password ?? Config::getDapnetApiPassword() ?? '';
        $this->timeout = $timeout;

        if (empty($this->apiUrl)) {
            throw new \RuntimeException('DAPNET_API_URL is not configured');
        }

        if (empty($this->username) || empty($this->password)) {
            Logger::error('DAPNET_API_USERNAME and DAPNET_API_PASSWORD must be configured');

            throw new \RuntimeException('DAPNET_API_USERNAME and DAPNET_API_PASSWORD must be configured');
        }
    }

    /**
     * Sends a DAPNET call to the API
     *
     * @param array<string, mixed> $call DAPNET call data (must match DAPNET Call Format)
     * @return array{success: bool, statusCode: int|null, error?: string} Result array
     */
    public function sendCall(array $call): array
    {
        $endpoint = rtrim($this->apiUrl, '/') . '/calls';

        $jsonPayload = json_encode($call, JSON_UNESCAPED_SLASHES);

        if ($jsonPayload === false) {
            Logger::error("JSON encoding failed for DAPNET call");

            return ['success' => false, 'statusCode' => null, 'error' => 'JSON encoding failed'];
        }

        // Create HTTP Basic Auth header
        $auth = base64_encode($this->username . ':' . $this->password);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonPayload),
                    'Authorization: Basic ' . $auth,
                    'User-Agent: ChaosPagerEventInfos/1.0',
                ],
                'content' => $jsonPayload,
                'timeout' => $this->timeout,
            ],
        ]);

        $response = @file_get_contents($endpoint, false, $context);

        if ($response === false) {
            $error = error_get_last();
            Logger::error("DAPNET API POST request failed: {$endpoint} - " . ($error['message'] ?? 'Unknown error'));

            return ['success' => false, 'statusCode' => null, 'error' => $error['message'] ?? 'Network error'];
        }

        // Check HTTP status code
        $statusCode = null;
        if (! empty($http_response_header)) {
            $statusLine = $http_response_header[0];
            if (preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $statusLine, $matches)) {
                $statusCode = (int)$matches[1];

                if ($statusCode === 201) {
                    // Success: Call created
                    Logger::info("DAPNET API call sent successfully: {$endpoint} (Status: {$statusCode})");

                    return ['success' => true, 'statusCode' => $statusCode];
                } elseif ($statusCode >= 200 && $statusCode < 300) {
                    // Other success codes
                    Logger::info("DAPNET API call completed: {$endpoint} (Status: {$statusCode})");

                    return ['success' => true, 'statusCode' => $statusCode];
                } else {
                    // Error handling for specific status codes
                    $errorMessage = "HTTP {$statusCode}";

                    if ($statusCode === 400) {
                        Logger::error("DAPNET API validation error: {$endpoint} (Status: {$statusCode}) - " . ($response ?: 'Invalid request format'));
                        $errorMessage = "Validation error: " . ($response ?: 'Invalid request format');
                    } elseif ($statusCode === 401 || $statusCode === 403) {
                        Logger::error("DAPNET API authentication error: {$endpoint} (Status: {$statusCode}) - Invalid credentials");
                        $errorMessage = "Authentication error: Invalid credentials";
                    } elseif ($statusCode === 423) {
                        Logger::error("DAPNET API resource conflict: {$endpoint} (Status: {$statusCode}) - Resource conflict");
                        $errorMessage = "Resource conflict: Call already exists";
                    } elseif ($statusCode === 429) {
                        Logger::warning("DAPNET API rate limit exceeded: {$endpoint} (Status: {$statusCode}) - Rate limiting");
                        $errorMessage = "Rate limit exceeded";
                    } elseif ($statusCode >= 500) {
                        Logger::error("DAPNET API server error: {$endpoint} (Status: {$statusCode}) - Server error");
                        $errorMessage = "Server error: HTTP {$statusCode}";
                    } else {
                        Logger::error("DAPNET API call failed: {$endpoint} (Status: {$statusCode})");
                    }

                    return ['success' => false, 'statusCode' => $statusCode, 'error' => $errorMessage];
                }
            }
        }

        Logger::info("DAPNET API call completed: {$endpoint}");

        return ['success' => true, 'statusCode' => $statusCode];
    }
}
