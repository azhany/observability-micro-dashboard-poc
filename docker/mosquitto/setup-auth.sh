#!/bin/bash
# Setup Mosquitto Authentication
#
# This script creates the initial password file for Mosquitto authentication

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_DIR="$SCRIPT_DIR/config"
PASSWD_FILE="$CONFIG_DIR/passwd"

echo "=== Mosquitto Authentication Setup ==="
echo ""

# Check if running from correct directory
if [ ! -f "$CONFIG_DIR/mosquitto.conf" ]; then
    echo "Error: Must run from project root or mosquitto directory"
    exit 1
fi

# Create password file for bridge user
echo "Creating password file at: $PASSWD_FILE"
echo ""
echo "Setting up 'bridge_user' account (for the MQTT bridge service)"
echo "Default password: bridge_pass (CHANGE THIS IN PRODUCTION)"
echo ""

# Create password file with bridge_user
# Using -b for batch mode (non-interactive)
docker run --rm -v "$CONFIG_DIR:/mosquitto/config" eclipse-mosquitto:2.0 \
    mosquitto_passwd -b -c /mosquitto/config/passwd bridge_user bridge_pass

echo ""
echo "✓ Password file created successfully"
echo ""
echo "To add more users:"
echo "  docker exec observability-mosquitto mosquitto_passwd -b /mosquitto/config/passwd <username> <password>"
echo ""
echo "To change the bridge_user password:"
echo "  docker exec observability-mosquitto mosquitto_passwd -b /mosquitto/config/passwd bridge_user <new_password>"
echo ""
echo "IMPORTANT: Update the bridge command connection with credentials:"
echo "  php artisan mqtt:bridge --username=bridge_user --password=bridge_pass"
echo ""
echo "Don't forget to restart Mosquitto after any password changes:"
echo "  docker-compose restart mosquitto"
echo ""
