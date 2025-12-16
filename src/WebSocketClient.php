<?php

namespace ChaosPagerEventInfos;

/**
 * WebSocketClient - Factory for WebSocket client implementations
 *
 * Creates mock or real WebSocket client instance based on configuration.
 */
class WebSocketClient
{
    /**
     * Creates WebSocket client based on configuration
     *
     * @return WebSocketClientInterface Mock or real implementation
     */
    public static function create(): WebSocketClientInterface
    {
        $mode = Config::get('WEBSOCKET_MODE', 'simulate');

        if ($mode === 'simulate') {
            return new MockWebSocketClient();
        }

        // Later: Real implementation with textalk/websocket-php
        // return new RealWebSocketClient();

        Logger::warning("WEBSOCKET_MODE={$mode} not supported, using simulation");

        return new MockWebSocketClient();
    }
}
