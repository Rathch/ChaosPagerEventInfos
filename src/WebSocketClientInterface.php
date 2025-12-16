<?php

namespace ChaosPagerEventInfos;

/**
 * WebSocketClientInterface - Interface for WebSocket client implementations
 * 
 * Defines the contract for mock and real WebSocket implementations.
 */
interface WebSocketClientInterface
{
    /**
     * Connects to WebSocket endpoint
     * 
     * @param string $endpoint WebSocket URL (e.g. "ws://localhost:8055")
     * @return bool true on success, false on error
     */
    public function connect(string $endpoint): bool;

    /**
     * Sends message over WebSocket
     * 
     * @param array $message Message in format ['SendMessage' => [...]]
     * @return bool true on success, false on error
     */
    public function send(array $message): bool;

    /**
     * Disconnects WebSocket connection
     * 
     * @return void
     */
    public function disconnect(): void;
}
