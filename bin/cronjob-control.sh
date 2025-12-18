#!/bin/bash

# Cronjob Control Script for ChaosPagerEventInfos
# Usage: ./bin/cronjob-control.sh [enable|disable|status]

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PHP_PATH="/usr/bin/php"
NOTIFY_SCRIPT="$PROJECT_DIR/bin/notify.php"
CRON_LOG="$PROJECT_DIR/logs/cron.log"

# Cronjob entry (runs every minute for local testing)
# Format: minute hour day month weekday command
# Change to script directory first to ensure relative paths work
CRON_ENTRY="* * * * * cd $PROJECT_DIR && $PHP_PATH bin/notify.php >> logs/cron.log 2>&1"

case "$1" in
    enable)
        echo "Enabling cronjob..."
        
        # Check if cronjob already exists
        if crontab -l 2>/dev/null | grep -q "notify.php"; then
            echo "Cronjob is already enabled!"
            exit 0
        fi
        
        # Add cronjob (ensure proper formatting with spaces)
        (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
        
        if [ $? -eq 0 ]; then
            # Verify the cronjob was added correctly
            if crontab -l 2>/dev/null | grep -q "notify.php"; then
                echo "✓ Cronjob enabled successfully!"
                echo "  Runs every minute"
                echo "  Script: $NOTIFY_SCRIPT"
                echo "  Log: $CRON_LOG"
                echo ""
                echo "Current cronjob entry:"
                crontab -l 2>/dev/null | grep "$NOTIFY_SCRIPT"
                echo ""
                echo "View logs with: tail -f $CRON_LOG"
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
        crontab -l 2>/dev/null | grep -v "notify.php" | crontab -
        
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
        
        if crontab -l 2>/dev/null | grep -q "notify.php"; then
            echo "✓ Cronjob is ENABLED"
            echo ""
            echo "Current cronjob entry:"
            crontab -l 2>/dev/null | grep "notify.php"
            echo ""
            echo "View logs with: tail -f $CRON_LOG"
        else
            echo "✗ Cronjob is DISABLED"
            echo ""
            echo "Enable with: $0 enable"
        fi
        ;;
    
    *)
        echo "Usage: $0 [enable|disable|status]"
        echo ""
        echo "Commands:"
        echo "  enable   - Enable the cronjob (runs every minute)"
        echo "  disable  - Disable the cronjob"
        echo "  status   - Check if cronjob is enabled or disabled"
        exit 1
        ;;
esac
