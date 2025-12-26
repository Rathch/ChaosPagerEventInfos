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
        $auth = base64_encode($username . ':' . $password);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Basic ' . $auth,
                    'User-Agent: ChaosPagerEventInfos/1.0',
                ],
                'timeout' => 5,
            ],
        ]);

        $response = @file_get_contents($endpoint, false, $context);

        if ($response === false) {
            // Check if it's a 404 (subscriber doesn't exist) vs other error
            if (! empty($http_response_header)) {
                $statusLine = $http_response_header[0];
                if (preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $statusLine, $matches)) {
                    $statusCode = (int)$matches[1];
                    if ($statusCode === 404) {
                        return false; // Subscriber doesn't exist
                    }
                    // Other error (401, 403, 500, etc.)
                    Logger::warning("DAPNET API error checking subscriber {$subscriberId}: HTTP {$statusCode}");

                    return false;
                }
            }

            return false;
        }

        // Success - subscriber exists
        return true;
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
        $auth = base64_encode($username . ':' . $password);

        // Build subscriber payload
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

        $context = stream_context_create([
            'http' => [
                'method' => 'PUT',
                'header' => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonPayload),
                    'Authorization: Basic ' . $auth,
                    'User-Agent: ChaosPagerEventInfos/1.0',
                ],
                'content' => $jsonPayload,
                'timeout' => 5,
            ],
        ]);

        $response = @file_get_contents($endpoint, false, $context);

        if ($response === false) {
            $error = error_get_last();
            Logger::error("DAPNET API subscriber creation failed: {$endpoint} - " . ($error['message'] ?? 'Unknown error'));

            return false;
        }

        // Check HTTP status code
        if (! empty($http_response_header)) {
            $statusLine = $http_response_header[0];
            if (preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $statusLine, $matches)) {
                $statusCode = (int)$matches[1];

                if ($statusCode === 201) {
                    Logger::info("DAPNET subscriber created successfully: {$subscriberId}");

                    return true;
                } elseif ($statusCode === 403) {
                    Logger::error("DAPNET subscriber creation failed: Insufficient permissions (HTTP 403). Admin/support credentials required.");

                    return false;
                } else {
                    Logger::error("DAPNET subscriber creation failed: HTTP {$statusCode}");

                    return false;
                }
            }
        }

        return false;
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
