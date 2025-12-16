<?php

namespace ChaosPagerEventInfos;

/**
 * MockWebSocketClient - Simulates WebSocket connection for MVP
 *
 * Logs messages instead of actually sending them.
 * Activated via WEBSOCKET_MODE=simulate.
 */
class MockWebSocketClient implements WebSocketClientInterface
{
    private bool $connected = false;
    private ?string $currentEndpoint = null;

    /**
     * Establishes simulated connection
     *
     * @param string $endpoint WebSocket URL
     * @return bool Always true (simulation)
     */
    public function connect(string $endpoint): bool
    {
        $this->currentEndpoint = $endpoint;
        $this->connected = true;
        Logger::info("WebSocket connection simulated: {$endpoint}");

        return true;
    }

    /**
     * Simulates message sending (only logs)
     *
     * @param array $message Message in WebSocket format
     * @return bool Always true (simulation)
     */
    public function send(array $message): bool
    {
        if (! $this->connected) {
            Logger::warning("WebSocket not connected, simulating connection...");
            $this->connected = true;
        }

        $jsonMessage = json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        Logger::info("WebSocket message (simulated):\n{$jsonMessage}");

        return true;
    }

    /**
     * Disconnects simulated connection
     *
     * @return void
     */
    public function disconnect(): void
    {
        if ($this->connected && $this->currentEndpoint) {
            Logger::info("WebSocket connection disconnected (simulated): {$this->currentEndpoint}");
        }
        $this->connected = false;
        $this->currentEndpoint = null;
    }
}
