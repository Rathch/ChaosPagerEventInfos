<?php

namespace ChaosPagerEventInfos;

/**
 * HttpClientInterface - Interface for HTTP client implementations
 * 
 * Defines the contract for mock and real HTTP POST implementations.
 */
interface HttpClientInterface
{
    /**
     * Sends HTTP POST request with JSON payload
     * 
     * @param string $endpoint HTTP URL (e.g. "http://192.168.188.21:5000/send")
     * @param array $payload Message payload in format ['RIC' => int, 'MSG' => string, 'm_type' => string, 'm_func' => string]
     * @return bool true on success, false on error
     */
    public function sendPost(string $endpoint, array $payload): bool;
}
