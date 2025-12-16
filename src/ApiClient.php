<?php

namespace ChaosPagerEventInfos;

/**
 * ApiClient - Loads event data from CCC API
 * 
 * Uses native PHP functions (file_get_contents with stream_context).
 */
class ApiClient
{
    private string $apiUrl;

    public function __construct(?string $apiUrl = null)
    {
        if ($apiUrl === null) {
            $apiUrl = Config::get('API_URL');
            if (empty($apiUrl)) {
                throw new \RuntimeException("API_URL not configured. Please set API_URL in .env file.");
            }
        }
        $this->apiUrl = $apiUrl;
    }

    /**
     * Fetches events from API
     * 
     * @return array Array of talk data
     * @throws \RuntimeException On HTTP errors or invalid JSON
     */
    public function fetchEvents(): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ChaosPagerEventInfos/1.0',
                    'Accept: application/json'
                ],
                'timeout' => 30
            ]
        ]);

        $response = @file_get_contents($this->apiUrl, false, $context);

        if ($response === false) {
            $error = error_get_last();
            Logger::error("API request failed: {$this->apiUrl} - " . ($error['message'] ?? 'Unknown error'));
            throw new \RuntimeException("API request failed: {$this->apiUrl}");
        }

        // Check HTTP status code
        if (isset($http_response_header)) {
            $statusLine = $http_response_header[0];
            if (preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $statusLine, $matches)) {
                $statusCode = (int)$matches[1];
                
                if ($statusCode === 404) {
                    Logger::error("API endpoint not found (404): {$this->apiUrl}");
                    throw new \RuntimeException("API endpoint not found (404)");
                }
                
                if ($statusCode >= 500) {
                    Logger::error("API server error ({$statusCode}): {$this->apiUrl}");
                    throw new \RuntimeException("API server error ({$statusCode})");
                }
                
                if ($statusCode === 429) {
                    Logger::error("API rate limiting (429): {$this->apiUrl}");
                    throw new \RuntimeException("API rate limiting (429)");
                }
            }
        }

        // Parse JSON
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::error("JSON parsing error: " . json_last_error_msg());
            throw new \RuntimeException("Invalid JSON format: " . json_last_error_msg());
        }

        // Extract events from API structure
        // Structure: schedule.conference.days[].rooms['RoomName'] = [events...]
        if (!isset($data['schedule']['conference']['days'])) {
            Logger::warning("API response does not contain days structure");
            return [];
        }

        $allEvents = [];
        $days = $data['schedule']['conference']['days'];

        foreach ($days as $day) {
            if (!isset($day['rooms']) || !is_array($day['rooms'])) {
                continue;
            }

            // rooms is a dictionary where key is room name and value is array of events
            foreach ($day['rooms'] as $roomName => $events) {
                if (!is_array($events)) {
                    continue;
                }

                // Add room name to each event if not already present
                foreach ($events as $event) {
                    if (!isset($event['room'])) {
                        $event['room'] = $roomName;
                    }
                    $allEvents[] = $event;
                }
            }
        }

        Logger::info("Extracted " . count($allEvents) . " events from API");
        return $allEvents;
    }
}
