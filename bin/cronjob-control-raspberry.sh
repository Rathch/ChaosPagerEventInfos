#!/bin/bash

# Cronjob Control Script for ChaosPagerEventInfos (Raspberry Pi)
# Usage: ./bin/cronjob-control-raspberry.sh [enable|disable|status]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Detect PHP path (common Raspberry Pi locations)
if [ -f "/usr/bin/php" ]; then
    PHP_PATH="/usr/bin/php"
elif [ -f "/usr/local/bin/php" ]; then
    PHP_PATH="/usr/local/bin/php"
elif [ -f "/opt/php/bin/php" ]; then
    PHP_PATH="/opt/php/bin/php"
else
    # Try to find PHP in PATH
    PHP_PATH=$(which php 2>/dev/null)
    if [ -z "$PHP_PATH" ]; then
        echo "✗ Error: PHP not found. Please install PHP or set PHP_PATH manually in this script."
        exit 1
    fi
fi

NOTIFY_SCRIPT="$PROJECT_DIR/bin/notify.php"
CRON_LOG="$PROJECT_DIR/logs/cron.log"

# Cronjob entry (runs every 5 minutes for production)
# Format: minute hour day month weekday command
CRON_ENTRY="*/5 * * * * $PHP_PATH $NOTIFY_SCRIPT >> $CRON_LOG 2>&1"

case "$1" in
    enable)
        echo "Enabling cronjob on Raspberry Pi..."
        
        # Check if cronjob already exists
        if crontab -l 2>/dev/null | grep -q "$NOTIFY_SCRIPT"; then
            echo "Cronjob is already enabled!"
            exit 0
        fi
        
        # Verify PHP path
        if [ ! -f "$PHP_PATH" ]; then
            echo "✗ Error: PHP not found at $PHP_PATH"
            echo "Please install PHP or update PHP_PATH in this script."
            exit 1
        fi
        
        # Verify script exists
        if [ ! -f "$NOTIFY_SCRIPT" ]; then
            echo "✗ Error: Script not found at $NOTIFY_SCRIPT"
            exit 1
        fi
        
        # Create logs directory if it doesn't exist
        mkdir -p "$(dirname "$CRON_LOG")"
        
        # Add cronjob (ensure proper formatting with spaces)
        (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
        
        if [ $? -eq 0 ]; then
            # Verify the cronjob was added correctly
            if crontab -l 2>/dev/null | grep -q "$NOTIFY_SCRIPT"; then
                echo "✓ Cronjob enabled successfully!"
                echo "  Runs every 5 minutes"
                echo "  PHP: $PHP_PATH"
                echo "  Script: $NOTIFY_SCRIPT"
                echo "  Log: $CRON_LOG"
                echo ""
                echo "Current cronjob entry:"
                crontab -l 2>/dev/null | grep "$NOTIFY_SCRIPT"
                echo ""
                echo "View logs with: tail -f $CRON_LOG"
                echo "Or application logs: tail -f $PROJECT_DIR/logs/event-pager.log"
            else
                echo "✗ Cronjob was added but verification failed"
                exit 1
            fi
        else
            echo "✗ Failed to enable cronjob"
            exit 1
        fi
        ;;
    
    disable)
        echo "Disabling cronjob..."
        
        # Remove cronjob if it exists
        crontab -l 2>/dev/null | grep -v "$NOTIFY_SCRIPT" | crontab -
        
        if [ $? -eq 0 ]; then
            echo "✓ Cronjob disabled successfully!"
        else
            echo "✗ Failed to disable cronjob"
            exit 1
        fi
        ;;
    
    status)
        echo "Checking cronjob status..."
        echo ""
        
        # Check if cron service is running
        if command -v systemctl >/dev/null 2>&1; then
            if systemctl is-active --quiet cron; then
                echo "✓ Cron service is running"
            else
                echo "⚠ Cron service is not running"
                echo "  Start with: sudo systemctl start cron"
            fi
        fi
        
        echo ""
        
        if crontab -l 2>/dev/null | grep -q "$NOTIFY_SCRIPT"; then
            echo "✓ Cronjob is ENABLED"
            echo ""
            echo "Current cronjob entry:"
            crontab -l 2>/dev/null | grep "$NOTIFY_SCRIPT"
            echo ""
            echo "PHP path: $PHP_PATH"
            echo "Script: $NOTIFY_SCRIPT"
            echo "Log: $CRON_LOG"
            echo ""
            echo "View logs with: tail -f $CRON_LOG"
        else
            echo "✗ Cronjob is DISABLED"
            echo ""
            echo "Enable with: $0 enable"
        fi
        ;;
    
    test)
        echo "Testing script execution..."
        echo ""
        
        if [ ! -f "$NOTIFY_SCRIPT" ]; then
            echo "✗ Error: Script not found at $NOTIFY_SCRIPT"
            exit 1
        fi
        
        if [ ! -f "$PHP_PATH" ]; then
            echo "✗ Error: PHP not found at $PHP_PATH"
            exit 1
        fi
        
        echo "Running: $PHP_PATH $NOTIFY_SCRIPT"
        echo ""
        
        $PHP_PATH $NOTIFY_SCRIPT
        
        if [ $? -eq 0 ]; then
            echo ""
            echo "✓ Script executed successfully!"
            echo "Check logs: tail -f $PROJECT_DIR/logs/event-pager.log"
        else
            echo ""
            echo "✗ Script execution failed"
            exit 1
        fi
        ;;
    
    *)
        echo "Usage: $0 [enable|disable|status|test]"
        echo ""
        echo "Commands:"
        echo "  enable   - Enable the cronjob (runs every 5 minutes)"
        echo "  disable  - Disable the cronjob"
        echo "  status   - Check if cronjob is enabled and cron service status"
        echo "  test     - Test script execution manually"
        echo ""
        echo "Setup instructions:"
        echo "  1. Make sure PHP is installed: php --version"
        echo "  2. Test script manually: $0 test"
        echo "  3. Enable cronjob: $0 enable"
        echo "  4. Check status: $0 status"
        exit 1
        ;;
esac
