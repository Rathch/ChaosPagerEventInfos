#!/usr/bin/env php
<?php

/**
 * notify.php - CLI entry point for event pager notifications
 *
 * Executed via cronjob every 5 minutes.
 *
 * Usage: php bin/notify.php
 */

// Require Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use ChaosPagerEventInfos\Config;
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
