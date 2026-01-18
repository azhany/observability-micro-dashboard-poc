# Supervisor Configuration for MQTT Bridge

## Overview

This directory contains Supervisor configuration for managing the MQTT Bridge as a long-running process in production environments.

## Files

- `mqtt-bridge.conf` - Supervisor program configuration for the bridge

## Production Deployment

### Option 1: Add to Existing Supervisor Container

If you're already using Supervisor in your application container:

1. Copy the config file to your Supervisor conf.d directory:
   ```bash
   cp docker/supervisor/mqtt-bridge.conf /etc/supervisor/conf.d/
   ```

2. Set the bridge password environment variable:
   ```bash
   export MQTT_BRIDGE_PASSWORD=your_secure_password
   ```

3. Reload Supervisor:
   ```bash
   supervisorctl reread
   supervisorctl update
   supervisorctl start mqtt-bridge
   ```

### Option 2: Modify Dockerfile

Add Supervisor to your application Dockerfile:

```dockerfile
# Install Supervisor
RUN apt-get update && apt-get install -y supervisor

# Copy Supervisor config
COPY docker/supervisor/mqtt-bridge.conf /etc/supervisor/conf.d/

# Create log directory
RUN mkdir -p /var/log/supervisor

# Start Supervisor instead of php-fpm
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
```

### Option 3: Separate Container (Recommended)

Create a dedicated container for the bridge in docker-compose.yml:

```yaml
  mqtt-bridge:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: observability-mqtt-bridge
    restart: unless-stopped
    working_dir: /var/www/html
    command: supervisord -n -c /etc/supervisor/supervisord.conf
    volumes:
      - ./app:/var/www/html
      - ./docker/supervisor/mqtt-bridge.conf:/etc/supervisor/conf.d/mqtt-bridge.conf
    environment:
      - MQTT_BRIDGE_PASSWORD=${MQTT_BRIDGE_PASSWORD:-bridge_pass}
      - DB_HOST=db
      - REDIS_HOST=redis
    networks:
      - observability-network
    depends_on:
      - mosquitto
      - redis
```

## Monitoring

### Check Status
```bash
supervisorctl status mqtt-bridge
```

### View Logs
```bash
tail -f /var/log/supervisor/mqtt-bridge.log
tail -f /var/log/supervisor/mqtt-bridge-error.log
```

### Restart Bridge
```bash
supervisorctl restart mqtt-bridge
```

### Stop Bridge
```bash
supervisorctl stop mqtt-bridge
```

## Configuration Details

- **Auto-restart**: Enabled - process will restart if it crashes
- **Start retries**: 3 attempts before giving up
- **Grace period**: 5 seconds before marking as started
- **Stop timeout**: 10 seconds for graceful shutdown
- **Log rotation**: Automatic with 5 backup files, 10MB max per file

## Security Notes

- The bridge password should be set via environment variable (`MQTT_BRIDGE_PASSWORD`)
- Never commit production passwords to version control
- Use `.env` files or secret management systems for credentials

## Development

For local development, you can still use:
```bash
composer dev
```

Supervisor is primarily for production/staging environments where process resilience is critical.
