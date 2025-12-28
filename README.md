# ChaosPagerEventInfos

Event Pager Notifications - Automatically sends notifications for talks in large rooms via DAPNET API.

## Overview

This script reads the event calendar from the CCC API periodically (configurable interval, default: every 1 minute), identifies talks in large rooms (One, Ground, Zero, Fuse) and sends DAPNET calls (messages) to pager devices a configurable number of minutes before each talk (default: 15 minutes, configurable via `NOTIFICATION_MINUTES` in `.env`).

## Requirements

- PHP 8.2+ (Raspberry Pi OS compatible)
- Composer (for dependency management)
- Internet connection for API requests
- DAPNET API access (with HTTP Basic Authentication credentials)

## Installation

### Quick Start (Recommended)

Use the `start.sh` script for automatic installation, cronjob setup, and startup. Use `stop.sh` to remove cronjobs:

```bash
chmod +x start.sh stop.sh
./start.sh    # Setup and start
./stop.sh     # Stop/remove cronjobs
```

The `start.sh` script will:
1. Check PHP version (requires PHP 8.2+)
2. Check Composer installation
3. Install Composer dependencies
4. Create `.env` file from `.env.example` (if not exists)
5. Create log directory
6. Validate configuration
7. **Setup cronjob** (optional, interactive interval configuration, default: every 1 minute)
8. Execute the main script once (for testing)

The `stop.sh` script will:
1. List all cronjobs related to this project
2. Allow you to select which cronjobs to remove (multiple selection supported)
3. Ask for confirmation before removing
4. Remove selected cronjobs and show remaining ones

### Manual Installation

1. **Clone repository**:
   ```bash
   git clone git@github.com:Rathch/ChaosPagerEventInfos.git
   cd ChaosPagerEventInfos
   ```

2. **Install Composer dependencies**:
   ```bash
   composer install
   ```

3. **Create configuration**:
   ```bash
   cp .env.example .env
   # Edit .env as needed
   ```

## Configuration (.env)

See `.env.example` for all available configuration options:

### API Configuration
- `API_URL`: URL of the CCC Event API (required)

### DAPNET API Configuration
- `DAPNET_API_URL`: DAPNET API base URL (e.g., `http://localhost:8080`)
- `DAPNET_API_USERNAME`: HTTP Basic Auth username for DAPNET API
- `DAPNET_API_PASSWORD`: HTTP Basic Auth password for DAPNET API
- `DAPNET_PRIORITY`: Call priority (0-7, default: 3)
- `DAPNET_EXPIRATION`: Call expiration in seconds (60-86400, default: 86400 = 24 hours)
- `DAPNET_LOCAL`: Local flag (true/false, default: false)
- `DAPNET_USE_HOME_INFO`: Use home info flag (true/false, default: false)
- `DAPNET_TRANSMITTER_GROUPS`: Comma-separated list of transmitter groups (default: "all")

### Room to Subscriber Mapping (Primary - for DAPNET Calls)
- `ROOM_SUBSCRIBER_ZERO`: DAPNET Subscriber ID for Room Zero (default: 1140)
- `ROOM_SUBSCRIBER_ONE`: DAPNET Subscriber ID for Room One (default: 1141)
- `ROOM_SUBSCRIBER_GROUND`: DAPNET Subscriber ID for Room Ground (default: 1142)
- `ROOM_SUBSCRIBER_FUSE`: DAPNET Subscriber ID for Room Fuse (default: 1143)
- `ROOM_SUBSCRIBER_ALL_ROOMS`: DAPNET Subscriber ID for All-Rooms (default: 1150)

### Room RIC Configuration (Secondary - for Subscriber Creation)
- `ROOM_RIC_ZERO`: RIC for Room Zero (default: 1140) - used for `pagers.ric` field
- `ROOM_RIC_ONE`: RIC for Room One (default: 1141) - used for `pagers.ric` field
- `ROOM_RIC_GROUND`: RIC for Room Ground (default: 1142) - used for `pagers.ric` field
- `ROOM_RIC_FUSE`: RIC for Room Fuse (default: 1143) - used for `pagers.ric` field
- `ROOM_RIC_ALL_ROOMS`: RIC for All-Rooms (default: 1150) - used for `pagers.ric` field



### Queue Configuration
- `QUEUE_DELAY_SECONDS`: Delay between successful messages in seconds (default: 5)
- `QUEUE_MAX_RETRIES`: Maximum number of retry attempts for failed messages (default: 3)
- `QUEUE_RETRY_DELAY_SECONDS`: Delay between retry attempts in seconds (default: 5)

### Logging
- `LOG_FILE`: Path to log file (default: `logs/event-pager.log`)
- `SENT_HASHES_FILE`: Path to temporary hash list file for duplicate tracking (default: `logs/sent-hashes.txt`)

### Notification Timing
- `NOTIFICATION_MINUTES`: Minutes before talk start to send notification (default: 15 minutes). Example: Set to `30` to send notifications 30 minutes before talks.

### Testing
- `TEST_MODE`: If `true`, sends notification for first found talk in large room regardless of time (useful for testing). Default: `false`
- `SIMULATE_CURRENT_TIME`: Simulated current time for local testing (ISO-8601 format, e.g., `"2025-12-27T10:15:00+01:00"`). If set, uses this time instead of system time. Useful for testing time-based notification logic. Leave empty to use real system time.

### Example .env Configuration

```env
# API Configuration
API_URL=https://api.events.ccc.de/congress/2025/schedule.json

# DAPNET API Configuration
DAPNET_API_URL=http://localhost:8080
DAPNET_API_USERNAME=your_username
DAPNET_API_PASSWORD=your_password
DAPNET_PRIORITY=3
DAPNET_EXPIRATION=86400
DAPNET_LOCAL=false
DAPNET_USE_HOME_INFO=false
DAPNET_TRANSMITTER_GROUPS=all

# Room to Subscriber Mapping (Primary - for DAPNET Calls)
ROOM_SUBSCRIBER_ZERO=1140
ROOM_SUBSCRIBER_ONE=1141
ROOM_SUBSCRIBER_GROUND=1142
ROOM_SUBSCRIBER_FUSE=1143
ROOM_SUBSCRIBER_ALL_ROOMS=1150

# Room RIC Configuration (Secondary - for Subscriber Creation)
ROOM_RIC_ZERO=1140
ROOM_RIC_ONE=1141
ROOM_RIC_GROUND=1142
ROOM_RIC_FUSE=1143
ROOM_RIC_ALL_ROOMS=1150

# RIC to Subscriber Mapping (Fallback - for Backward Compatibility)
RIC_1140_SUBSCRIBER=1140
RIC_1141_SUBSCRIBER=1141
RIC_1142_SUBSCRIBER=1142
RIC_1143_SUBSCRIBER=1143
RIC_1150_SUBSCRIBER=1150

# Queue Configuration
QUEUE_DELAY_SECONDS=5
QUEUE_MAX_RETRIES=3
QUEUE_RETRY_DELAY_SECONDS=5

# Logging
LOG_FILE=logs/event-pager.log
SENT_HASHES_FILE=logs/sent-hashes.txt

# Testing
TEST_MODE=false
# SIMULATE_CURRENT_TIME=2025-12-27T10:15:00+01:00
```

## Usage

### Using start.sh (Recommended)

The easiest way to run the script is using the `start.sh` script:

```bash
./start.sh
```

This will handle all prerequisites and execute the main script.

### Manual Execution

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

### Time Simulation Mode

To test the time-based notification logic locally without waiting for the actual time, you can use `SIMULATE_CURRENT_TIME`:

```env
SIMULATE_CURRENT_TIME=2025-12-27T10:15:00+01:00
TEST_MODE=false
```

**How it works:**
- When `SIMULATE_CURRENT_TIME` is set, the script uses this time instead of the current system time
- The script will check which talks start exactly `NOTIFICATION_MINUTES` minutes after the simulated time (default: 15 minutes, ±30 seconds tolerance)
- This allows you to test the time-based notification logic locally
- **Important**: Set `TEST_MODE=false` when using time simulation, otherwise TEST_MODE will override the time-based logic

**Example usage:**
1. Find a talk in the API that starts at, e.g., `2025-12-27T10:30:00+01:00`
2. Set `SIMULATE_CURRENT_TIME=2025-12-27T10:15:00+01:00` (15 minutes before the talk, or adjust based on your `NOTIFICATION_MINUTES` setting)
3. Set `TEST_MODE=false` to enable time-based logic
4. Run the script - it should send a notification for that talk
5. The log will show detailed information about which talks were checked and why they were/were not sent

**Log output example:**
```
[2025-12-27 10:15:00] INFO: Starting event pager notification process (SIMULATED TIME: 2025-12-27 10:15:00)
[2025-12-27 10:15:00] INFO: Checking 5 talks for time-based notifications (SIMULATION MODE) (current time: 2025-12-27 10:15:00)
[2025-12-27 10:15:00] INFO:   - Talk 1: Too early (notification would be sent in 30 minutes)
[2025-12-27 10:15:00] INFO: ✓ Talk matches time criteria: Grand opening (starts at 2025-12-27T10:30:00+01:00)
[2025-12-27 10:15:00] INFO: Notification sent at 2025-12-27 10:15:00: Grand opening
[2025-12-27 10:15:00] INFO: Simulation complete: Checked 5 talks, 1 notifications sent
```

**Important**: 
- Leave `SIMULATE_CURRENT_TIME` empty or unset for production use (uses real system time)
- Set `TEST_MODE=false` when using time simulation
- The simulated time is logged at the start of the process
- The actual send time is logged with each notification
- **Duplicate tracking**: If you run the script multiple times with the same simulated time, already sent messages will be detected as duplicates. To test with the same time again, delete the `SENT_HASHES_FILE` or use a different simulated time

### Setup Cronjob

#### Automatic Setup (Recommended)

The `start.sh` script can set up the cronjob for you interactively. Simply run:

```bash
./start.sh
```

The script will:
- Ask for the cronjob interval (interactive, default: 1 minute)
- Ask if you want to set up the cronjob (y/n)
- Find PHP executable automatically
- Create cronjob entry with the configured interval
- Configure logging to `logs/cron.log`
- Run the script once for testing

**Note**: The cronjob runs `bin/notify.php` directly, not `start.sh`. This ensures efficient execution.

##### Configuring Cronjob Interval

**Interactive Configuration (Recommended):**

When running `./start.sh`, you will be prompted to enter the interval:
- Simply press Enter to use the default (1 minute)
- Or enter a number between 1-59 for a custom interval

**Non-Interactive Configuration:**

You can also configure the interval by setting the `CRON_INTERVAL_MINUTES` environment variable before running `start.sh`:

```bash
# Run every 10 minutes
export CRON_INTERVAL_MINUTES=10
./start.sh

# Run every 5 minutes
export CRON_INTERVAL_MINUTES=5
./start.sh

# Run every 15 minutes
export CRON_INTERVAL_MINUTES=15
./start.sh
```

**Valid values**: 1-59 minutes

**Default**: 1 minute (if not specified)

The script will automatically convert the interval to the correct cron format:
- **1 minute**: `* * * * *` (runs every minute)
- **Divisible by 60** (e.g., 5, 10, 15, 30): `*/N * * * *` (e.g., `*/5 * * * *`)
- **Other values**: Comma-separated minute list (e.g., `0,7,14,21,28,35,42,49,56 * * * *` for every 7 minutes)

#### Manual Cronjob Setup

If you prefer to set up the cronjob manually:

#### Step 1: Find PHP Path

Find the correct PHP path on your system:

```bash
which php
```

Common paths:
- `/usr/bin/php` (standard Linux)
- `/usr/local/bin/php` (if compiled from source)
- `/opt/php/bin/php` (if installed via package manager)
- On macOS: `/usr/bin/php` or `/opt/homebrew/bin/php` (if installed via Homebrew)
- On WSL: `/usr/bin/php`

#### Step 2: Get Absolute Script Path

Get the absolute path to the script:

```bash
cd /path/to/ChaosPagerEventInfos
pwd
# Example output: /home/user/ChaosPagerEventInfos
```

The full script path will be: `$(pwd)/bin/notify.php`

#### Step 3: Create Log Directory (if needed)

```bash
mkdir -p logs
# Or use absolute path:
mkdir -p /path/to/ChaosPagerEventInfos/logs
```

#### Step 4: Test Manual Execution

Before setting up the cronjob, test manually:

```bash
php bin/notify.php
# Or with full path:
/usr/bin/php /path/to/ChaosPagerEventInfos/bin/notify.php
```

If this works, proceed to cronjob setup.

#### Step 5: Edit Crontab

```bash
crontab -e
```

#### Step 6: Add Cronjob Entry

**For Production (every 1 minute - default):**
```bash
# Run every 1 minute (default)
* * * * * /usr/bin/php /path/to/ChaosPagerEventInfos/bin/notify.php >> /path/to/ChaosPagerEventInfos/logs/cron.log 2>&1
```

**For Production (every 5 minutes - recommended for lower load):**
```bash
# Run every 5 minutes
*/5 * * * * /usr/bin/php /path/to/ChaosPagerEventInfos/bin/notify.php >> /path/to/ChaosPagerEventInfos/logs/cron.log 2>&1
```

**For Local Testing (every minute - for faster testing):**
```bash
# Run every minute (for local testing)
* * * * * /usr/bin/php /path/to/ChaosPagerEventInfos/bin/notify.php >> /path/to/ChaosPagerEventInfos/logs/cron.log 2>&1
```

**Important**: Replace paths with your actual paths:
- `/usr/bin/php` → Your PHP path from Step 1
- `/path/to/ChaosPagerEventInfos` → Your project path from Step 2

#### Step 7: Verify Cronjob

Check if cronjob was added:

```bash
crontab -l
```

#### Step 8: Test Cronjob Execution

**On Linux/WSL:**
```bash
# Check cron service status
sudo systemctl status cron

# View cron logs (on Debian/Ubuntu/WSL)
sudo tail -f /var/log/syslog | grep CRON

# Or check the cron log file directly
tail -f logs/cron.log
```

**On macOS:**
```bash
# Check cron service (macOS uses launchd, but crontab still works)
# View cron logs
tail -f /var/log/system.log | grep cron

# Or check the cron log file directly
tail -f logs/cron.log
```

**Manual Test (run immediately):**
```bash
# Run the script manually to test
php bin/notify.php

# Check if it worked
tail -n 20 logs/event-pager.log
```

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
   - Tolerance: ±30 seconds (per Success Criteria SC-003)
4. **Duplicate Check**: Already sent messages are not sent again (tracked via hash file)
5. **Message Queue**: Messages are enqueued for sequential sending
   - Messages are sent one at a time (not in parallel)
   - Configurable delay between successful messages (default: 5 seconds)
   - Automatic retry for failed messages (HTTP 429, HTTP 500, network errors)
   - Maximum retry attempts configurable (default: 3 attempts)
6. **Message Sending**: DAPNET calls are sent via DAPNET API
   - Messages are formatted as DAPNET Call Format
   - ASCII sanitization and intelligent truncation (max 160 characters)
   - HTTP Basic Authentication
   - Automatic subscriber creation (if permissions allow)

## Message Queue and Retry Mechanism

The system uses a message queue to ensure reliable delivery and handle HTTP 429 errors (Rate limiting). Messages are sent **sequentially** (one at a time) with configurable delays between successful messages. Failed messages are automatically retried.

### Queue Behavior

- **Sequential Sending**: Only one message is sent at a time (no parallel requests)
- **Configurable Delay**: Delay between successful messages (default: 5 seconds)
- **Automatic Retry**: Failed messages (HTTP 429, HTTP 500, network errors) are automatically retried
- **Max Retries**: Configurable maximum retry attempts (default: 3 attempts)
- **Retry Delay**: Configurable delay between retry attempts (default: 5 seconds)

### HTTP 429 Handling

When the DAPNET API returns HTTP 429 (Rate limit exceeded), the message is automatically retried after a longer delay (double the normal retry delay). This helps handle rate limiting gracefully.

### HTTP 423 Handling

When the DAPNET API returns HTTP 423 (Resource conflict - Call already exists), the message is marked as failed immediately without retry, as retrying would not help.

## Room-Specific Subscriber Mapping

The system supports room-specific DAPNET Subscriber mapping. For each talk, **two notifications are sent**:

1. **Room-specific notification**: Sent directly to the DAPNET Subscriber configured for the specific room (e.g., Subscriber "1141" for Room "One")
2. **All-Rooms notification**: Sent to the DAPNET Subscriber configured for All-Rooms (default: "1150") for participants monitoring all rooms

**Note**: The DAPNET API uses Subscriber IDs directly for calls. RICs are only used for subscriber creation (`pagers.ric` field in the DAPNET API).

### Room to Subscriber Mapping (Primary)

Each room is directly mapped to a DAPNET Subscriber ID in `.env`:

- **Room Zero** → Subscriber ID (configurable via `ROOM_SUBSCRIBER_ZERO`, default: 1140)
- **Room One** → Subscriber ID (configurable via `ROOM_SUBSCRIBER_ONE`, default: 1141)
- **Room Ground** → Subscriber ID (configurable via `ROOM_SUBSCRIBER_GROUND`, default: 1142)
- **Room Fuse** → Subscriber ID (configurable via `ROOM_SUBSCRIBER_FUSE`, default: 1143)
- **All Rooms** → Subscriber ID (configurable via `ROOM_SUBSCRIBER_ALL_ROOMS`, default: 1150)

**Note**: The DAPNET API uses Subscriber IDs directly for calls (no RIC needed). RICs are only used for subscriber creation (`pagers.ric` field).

### Room-RIC Configuration (Secondary - for Subscriber Creation)

RICs are only needed for creating subscribers (the `pagers.ric` field in the DAPNET API). If not configured, defaults are used:

- **Room Zero** → RIC 1140 (configurable via `ROOM_RIC_ZERO`)
- **Room One** → RIC 1141 (configurable via `ROOM_RIC_ONE`)
- **Room Ground** → RIC 1142 (configurable via `ROOM_RIC_GROUND`)
- **Room Fuse** → RIC 1143 (configurable via `ROOM_RIC_FUSE`)
- **All Rooms** → RIC 1150 (configurable via `ROOM_RIC_ALL_ROOMS`)

### RIC to Subscriber Mapping (Fallback - for Backward Compatibility)

If `ROOM_SUBSCRIBER_*` is not configured, the system falls back to RIC-based mapping:

- `RIC_1140_SUBSCRIBER`: Subscriber ID for RIC 1140 (Room Zero)
- `RIC_1141_SUBSCRIBER`: Subscriber ID for RIC 1141 (Room One)
- `RIC_1142_SUBSCRIBER`: Subscriber ID for RIC 1142 (Room Ground)
- `RIC_1143_SUBSCRIBER`: Subscriber ID for RIC 1143 (Room Fuse)
- `RIC_1150_SUBSCRIBER`: Subscriber ID for RIC 1150 (All-Rooms)

**Note**: The script will automatically check if subscribers exist and create them if missing (if DAPNET API credentials have admin/support permissions). RICs are used during subscriber creation for the `pagers.ric` field.

### Example: Multiple Talks

If there are 4 talks at 10:45 in 4 different rooms (One, Ground, Zero, Fuse):
- 4 room-specific notifications (one per room with its specific RIC and Subscriber)
- 4 all-rooms notifications (all to RIC 1150 Subscriber, one per talk)
- **Total: 8 DAPNET calls**

### Example: Single Talk

For a talk "Grand opening" at 10:45 in Room "One":
- **Call 1**: Room "One" → Subscriber "1141" with message "10:45, One, Grand opening"
- **Call 2**: All-Rooms → Subscriber "1150" with message "10:45, One, Grand opening"

## DAPNET Call Format

The script sends DAPNET calls to the DAPNET API using HTTP POST requests with the following format:

### Request Details

- **Method**: POST
- **Endpoint**: `{DAPNET_API_URL}/calls`
- **Content-Type**: `application/json`
- **Authentication**: HTTP Basic Auth (using `DAPNET_API_USERNAME` and `DAPNET_API_PASSWORD`)

### DAPNET Call Payload

```json
{
  "data": "10:30, One, Grand opening",
  "expiration": 86400,
  "local": false,
  "priority": 3,
  "subscriber_groups": [],
  "subscribers": ["1141"],
  "transmitter_groups": ["all"],
  "transmitters": [],
  "use_home_info": false
}
```

### Payload Fields

- `data` (string): Message text (max 160 characters, ASCII-only)
  - Format: "HH:MM, Room, Title" (e.g., "10:30, One, Grand opening")
  - Automatically sanitized to ASCII-only characters
  - Automatically truncated to 160 characters with intelligent shortening (adds "..." if truncated)
- `expiration` (integer): Call expiration in seconds (60-86400, default: 86400 = 24 hours)
- `local` (boolean): Local flag (default: false)
- `priority` (integer): Call priority (0-7, default: 3)
- `subscriber_groups` (array): Subscriber groups (empty, using individual subscribers)
- `subscribers` (array): List of DAPNET Subscriber IDs (one per RIC)
- `transmitter_groups` (array): List of transmitter group names (default: ["all"])
- `transmitters` (array): List of transmitter IDs (empty, using transmitter groups)
- `use_home_info` (boolean): Use home info flag (default: false)

### Message Formatting

Messages are formatted as: `"HH:MM, Room, Title"`

- **Time**: 24-hour format (HH:MM)
- **Room**: Room name (One, Ground, Zero, Fuse)
- **Title**: Talk title

Example: `"10:30, One, Grand opening"`

### ASCII Sanitization

All messages are automatically sanitized to ASCII-only characters. Non-ASCII characters are converted or removed to ensure compatibility with DAPNET API requirements.

### Message Truncation

Messages exceeding 160 characters are automatically truncated:
- Truncates to 157 characters and adds "..."
- Attempts to truncate at word boundaries when possible

## Code Structure

```
src/
├── EventPagerNotifier.php    # Main class
├── ApiClient.php             # API client
├── DapnetApiClient.php      # DAPNET API client
├── DapnetCallFormatter.php  # DAPNET Call formatting
├── DapnetSubscriberManager.php # DAPNET Subscriber management
├── MessageQueue.php          # Message queue for sequential sending
├── QueuedMessage.php         # Single message in queue
├── MessageFormatter.php      # Message formatting
├── AsciiSanitizer.php        # ASCII sanitization
├── RoomRicMapper.php         # Room-RIC mapping
├── TalkFilter.php            # Talk filtering
├── Logger.php                # Logging
├── DuplicateTracker.php      # Duplicate tracking
└── Config.php                # Configuration

bin/
└── notify.php                # CLI entry point

start.sh                      # Installation and startup script
```

## Development

### Tests

Tests can be run with PHPUnit (see `composer.json`):

```bash
composer install
vendor/bin/phpunit
```

## Troubleshooting

### DAPNET API Issues

**Problem**: DAPNET API authentication errors
- Verify `DAPNET_API_USERNAME` and `DAPNET_API_PASSWORD` are correct
- Check if credentials have necessary permissions (read/write for calls, admin/support for subscriber creation)
- Test API access manually: `curl -u username:password http://your-dapnet-api/calls`

**Problem**: Missing subscriber mappings
- Ensure all RIC-to-Subscriber mappings are configured in `.env` (e.g., `RIC_1140_SUBSCRIBER`, `RIC_1141_SUBSCRIBER`, etc.)
- The script will attempt to create missing subscribers automatically if credentials have admin/support permissions
- Check logs for subscriber creation errors

**Problem**: HTTP 423 errors (Resource conflict)
- This means a call with the same content already exists
- The message is marked as failed without retry (this is expected behavior)
- Check if duplicate tracking is working correctly

**Problem**: HTTP 429 errors (Rate limiting)
- The script automatically retries with longer delays
- Increase `QUEUE_DELAY_SECONDS` to reduce rate limiting
- Check DAPNET API rate limits

### General Issues

**Problem**: Cronjob doesn't run
- **Linux/WSL**: Check if cron service is running: `sudo systemctl status cron`
- **macOS**: Cronjobs run automatically, no service to check
- Verify PHP path is correct: `which php`
- Check script permissions: `chmod +x bin/notify.php`
- Verify `.env` file exists and is readable
- Check cron logs: 
  - Linux/WSL: `sudo tail -f /var/log/syslog | grep CRON`
  - macOS: `tail -f /var/log/system.log | grep cron`
  - Or check: `tail -f logs/cron.log`

**Problem**: "Command not found" errors
- Use absolute paths for PHP and script
- Ensure PHP is in PATH or use full path: `which php`
- Use `start.sh` script which handles path detection automatically

**Problem**: Script runs but no output
- Check log file: `tail -f logs/event-pager.log`
- Check cron log: `tail -f logs/cron.log`
- Verify `.env` configuration is correct
- Check if DAPNET API is configured and accessible

**Problem**: Messages are not being sent sequentially
- Verify `QUEUE_DELAY_SECONDS` is configured correctly
- Check logs for queue processing messages
- Ensure MessageQueue is properly initialized

**Problem**: Retry mechanism not working
- Verify `QUEUE_MAX_RETRIES` and `QUEUE_RETRY_DELAY_SECONDS` are configured
- Check logs for retry attempts and failures
- Ensure HTTP status codes are being detected correctly

**Problem**: Permission denied
- Ensure script is executable: `chmod +x bin/notify.php`
- Check log directory permissions: `chmod 755 logs/`
- Ensure `.env` file is readable: `chmod 644 .env`

**Problem**: start.sh script fails
- Check PHP version: `php -v` (requires PHP 8.2+)
- Check Composer installation: `composer --version`
- Verify `.env.example` exists
- Check script permissions: `chmod +x start.sh`
- Review error messages in script output

## License

GPL-3.0-or-later (see LICENSE)

## Further Documentation

- [Feature Specification: Event Pager Notifications](specs/001-event-pager-notifications/spec.md)
- [Feature Specification: DAPNET API Integration](specs/004-dapnet-api-integration/spec.md)
- [Feature Specification: Start Script](specs/005-start-script/spec.md)
