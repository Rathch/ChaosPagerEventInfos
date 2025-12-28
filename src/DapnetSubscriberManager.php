<?php

namespace ChaosPagerEventInfos;

/**
 * DapnetSubscriberManager - Manages DAPNET Subscribers
 *
 * Checks if subscribers exist and creates them if missing (if permissions allow).
 */
class DapnetSubscriberManager
{
    private string $apiUrl;

    /**
     * Creates a new DAPNET Subscriber Manager
     *
     * @param DapnetApiClient|null $apiClient DAPNET API client (default: new instance)
     */
    public function __construct(?DapnetApiClient $apiClient = null)
    {
        try {
            // Initialize DAPNET API client to validate configuration
            $apiClient ?? new DapnetApiClient();
            $this->apiUrl = Config::getDapnetApiUrl() ?? '';
        } catch (\Exception $e) {
            Logger::error("DapnetSubscriberManager: Failed to initialize DAPNET API client: " . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Checks if a subscriber exists in the DAPNET API
     *
     * @param string $subscriberId Subscriber ID (e.g., "1140", "1141")
     * @return bool True if subscriber exists, false otherwise
     */
    public function checkSubscriber(string $subscriberId): bool
    {
        $endpoint = rtrim($this->apiUrl, '/') . '/subscribers/' . urlencode($subscriberId);

        $username = Config::getDapnetApiUsername() ?? '';
        $password = Config::getDapnetApiPassword() ?? '';

        // Use curl for better error handling and HTTP status code detection
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: ChaosPagerEventInfos/1.0',
            ],
            CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FAILONERROR => false, // Don't fail on HTTP error codes
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false && ! empty($curlError)) {
            Logger::warning("DAPNET API error checking subscriber {$subscriberId}: {$curlError}");

            return false;
        }

        // According to API implementation, GET /subscribers/:id returns HTTP 400 if subscriber doesn't exist
        if ($httpCode === 200) {
            // Success - subscriber exists
            return true;
        } elseif ($httpCode === 400) {
            // Subscriber doesn't exist (API returns 400 with {"message":"subscriber does not exist"})
            return false;
        } else {
            // Other error (401, 403, 500, etc.)
            Logger::warning("DAPNET API error checking subscriber {$subscriberId}: HTTP {$httpCode}");

            return false;
        }
    }

    /**
     * Creates a subscriber in the DAPNET API
     *
     * @param string $subscriberId Subscriber ID (e.g., "1140", "1141")
     * @param string $description Subscriber description
     * @param int $ric Radio Identification Code (e.g., 1140, 1141)
     * @return bool True if subscriber was created successfully, false otherwise
     */
    public function createSubscriber(string $subscriberId, string $description, int $ric): bool
    {
        $endpoint = rtrim($this->apiUrl, '/') . '/subscribers';

        $username = Config::getDapnetApiUsername() ?? '';
        $password = Config::getDapnetApiPassword() ?? '';

        // Build subscriber payload (matching the shell example format)
        $subscriber = [
            '_id' => $subscriberId,
            'description' => $description,
            'groups' => [],
            'owners' => ['admin'],
            'pagers' => [
                [
                    'charset' => 'DE',
                    'enabled' => true,
                    'name' => 'Skyper2',
                    'ric' => $ric,
                    'sub_ric' => 3,
                    'type' => 'skyper',
                ],
            ],
            'third_party_services' => [
                'aprs' => [],
                'brandmeister' => [],
                'email' => [],
                'hamstatus' => [],
                'ipsc2' => [],
                'tetra_svx' => [],
                'tmo_services' => [],
            ],
        ];

        $jsonPayload = json_encode($subscriber, JSON_UNESCAPED_SLASHES);

        if ($jsonPayload === false) {
            Logger::error("JSON encoding failed for subscriber creation");

            return false;
        }

        // Use curl for better error handling and HTTP status code detection
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: ChaosPagerEventInfos/1.0',
            ],
            CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FAILONERROR => false, // Don't fail on HTTP error codes
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false && ! empty($curlError)) {
            Logger::error("DAPNET API subscriber creation failed: {$endpoint} - {$curlError}");

            return false;
        }

        // According to API implementation:
        // - HTTP 201: Subscriber created or updated
        // - HTTP 200: Subscriber exists, no changes needed
        // - HTTP 400: Bad request (e.g., validation error)
        // - HTTP 403: Insufficient permissions
        if ($httpCode === 201) {
            Logger::info("DAPNET subscriber created/updated successfully: {$subscriberId}");

            return true;
        } elseif ($httpCode === 200) {
            Logger::info("DAPNET subscriber already exists with same content: {$subscriberId}");

            return true;
        } elseif ($httpCode === 400) {
            $errorMsg = $response !== false ? (json_decode($response, true)['message'] ?? 'Bad request') : 'Bad request';
            Logger::error("DAPNET subscriber creation failed: HTTP 400 - {$errorMsg}");

            return false;
        } elseif ($httpCode === 403) {
            Logger::error("DAPNET subscriber creation failed: Insufficient permissions (HTTP 403). Admin/support credentials required.");

            return false;
        } else {
            Logger::error("DAPNET subscriber creation failed: HTTP {$httpCode}");

            return false;
        }
    }

    /**
     * Sets up all configured subscribers
     *
     * Checks if all configured subscribers exist and creates missing ones (if permissions allow).
     *
     * @return array{checked: int, created: int, errors: array<int, string>} Result array
     */
    public function setupSubscribers(): array
    {
        $result = [
            'checked' => 0,
            'created' => 0,
            'errors' => [],
        ];

        // Get all configured RICs and their subscribers
        $rics = [1140, 1141, 1142, 1143, 1150];

        foreach ($rics as $ric) {
            $subscriberId = Config::getSubscriberForRic($ric);

            if ($subscriberId === null) {
                Logger::warning("No subscriber mapping configured for RIC {$ric}, skipping");

                continue;
            }

            $result['checked']++;

            // Check if subscriber exists
            if ($this->checkSubscriber($subscriberId)) {
                Logger::info("DAPNET subscriber {$subscriberId} (RIC {$ric}) exists");

                continue;
            }

            // Subscriber doesn't exist - try to create it
            Logger::info("DAPNET subscriber {$subscriberId} (RIC {$ric}) not found, attempting to create...");

            $description = "Eventinfos RIC {$ric}";
            if ($this->createSubscriber($subscriberId, $description, $ric)) {
                $result['created']++;
                Logger::info("DAPNET subscriber {$subscriberId} (RIC {$ric}) created successfully");
            } else {
                $error = "Failed to create subscriber {$subscriberId} (RIC {$ric}). Check permissions (admin/support required).";
                $result['errors'][] = $error;
                Logger::error($error);
            }
        }

        return $result;
    }
}
