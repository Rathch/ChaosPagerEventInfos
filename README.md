# ChaosPagerEventInfos

Event Pager Notifications - Automatically sends notifications for talks in large rooms.

## Overview

This script reads the event calendar from the CCC API every 5 minutes, identifies talks in large rooms (One, Ground, Zero, Fuse) and sends a WebSocket notification to pager devices 15 minutes before each talk.

## Requirements

- PHP 7.4+ (Raspberry Pi OS compatible)
- Composer (for dependency management)
- Internet connection for API requests
- Optional: WebSocket server for real connection (MVP uses simulation)

## Installation

### Step 1: Install Composer on Raspberry Pi

If Composer is not already installed:

```bash
# Download Composer installer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

# Verify installer (optional, but recommended)
php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

# Install Composer globally
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer

# Cleanup
rm composer-setup.php

# Verify installation
composer --version
```

**Alternative**: Install via package manager (if available):
```bash
sudo apt-get update
sudo apt-get install composer
```

### Step 2: Clone Repository

```bash
git clone <repository-url>
cd ChaosPagerEventInfos
```

### Step 3: Install Dependencies

```bash
composer install --no-dev
```

**Note**: Use `--no-dev` for production (Raspberry Pi) to skip development dependencies like PHPUnit, PHP CS Fixer, and PHPStan. For development, use `composer install` without flags.

### Step 4: Create Configuration

```bash
cp .env.example .env
# Edit .env as needed
```

## Configuration (.env)

See `.env.example` for all available configuration options:

- `API_URL`: URL of the CCC Event API
- `WEBSOCKET_MODE`: `simulate` (MVP) or `real` (later)
- `WEBSOCKET_ENDPOINTS`: Comma-separated list of WebSocket endpoints
- `LOG_FILE`: Path to log file
- `SENT_HASHES_FILE`: Path to temporary hash list file
- `RIC`: Radio Identification Code (default: 1142)
- `TEST_MODE`: If `true`, sends notification for first found talk in large room regardless of time (useful for testing). Default: `false`

## Usage

### Manual Test Run

```bash
php bin/notify.php
```

### Test Mode

To test the notification sending without waiting for the correct time, enable test mode in `.env`:

```env
TEST_MODE=true
```

When enabled, the script will send a notification for the first talk found in a large room, regardless of the current time. This is useful for testing the complete notification flow.

**Important**: Remember to set `TEST_MODE=false` for production use!

### Setup Cronjob

#### Step 1: Find PHP Path

On Raspberry Pi, PHP might not be in `/usr/bin/php`. Find the correct path:

```bash
which php
```

Common paths:
- `/usr/bin/php` (standard)
- `/usr/local/bin/php` (if compiled from source)
- `/opt/php/bin/php` (if installed via package manager)

#### Step 2: Get Absolute Script Path

Get the absolute path to the script:

```bash
cd /path/to/ChaosPagerEventInfos
pwd
# Example output: /home/pi/ChaosPagerEventInfos
```

The full script path will be: `$(pwd)/bin/notify.php`

#### Step 3: Create Log Directory (if needed)

```bash
mkdir -p /path/to/logs
# Or use the logs directory in the project:
mkdir -p /home/pi/ChaosPagerEventInfos/logs
```

#### Step 4: Test Manual Execution

Before setting up the cronjob, test manually:

```bash
/usr/bin/php /home/pi/ChaosPagerEventInfos/bin/notify.php
```

If this works, proceed to cronjob setup.

#### Step 5: Edit Crontab

```bash
crontab -e
```

#### Step 6: Add Cronjob Entry

Add this line (adjust paths according to your system):

```bash
# Run every 5 minutes
*/5 * * * * /usr/bin/php /home/pi/ChaosPagerEventInfos/bin/notify.php >> /home/pi/ChaosPagerEventInfos/logs/cron.log 2>&1
```

**Important**: Replace paths with your actual paths:
- `/usr/bin/php` → Your PHP path from Step 1
- `/home/pi/ChaosPagerEventInfos` → Your project path from Step 2

#### Step 7: Verify Cronjob

Check if cronjob was added:

```bash
crontab -l
```

#### Step 8: Test Cronjob Execution

Wait for the next 5-minute interval, or trigger manually:

```bash
# Check cron service status
sudo systemctl status cron

# View cron logs (on Raspberry Pi OS / Debian)
sudo tail -f /var/log/syslog | grep CRON
```

#### Troubleshooting

**Problem**: Cronjob doesn't run
- Check if cron service is running: `sudo systemctl status cron`
- Verify PHP path is correct: `which php`
- Check script permissions: `chmod +x bin/notify.php`
- Verify `.env` file exists and is readable
- **Important**: Ensure `vendor/autoload.php` exists (run `composer install --no-dev` if missing)
- Check cron logs: `sudo tail -f /var/log/syslog | grep CRON`

**Problem**: "Command not found" errors
- Use absolute paths for PHP and script
- Ensure PHP is in PATH or use full path: `which php`

**Problem**: "Class not found" or "Composer autoloader missing" errors
- Run `composer install --no-dev` to install dependencies
- Verify `vendor/autoload.php` exists in project root

**Problem**: Script runs but no output
- Check log file: `tail -f logs/event-pager.log`
- Check cron log: `tail -f logs/cron.log`
- Verify `.env` configuration is correct

**Problem**: Permission denied
- Ensure script is executable: `chmod +x bin/notify.php`
- Check log directory permissions: `chmod 755 logs/`

### View Logs

```bash
# Application logs
tail -f logs/event-pager.log

# Cronjob execution logs (if configured)
tail -f logs/cron.log
```

## Expected Behavior

1. **API Request**: Script loads talk data from CCC API
2. **Filtering**: Only talks in large rooms (One, Ground, Zero, Fuse) are considered
3. **Time Check**: Only talks starting in 15 minutes are processed (unless TEST_MODE=true)
4. **Duplicate Check**: Already sent messages are not sent again
5. **Message Sending**: WebSocket message is sent (or simulated)

## Code Structure

```
src/
├── EventPagerNotifier.php    # Main class
├── ApiClient.php             # API client
├── WebSocketClient.php       # WebSocket client factory
├── WebSocketClientInterface.php  # WebSocket interface
├── MockWebSocketClient.php   # Mock implementation
├── MessageFormatter.php      # Message formatting
├── TalkFilter.php            # Talk filtering
├── Logger.php                # Logging
├── DuplicateTracker.php      # Duplicate tracking
└── Config.php                # Configuration

bin/
└── notify.php                # CLI entry point
```

## Development

### Tests

Tests can be run with PHPUnit:

```bash
composer install
vendor/bin/phpunit
```

### Linting

Code quality checks:

```bash
# Run all linting checks
composer lint

# Check code style (dry-run)
composer lint:cs

# Fix code style automatically
composer lint:cs:fix

# Run static analysis
composer lint:stan
```

## License

GPL-3.0-or-later (see LICENSE)
