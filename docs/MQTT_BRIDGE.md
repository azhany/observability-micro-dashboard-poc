# MQTT Bridge Documentation

## Overview
The MQTT Bridge service subscribes to MQTT topics and forwards metrics to the ingestion pipeline with authentication and graceful shutdown support.

## Security

### Authentication Setup

**IMPORTANT**: The MQTT broker requires authentication by default for security.

1. **Initial Setup**: Create the password file:
   ```bash
   ./docker/mosquitto/setup-auth.sh
   ```

2. **Default Credentials** (for development):
   - Username: `bridge_user`
   - Password: `bridge_pass`

   **CRITICAL**: Change these credentials in production!

3. **Add Additional Users**:
   ```bash
   docker exec observability-mosquitto mosquitto_passwd -b /mosquitto/config/passwd <username> <password>
   ```

4. **Development Mode** (disable authentication temporarily):
   Edit `docker/mosquitto/config/mosquitto.conf`:
   ```conf
   allow_anonymous true
   # password_file /mosquitto/config/passwd  # comment out
   # acl_file /mosquitto/config/acl          # comment out
   ```
   Then restart: `docker-compose restart mosquitto`

### Access Control Lists (ACL)

The bridge requires write access to:
- `metrics/#` - for receiving metrics
- `bridge/#` - for status messages

ACL configuration is in `docker/mosquitto/config/acl`.

## Running the Bridge

### Standalone Mode
```bash
php artisan mqtt:bridge
```

Default credentials (`bridge_user:bridge_pass`) are used automatically.

### With Custom Credentials
```bash
php artisan mqtt:bridge --username=myuser --password=mypass
```

### With Custom Broker
```bash
php artisan mqtt:bridge --host=mqtt.example.com --port=1883 --username=bridge --password=secret
```

### Development Mode
The bridge is automatically started when running:
```bash
composer dev
```

This starts all services including:
- Laravel server
- Queue worker
- Logs viewer (Pail)
- Vite dev server
- **MQTT Bridge** (with default credentials)

### Production Mode
Use Supervisor for process management. See `docker/supervisor/README.md` for details.

```bash
supervisorctl start mqtt-bridge
```

## Topic Format
The bridge subscribes to: `metrics/#`

Expected topic structure: `metrics/{tenant_id}/{agent_id}/{metric_name}`

## Message Format
Messages must be valid JSON with the following structure:
```json
{
  "value": 123.45,
  "timestamp": "2025-01-18 12:00:00",
  "dedupe_id": "optional-unique-id"
}
```

### Required Fields
- `value`: The metric value (number or string)

### Optional Fields
- `timestamp`: ISO 8601 timestamp (defaults to current time if not provided)
- `dedupe_id`: Unique identifier for deduplication

## Testing with mosquitto_pub

### With Authentication (Default)

#### From Host Machine
```bash
mosquitto_pub -h localhost -p 1883 \
  -u bridge_user -P bridge_pass \
  -t "metrics/{tenant_uuid}/{agent_id}/{metric_name}" \
  -m '{"value": 100, "timestamp": "2025-01-18 12:00:00"}'
```

#### From Docker Container
```bash
docker exec observability-mosquitto mosquitto_pub \
  -u bridge_user -P bridge_pass \
  -t "metrics/{tenant_uuid}/{agent_id}/{metric_name}" \
  -m '{"value": 100}'
```

### Without Authentication (Dev Only)
If anonymous access is enabled:
```bash
mosquitto_pub -h localhost -p 1883 \
  -t "metrics/{tenant_uuid}/{agent_id}/{metric_name}" \
  -m '{"value": 100}'
```

## Error Handling
The bridge handles the following error cases:
- Authentication failures
- Invalid topic format (not matching `metrics/{tenant_id}/{agent_id}/{metric_name}`)
- Unknown tenant ID
- Invalid JSON payload
- Missing required fields

All errors are logged for debugging purposes.

## Graceful Shutdown

The bridge supports graceful shutdown via signals:
- `SIGTERM` - Sent by Supervisor or `docker stop`
- `SIGINT` - Sent by Ctrl+C

On shutdown, the bridge:
1. Stops accepting new messages
2. Disconnects cleanly from MQTT broker
3. Exits with proper status code

## Monitoring

### Log Viewing

Development:
```bash
php artisan pail
```

Production (Supervisor):
```bash
tail -f /var/log/supervisor/mqtt-bridge.log
tail -f /var/log/supervisor/mqtt-bridge-error.log
```

### Process Status

```bash
supervisorctl status mqtt-bridge
```

### Memory Monitoring (PERF-001 Risk)

The bridge is a long-running PHP process. Monitor memory usage:

**Docker Stats**:
```bash
docker stats observability-app
```

**Within Container**:
```bash
docker exec observability-app ps aux | grep mqtt:bridge
```

**Expected Memory Usage**:
- Initial: ~30-50MB
- Stable: ~50-100MB (depends on message volume)
- Alert threshold: >200MB or steady growth over time

**If Memory Leaks Detected**:
1. Check logs for unusual errors
2. Review message processing logic
3. Consider restarting bridge periodically (e.g., daily via cron + Supervisor)

### Connection Monitoring

Monitor for disconnections:
```bash
tail -f /var/log/supervisor/mqtt-bridge.log | grep -i "disconnect\|error"
```

The bridge will automatically reconnect on network issues when managed by Supervisor.

## Security Best Practices

1. **Change Default Passwords**: Never use `bridge_pass` in production
2. **Use Environment Variables**: Store credentials in `.env` or secrets manager
3. **Enable TLS**: For production, configure Mosquitto with TLS/SSL
4. **Restrict ACLs**: Limit topic access per the principle of least privilege
5. **Monitor Authentication Failures**: Alert on repeated failed auth attempts
6. **Rotate Credentials**: Implement regular password rotation policy

## Troubleshooting

### Bridge Won't Connect
- Check Mosquitto is running: `docker ps | grep mosquitto`
- Verify credentials: Try `mosquitto_pub` with same credentials
- Check logs: `docker logs observability-mosquitto`

### Authentication Errors
- Ensure password file exists: `ls -l docker/mosquitto/config/passwd`
- Verify username in ACL: Check `docker/mosquitto/config/acl`
- Restart Mosquitto after password changes: `docker-compose restart mosquitto`

### High Memory Usage
- Enable debug logging: `php artisan mqtt:bridge --verbose`
- Check message volume: High throughput may require optimization
- Consider implementing message batching

### Bridge Crashes
- Check error logs: `docker logs observability-app`
- Verify Supervisor is configured (production)
- Ensure PCNTL extension is installed for signal handling
