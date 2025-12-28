<?php

namespace ChaosPagerEventInfos;

/**
 * DapnetApiClient - Client for DAPNET v2 API integration
 *
 * Sends DAPNET calls (messages) to the DAPNET API using HTTP POST requests.
 * Uses curl for reliable HTTP requests with proper error handling.
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

        // Debug logging: Log payload for troubleshooting (especially for subscriber 1150)
        $subscribers = $call['subscribers'] ?? [];
        if (in_array('1150', $subscribers, true) || in_array(1150, $subscribers, true)) {
            Logger::info("DAPNET call payload for subscriber 1150: " . $jsonPayload);
        }

        // Use curl for better error handling and HTTP status code detection
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: ChaosPagerEventInfos/1.0',
            ],
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FAILONERROR => false, // Don't fail on HTTP error codes
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false && ! empty($curlError)) {
            Logger::error("DAPNET API POST request failed: {$endpoint} - {$curlError}");

            return ['success' => false, 'statusCode' => null, 'error' => $curlError];
        }

        // Check HTTP status code
        if ($httpCode === 201) {
            // Success: Call created
            Logger::info("DAPNET API call sent successfully: {$endpoint} (Status: {$httpCode})");

            return ['success' => true, 'statusCode' => $httpCode];
        } elseif ($httpCode >= 200 && $httpCode < 300) {
            // Other success codes
            Logger::info("DAPNET API call completed: {$endpoint} (Status: {$httpCode})");

            return ['success' => true, 'statusCode' => $httpCode];
        } else {
            // Error handling for specific status codes
            $errorMessage = "HTTP {$httpCode}";

            if ($httpCode === 400) {
                $errorMsg = $response !== false ? (json_decode($response, true)['message'] ?? 'Invalid request format') : 'Invalid request format';
                Logger::error("DAPNET API validation error: {$endpoint} (Status: {$httpCode}) - {$errorMsg}");
                $errorMessage = "Validation error: {$errorMsg}";
            } elseif ($httpCode === 401 || $httpCode === 403) {
                Logger::error("DAPNET API authentication error: {$endpoint} (Status: {$httpCode}) - Invalid credentials");
                $errorMessage = "Authentication error: Invalid credentials";
            } elseif ($httpCode === 423) {
                Logger::error("DAPNET API resource conflict: {$endpoint} (Status: {$httpCode}) - Resource conflict");
                $errorMessage = "Resource conflict: Call already exists";
            } elseif ($httpCode === 429) {
                Logger::warning("DAPNET API rate limit exceeded: {$endpoint} (Status: {$httpCode}) - Rate limiting");
                $errorMessage = "Rate limit exceeded";
            } elseif ($httpCode >= 500) {
                $errorMsg = $response !== false ? (json_decode($response, true)['message'] ?? $response) : 'Server error';
                Logger::error("DAPNET API server error: {$endpoint} (Status: {$httpCode}) - {$errorMsg}");
                $errorMessage = "Server error: HTTP {$httpCode} - {$errorMsg}";
            } else {
                $errorMsg = $response !== false ? (json_decode($response, true)['message'] ?? $response) : 'Unknown error';
                Logger::error("DAPNET API call failed: {$endpoint} (Status: {$httpCode}) - {$errorMsg}");
                $errorMessage = "HTTP {$httpCode}: {$errorMsg}";
            }

            return ['success' => false, 'statusCode' => $httpCode, 'error' => $errorMessage];
        }
    }
}
