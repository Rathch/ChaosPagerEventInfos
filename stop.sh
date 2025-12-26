#!/bin/bash

# stop.sh - Stop/remove cronjobs for ChaosPagerEventInfos
# 
# Lists all cronjobs related to this project and allows the user
# to select which ones to remove.

set -u  # Exit on undefined variable
# Note: We don't use 'set -e' because we need to handle crontab errors gracefully

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Determine script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Exit codes
EXIT_SUCCESS=0
EXIT_GENERAL_ERROR=1
EXIT_PERMISSION_ERROR=2
EXIT_NO_CRONJOBS=3

# Helper functions for output
error() {
    echo -e "${RED}✗${NC} $1" >&2
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

highlight() {
    echo -e "${BLUE}$1${NC}"
}

# Check if crontab command is available
check_crontab_available() {
    if ! command -v crontab &> /dev/null; then
        error "crontab command not found"
        echo ""
        echo "crontab is required to manage cronjobs."
        echo "Install it using:"
        echo "  Debian/Ubuntu: sudo apt install cron"
        echo "  CentOS/RHEL:   sudo yum install cronie"
        exit $EXIT_GENERAL_ERROR
    fi
}

# Get all cronjobs related to this project
get_project_cronjobs() {
    local script_path="$SCRIPT_DIR/bin/notify.php"
    local script_name="notify.php"
    
    # Get current crontab
    local current_crontab
    current_crontab=$(crontab -l 2>/dev/null)
    local crontab_exists=$?
    
    if [ $crontab_exists -ne 0 ]; then
        return 1
    fi
    
    # Find all lines that contain the script path or script name
    # and are not comments
    local line_num=0
    local cronjobs=()
    
    while IFS= read -r line; do
        line_num=$((line_num + 1))
        
        # Skip empty lines and comments
        if [[ -z "$line" ]] || [[ "$line" =~ ^[[:space:]]*# ]]; then
            continue
        fi
        
        # Check if line contains our script
        if echo "$line" | grep -qF "$script_path" || echo "$line" | grep -qF "$script_name"; then
            cronjobs+=("$line_num|$line")
        fi
    done <<< "$current_crontab"
    
    # Return cronjobs as array (will be stored in global variable)
    CRONJOBS_ARRAY=("${cronjobs[@]}")
    
    return 0
}

# Display cronjobs list
display_cronjobs() {
    local cronjobs=("${CRONJOBS_ARRAY[@]}")
    
    if [ ${#cronjobs[@]} -eq 0 ]; then
        return 1
    fi
    
    echo ""
    highlight "Found ${#cronjobs[@]} cronjob(s) related to this project:"
    echo ""
    
    local index=1
    for cronjob in "${cronjobs[@]}"; do
        local line_num=$(echo "$cronjob" | cut -d'|' -f1)
        local line=$(echo "$cronjob" | cut -d'|' -f2-)
        
        echo "  [$index] Line $line_num:"
        echo "      $line"
        echo ""
        index=$((index + 1))
    done
    
    return 0
}

# Remove selected cronjobs
remove_cronjobs() {
    local selected_indices=("$@")
    local cronjobs=("${CRONJOBS_ARRAY[@]}")
    
    # Get current crontab
    local current_crontab
    current_crontab=$(crontab -l 2>/dev/null)
    
    # Build list of line numbers to remove
    local lines_to_remove=()
    
    for index in "${selected_indices[@]}"; do
        # Convert to 0-based array index
        local array_index=$((index - 1))
        
        if [ $array_index -ge 0 ] && [ $array_index -lt ${#cronjobs[@]} ]; then
            local line_num=$(echo "${cronjobs[$array_index]}" | cut -d'|' -f1)
            lines_to_remove+=("$line_num")
        fi
    done
    
    # Sort line numbers in reverse order (remove from bottom to top)
    IFS=$'\n' sorted_lines=($(sort -rn <<<"${lines_to_remove[*]}"))
    unset IFS
    
    # Remove lines from crontab
    local new_crontab="$current_crontab"
    
    for line_num in "${sorted_lines[@]}"; do
        # Remove the line using sed
        new_crontab=$(echo "$new_crontab" | sed "${line_num}d")
    done
    
    # Update crontab
    if [ -z "$new_crontab" ] || [ "$new_crontab" = $'\n' ]; then
        # If crontab is empty after removal, delete it
        crontab -r 2>/dev/null || true
    else
        echo "$new_crontab" | crontab -
    fi
    
    return $?
}

# Ask user to select cronjobs to remove
ask_select_cronjobs() {
    local cronjobs=("${CRONJOBS_ARRAY[@]}")
    
    echo ""
    info "Select cronjob(s) to remove:"
    echo "  - Enter numbers separated by spaces (e.g., '1 3' to remove jobs 1 and 3)"
    echo "  - Enter 'all' to remove all listed cronjobs"
    echo "  - Enter 'q' or press Enter to cancel"
    echo ""
    
    while true; do
        read -p "Selection: " -r response
        
        # Handle empty input (cancel)
        if [ -z "$response" ]; then
            return 1
        fi
        
        # Handle 'q' (quit)
        if [ "$response" = "q" ] || [ "$response" = "Q" ]; then
            return 1
        fi
        
        # Handle 'all'
        if [ "$response" = "all" ] || [ "$response" = "ALL" ]; then
            SELECTED_INDICES=()
            for i in $(seq 1 ${#cronjobs[@]}); do
                SELECTED_INDICES+=("$i")
            done
            return 0
        fi
        
        # Parse numbers
        local valid=true
        local indices=()
        
        for num in $response; do
            # Check if it's a valid number
            if ! [[ "$num" =~ ^[0-9]+$ ]]; then
                warning "Invalid input: '$num'. Please enter numbers only."
                valid=false
                break
            fi
            
            # Check if number is in valid range
            if [ "$num" -lt 1 ] || [ "$num" -gt ${#cronjobs[@]} ]; then
                warning "Number $num is out of range (1-${#cronjobs[@]})."
                valid=false
                break
            fi
            
            indices+=("$num")
        done
        
        if [ "$valid" = true ]; then
            SELECTED_INDICES=("${indices[@]}")
            return 0
        fi
    done
}

# Confirm removal
confirm_removal() {
    local count=$1
    
    echo ""
    if [ $count -eq 1 ]; then
        warning "You are about to remove 1 cronjob."
    else
        warning "You are about to remove $count cronjob(s)."
    fi
    echo ""
    
    while true; do
        read -p "Are you sure? (y/n) [n]: " -r response
        response=${response:-n}
        
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

# Main function
main() {
    info "=== ChaosPagerEventInfos Stop Script ==="
    echo ""
    
    # Check if crontab is available
    check_crontab_available
    
    # Get project cronjobs
    if ! get_project_cronjobs; then
        info "No crontab found or no cronjobs related to this project."
        echo ""
        info "To check manually, run: crontab -l"
        exit $EXIT_NO_CRONJOBS
    fi
    
    # Display cronjobs
    if ! display_cronjobs; then
        success "No cronjobs found for this project."
        exit $EXIT_SUCCESS
    fi
    
    # Ask user to select cronjobs
    if ! ask_select_cronjobs; then
        info "Operation cancelled. No cronjobs were removed."
        exit $EXIT_SUCCESS
    fi
    
    # Confirm removal
    local count=${#SELECTED_INDICES[@]}
    if ! confirm_removal "$count"; then
        info "Operation cancelled. No cronjobs were removed."
        exit $EXIT_SUCCESS
    fi
    
    # Remove selected cronjobs
    echo ""
    info "Removing selected cronjob(s)..."
    
    if remove_cronjobs "${SELECTED_INDICES[@]}"; then
        success "Successfully removed $count cronjob(s)."
        echo ""
        info "Remaining cronjobs:"
        crontab -l 2>/dev/null | grep -n "notify.php" || info "  (none)"
    else
        error "Failed to remove cronjob(s)."
        echo ""
        echo "You can try manually:"
        echo "  1. Run: crontab -e"
        echo "  2. Delete the lines you want to remove"
        echo "  3. Save and exit"
        exit $EXIT_GENERAL_ERROR
    fi
    
    exit $EXIT_SUCCESS
}

# Execute script
main "$@"

