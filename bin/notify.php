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
    require_once __DIR__ . '/../src/AsciiSanitizer.php';
    require_once __DIR__ . '/../src/DapnetCallFormatter.php';
    require_once __DIR__ . '/../src/DapnetApiClient.php';
    require_once __DIR__ . '/../src/DapnetSubscriberManager.php';
    require_once __DIR__ . '/../src/EventPagerNotifier.php';
}

use ChaosPagerEventInfos\Config;
use ChaosPagerEventInfos\DapnetSubscriberManager;
use ChaosPagerEventInfos\EventPagerNotifier;
use ChaosPagerEventInfos\Logger;

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

    // Setup DAPNET subscribers if DAPNET is configured
    if (Config::getDapnetApiUrl() !== null) {
        try {
            Logger::info("DAPNET API configured, checking subscribers...");
            $subscriberManager = new DapnetSubscriberManager();
            $setupResult = $subscriberManager->setupSubscribers();

            Logger::info("DAPNET subscriber setup completed: " .
                        "checked={$setupResult['checked']}, " .
                        "created={$setupResult['created']}, " .
                        "errors=" . count($setupResult['errors']));

            if (! empty($setupResult['errors'])) {
                foreach ($setupResult['errors'] as $error) {
                    Logger::warning($error);
                }
            }
        } catch (\Exception $e) {
            Logger::warning("DAPNET subscriber setup failed: " . $e->getMessage() . ". Continuing with notification process...");
        }
    }

    // Execute notification process
    $notificationMinutes = Config::getNotificationMinutes();
    $notifier = new EventPagerNotifier(null, null, null, $notificationMinutes);
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
