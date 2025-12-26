#!/bin/bash

# start.sh - Installation and startup script for ChaosPagerEventInfos
# 
# Checks PHP version, Composer installation, installs dependencies,
# creates .env from .env.example, creates log directories and
# then executes the main script (bin/notify.php) once.

set -e  # Exit on error
set -u  # Exit on undefined variable

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Determine script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Exit codes
EXIT_SUCCESS=0
EXIT_GENERAL_ERROR=1
EXIT_PHP_ERROR=2
EXIT_COMPOSER_ERROR=3
EXIT_PERMISSION_ERROR=4
EXIT_ENV_EXAMPLE_ERROR=5
EXIT_SCRIPT_ERROR=6

# Helper functions for output
error() {
    echo -e "${RED}✗${NC} $1" >&2
}

error_with_solution() {
    echo -e "${RED}✗${NC} $1" >&2
    if [ -n "${2:-}" ]; then
        echo -e "  Solution: $2" >&2
    fi
}

warning() {
    echo -e "${YELLOW}⚠${NC} $1" >&2
}

success() {
    echo -e "${GREEN}✓${NC} $1"
}

info() {
    echo -e "$1"
}

# Check write permissions for directory
check_write_permissions() {
    local dir="$1"
    if [ ! -w "$dir" ] 2>/dev/null; then
        error_with_solution "No write permissions for directory: $dir" \
            "Run: chmod 755 $dir"
        exit $EXIT_PERMISSION_ERROR
    fi
}

# Handle network errors
handle_network_error() {
    local operation="$1"
    error "Network error during: $operation"
    echo ""
    echo "Possible solutions:"
    echo "  - Check your internet connection"
    echo "  - Check firewall settings"
    echo "  - Check proxy settings (if applicable)"
    exit $EXIT_COMPOSER_ERROR
}

# Check PHP version
check_php_version() {
    if ! command -v php &> /dev/null; then
        error "PHP is not installed or not in PATH."
        echo ""
        echo "Install PHP 8.2+ using one of the following commands:"
        echo "  Debian/Ubuntu: sudo apt install php"
        echo "  CentOS/RHEL:   sudo yum install php"
        echo "  Arch Linux:     sudo pacman -S php"
        exit $EXIT_PHP_ERROR
    fi
    
    # Parse PHP version
    PHP_VERSION=$(php -v | head -n 1 | grep -oP '\d+\.\d+' | head -n 1)
    PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d. -f1)
    PHP_MINOR=$(echo "$PHP_VERSION" | cut -d. -f2)
    
    # Check for at least PHP 8.2
    if [ "$PHP_MAJOR" -lt 8 ] || ([ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 2 ]); then
        error "PHP version $PHP_VERSION is too old. PHP 8.2 or higher is required."
        echo ""
        echo "Update PHP to version 8.2 or higher."
        exit $EXIT_PHP_ERROR
    fi
    
    success "PHP Version: $PHP_VERSION"
}

# Check Composer installation
check_composer() {
    if ! command -v composer &> /dev/null; then
        error "Composer is not installed or not in PATH."
        echo ""
        echo "Install Composer using one of the following commands:"
        echo "  Global: curl -sS https://getcomposer.org/installer | php"
        echo "          sudo mv composer.phar /usr/local/bin/composer"
        echo ""
        echo "  Or follow the instructions at: https://getcomposer.org/download/"
        exit $EXIT_COMPOSER_ERROR
    fi
    
    COMPOSER_VERSION=$(composer --version | grep -oP '\d+\.\d+\.\d+' | head -n 1)
    success "Composer found (Version: $COMPOSER_VERSION)"
}

# Install Composer dependencies
install_composer_dependencies() {
    if [ -d "vendor" ] && [ -f "composer.lock" ]; then
        # Check if composer.json was modified (simple check)
        if [ "composer.json" -nt "composer.lock" ]; then
            info "Updating Composer dependencies..."
            composer install --no-interaction --no-dev --optimize-autoloader || {
                handle_network_error "Composer installation"
            }
            success "Composer dependencies updated"
        else
            success "Composer dependencies already installed"
        fi
    else
        info "Installing Composer dependencies..."
        composer install --no-interaction --no-dev --optimize-autoloader || {
            handle_network_error "Composer installation"
        }
        success "Composer dependencies installed"
    fi
}

# Create .env file
create_env_file() {
    if [ ! -f ".env.example" ]; then
        error ".env.example file not found"
        exit $EXIT_ENV_EXAMPLE_ERROR
    fi
    
    if [ ! -f ".env" ]; then
        info "Creating .env file from .env.example..."
        cp .env.example .env || {
            error "Error creating .env file"
            exit $EXIT_GENERAL_ERROR
        }
        warning ".env file was created. Please configure the values."
    else
        success ".env file already exists"
    fi
}

# Create log directory
create_log_directory() {
    if [ ! -d "logs" ]; then
        info "Creating log directory..."
        mkdir -p logs || {
            error_with_solution "Error creating log directory" \
                "Check permissions for the project directory"
            exit $EXIT_PERMISSION_ERROR
        }
        success "Log directory created"
    else
        success "Log directory already exists"
    fi
    
    # Check write permissions for log directory
    check_write_permissions "logs"
}

# Validate .env file (simple check)
validate_env_file() {
    if [ ! -f ".env" ]; then
        error ".env file not found"
        exit $EXIT_GENERAL_ERROR
    fi
    
    # Check if .env file is readable
    if [ ! -r ".env" ]; then
        error_with_solution ".env file is not readable" \
            "Check permissions: chmod 644 .env"
        exit $EXIT_PERMISSION_ERROR
    fi
    
    # Check important configuration values
    local missing_values=()
    
    # DAPNET API configuration (important)
    if ! grep -q "^DAPNET_API_URL=" .env 2>/dev/null; then
        missing_values+=("DAPNET_API_URL")
    fi
    
    if ! grep -q "^DAPNET_API_USERNAME=" .env 2>/dev/null; then
        missing_values+=("DAPNET_API_USERNAME")
    fi
    
    if ! grep -q "^DAPNET_API_PASSWORD=" .env 2>/dev/null; then
        missing_values+=("DAPNET_API_PASSWORD")
    fi
    
    # API URL (important)
    if ! grep -q "^API_URL=" .env 2>/dev/null; then
        missing_values+=("API_URL")
    fi
    
    if [ ${#missing_values[@]} -gt 0 ]; then
        warning "Important configuration values missing in .env:"
        for value in "${missing_values[@]}"; do
            echo "  - $value"
        done
        echo ""
        echo "Please configure these values in the .env file."
    else
        success ".env file is valid"
    fi
}

# Find PHP executable path
find_php_path() {
    if command -v php &> /dev/null; then
        PHP_PATH=$(command -v php)
        echo "$PHP_PATH"
        return 0
    fi
    
    # Try common paths
    local common_paths=(
        "/usr/bin/php"
        "/usr/local/bin/php"
        "/opt/php/bin/php"
        "/opt/homebrew/bin/php"
    )
    
    for path in "${common_paths[@]}"; do
        if [ -x "$path" ]; then
            echo "$path"
            return 0
        fi
    done
    
    return 1
}

# Get cronjob interval from environment or use default
get_cron_interval() {
    local interval="${CRON_INTERVAL_MINUTES:-1}"
    
    # Validate interval (must be between 1 and 59 minutes)
    if ! [[ "$interval" =~ ^[0-9]+$ ]] || [ "$interval" -lt 1 ] || [ "$interval" -gt 59 ]; then
        warning "Invalid CRON_INTERVAL_MINUTES value: $interval. Using default: 1 minute"
        interval=1
    fi
    
    echo "$interval"
}

# Ask user for cronjob interval
ask_cron_interval() {
    local default_interval=$(get_cron_interval)
    
    echo ""
    info "Cronjob Interval Configuration:"
    echo "  Enter the interval in minutes (1-59)"
    echo "  Default: $default_interval minute(s)"
    echo ""
    
    while true; do
        read -p "Interval in minutes [$default_interval]: " -r response
        
        # Use default if empty
        if [ -z "$response" ]; then
            CRON_INTERVAL_MINUTES=$default_interval
            return 0
        fi
        
        # Validate input
        if ! [[ "$response" =~ ^[0-9]+$ ]]; then
            warning "Please enter a number between 1 and 59"
            continue
        fi
        
        if [ "$response" -lt 1 ] || [ "$response" -gt 59 ]; then
            warning "Interval must be between 1 and 59 minutes"
            continue
        fi
        
        CRON_INTERVAL_MINUTES=$response
        return 0
    done
}

# Convert minutes to cron format
minutes_to_cron() {
    local minutes=$1
    
    if [ "$minutes" -eq 1 ]; then
        echo "* * * * *"
    elif [ "$minutes" -le 59 ] && [ $((60 % minutes)) -eq 0 ]; then
        # If minutes divides evenly into 60, use */N format
        echo "*/$minutes * * * *"
    else
        # Otherwise, use comma-separated list for each minute
        local cron_minutes=""
        local i=0
        while [ $i -lt 60 ]; do
            if [ -n "$cron_minutes" ]; then
                cron_minutes="$cron_minutes,"
            fi
            cron_minutes="$cron_minutes$i"
            i=$((i + minutes))
        done
        echo "$cron_minutes * * * *"
    fi
}

# Ask user if cronjob should be set up
ask_setup_cronjob() {
    # First ask for interval
    ask_cron_interval
    
    local interval=$(get_cron_interval)
    
    echo ""
    info "Cronjob Configuration:"
    echo "  Interval: Every $interval minute(s)"
    echo "  Script: bin/notify.php"
    echo ""
    
    while true; do
        read -p "Do you want to set up the cronjob? (y/n) [y]: " -r response
        response=${response:-y}
        
        case "$response" in
            [Yy]|[Yy][Ee][Ss])
                return 0
                ;;
            [Nn]|[Nn][Oo])
                return 1
                ;;
            *)
                warning "Please enter 'y' or 'n'"
                ;;
        esac
    done
}

# Setup cronjob
setup_cronjob() {
    local interval=$(get_cron_interval)
    
    info "Setting up cronjob (interval: every $interval minute(s))..."
    
    # Check if crontab command is available
    if ! command -v crontab &> /dev/null; then
        warning "crontab command not found. Cronjob setup skipped."
        echo ""
        echo "To set up cronjob manually:"
        echo "  1. Install cron service (if not installed)"
        echo "  2. Run: crontab -e"
        echo "  3. Add the cronjob entry (see README.md for details)"
        return 0
    fi
    
    # Find PHP path
    PHP_PATH=$(find_php_path)
    if [ -z "$PHP_PATH" ]; then
        error "Could not find PHP executable"
        exit $EXIT_PHP_ERROR
    fi
    
    # Get absolute paths
    local script_path="$SCRIPT_DIR/bin/notify.php"
    local cron_log="$SCRIPT_DIR/logs/cron.log"
    
    # Verify script exists
    if [ ! -f "$script_path" ]; then
        error_with_solution "Main script not found: $script_path" \
            "Make sure the script exists in the project"
        exit $EXIT_SCRIPT_ERROR
    fi
    
    # Convert interval to cron format
    local cron_schedule=$(minutes_to_cron "$interval")
    
    # Create cronjob entry
    local cron_entry="$cron_schedule $PHP_PATH $script_path >> $cron_log 2>&1"
    
    # Get current crontab
    local current_crontab
    current_crontab=$(crontab -l 2>/dev/null)
    local crontab_exists=$?
    
    # Check if cronjob already exists
    if [ $crontab_exists -eq 0 ] && echo "$current_crontab" | grep -qF "$script_path"; then
        info "Cronjob already exists for this script"
        
        # Check if it matches our desired configuration
        if echo "$current_crontab" | grep -qF "$cron_entry"; then
            success "Cronjob is already configured correctly"
            return 0
        else
            warning "Existing cronjob found but with different configuration"
            info "Current cronjob entry:"
            echo "$current_crontab" | grep -F "$script_path" || true
            echo ""
            info "Desired cronjob entry:"
            echo "  $cron_entry"
            echo ""
            warning "Please update the cronjob manually with: crontab -e"
            return 0
        fi
    fi
    
    # Add cronjob
    info "Adding cronjob entry..."
    
    if [ $crontab_exists -eq 0 ]; then
        # Append to existing crontab
        (echo "$current_crontab"; echo "$cron_entry") | crontab - 2>/dev/null
    else
        # Create new crontab
        echo "$cron_entry" | crontab - 2>/dev/null
    fi
    
    if [ $? -eq 0 ]; then
        success "Cronjob added successfully"
        info "Cronjob will run every $interval minute(s)"
        info "Logs will be written to: $cron_log"
        echo ""
        info "To view current cronjobs, run: crontab -l"
        info "To edit cronjobs, run: crontab -e"
        info "To remove cronjob, run: crontab -e (then delete the line)"
    else
        error "Failed to add cronjob"
        echo ""
        echo "Possible reasons:"
        echo "  - No permission to modify crontab"
        echo "  - Cron service not running"
        echo ""
        echo "You can add it manually by running:"
        echo "  crontab -e"
        echo ""
        echo "And adding this line:"
        echo "  $cron_entry"
        warning "Continuing without cronjob setup..."
    fi
}

# Execute main script once (for testing)
run_main_script_once() {
    local script_path="bin/notify.php"
    
    if [ ! -f "$script_path" ]; then
        error_with_solution "Main script not found: $script_path" \
            "Make sure the script exists in the project"
        exit $EXIT_SCRIPT_ERROR
    fi
    
    if [ ! -r "$script_path" ]; then
        error_with_solution "Main script is not readable: $script_path" \
            "Check permissions: chmod 644 $script_path"
        exit $EXIT_SCRIPT_ERROR
    fi
    
    info "Running main script once for testing..."
    php "$script_path"
    local exit_code=$?
    
    if [ $exit_code -eq 0 ]; then
        success "Main script executed successfully"
    else
        error "Main script exited with error code: $exit_code"
        warning "Cronjob was still set up. Check logs for details: logs/event-pager.log"
    fi
}

# Main function
main() {
    info "=== ChaosPagerEventInfos Start Script ==="
    echo ""
    
    # Phase 1: Check PHP and Composer
    check_php_version
    check_composer
    echo ""
    
    # Phase 2: Install dependencies
    install_composer_dependencies
    echo ""
    
    # Phase 3: Create .env file
    create_env_file
    echo ""
    
    # Phase 4: Create log directory
    create_log_directory
    echo ""
    
    # Phase 5: Validate .env
    validate_env_file
    echo ""
    
    # Phase 6: Setup cronjob (if user wants)
    if ask_setup_cronjob; then
        setup_cronjob
    else
        info "Cronjob setup skipped by user"
        echo ""
        info "To set up cronjob later, you can:"
        echo "  1. Run this script again and answer 'y' to the cronjob question"
        echo "  2. Or manually edit crontab: crontab -e"
        echo ""
        info "To configure the interval, set CRON_INTERVAL_MINUTES environment variable:"
        echo "  export CRON_INTERVAL_MINUTES=10  # Run every 10 minutes"
        echo "  ./start.sh"
    fi
    echo ""
    
    # Phase 7: Run main script once for testing
    run_main_script_once
    
    exit $EXIT_SUCCESS
}

# Execute script
main "$@"
