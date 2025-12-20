#!/usr/bin/env php
<?php

/**
 * notify.php - CLI entry point for event pager notifications
 * 
 * Executed via cronjob every 5 minutes.
 * 
 * Usage: php bin/notify.php
 */

// Use Composer autoloader if available, otherwise use manual requires
$autoloadFile = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadFile)) {
    require_once $autoloadFile;
} else {
    // Manual autoloader (without Composer for MVP)
    require_once __DIR__ . '/../src/Config.php';
    require_once __DIR__ . '/../src/Logger.php';
    require_once __DIR__ . '/../src/ApiClient.php';
    require_once __DIR__ . '/../src/TalkFilter.php';
    require_once __DIR__ . '/../src/DuplicateTracker.php';
    require_once __DIR__ . '/../src/MessageFormatter.php';
    require_once __DIR__ . '/../src/RoomRicMapper.php';
    require_once __DIR__ . '/../src/QueuedMessage.php';
    require_once __DIR__ . '/../src/MessageQueue.php';
    require_once __DIR__ . '/../src/HttpClientInterface.php';
    require_once __DIR__ . '/../src/MockHttpClient.php';
    require_once __DIR__ . '/../src/RealHttpClient.php';
    require_once __DIR__ . '/../src/HttpClient.php';
    require_once __DIR__ . '/../src/EventPagerNotifier.php';
}

use ChaosPagerEventInfos\Config;
use ChaosPagerEventInfos\Logger;
use ChaosPagerEventInfos\EventPagerNotifier;

// Error handling
set_error_handler(function ($severity, $message, $file, $line) {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

try {
    // Load configuration
    Config::load();
    
    // Initialize logger
    Logger::init();
    
    Logger::info("=== Event Pager Notifications Script started ===");
    
    // Execute notification process
    $notifier = new EventPagerNotifier();
    $sentCount = $notifier->run();
    
    Logger::info("=== Script completed successfully ===");
    exit(0);

} catch (\Exception $e) {
    // Error handling
    if (class_exists('ChaosPagerEventInfos\Logger')) {
        Logger::error("Script error: " . $e->getMessage());
        Logger::error("Stack Trace: " . $e->getTraceAsString());
    } else {
        error_log("Event Pager Notifications Error: " . $e->getMessage());
    }
    
    exit(1);
}
