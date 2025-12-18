<?php

namespace ChaosPagerEventInfos;

/**
 * HttpClient - Factory for HTTP client implementations
 * 
 * Creates mock or real HTTP client instance based on configuration.
 */
class HttpClient
{
    /**
     * Creates HTTP client based on configuration
     * 
     * @return HttpClientInterface Mock or real implementation
     */
    public static function create(): HttpClientInterface
    {
        $mode = Config::get('HTTP_MODE', 'simulate');

        if ($mode === 'simulate') {
            return new MockHttpClient();
        }

        // Real implementation using native PHP stream_context
        if ($mode === 'real') {
            return new RealHttpClient();
        }
        
        Logger::warning("HTTP_MODE={$mode} not supported, using simulation");
        return new MockHttpClient();
    }
}
