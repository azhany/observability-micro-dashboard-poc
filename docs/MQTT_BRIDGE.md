# MQTT Bridge Documentation

## Overview
The MQTT Bridge service subscribes to MQTT topics and forwards metrics to the ingestion pipeline.

## Running the Bridge

### Standalone Mode
To run the bridge command standalone:
```bash
php artisan mqtt:bridge
```

### With Options
You can customize the MQTT broker connection:
```bash
php artisan mqtt:bridge --host=mosquitto --port=1883
```

### Development Mode
The bridge is automatically started when running the development environment:
```bash
composer dev
```

This will start all services including:
- Laravel server
- Queue worker
- Logs viewer (Pail)
- Vite dev server
- **MQTT Bridge**

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

### From Host Machine
```bash
mosquitto_pub -h localhost -p 1883 \
  -t "metrics/{tenant_uuid}/{agent_id}/{metric_name}" \
  -m '{"value": 100, "timestamp": "2025-01-18 12:00:00"}'
```

### From Docker Container
```bash
docker exec observability-mosquitto mosquitto_pub \
  -t "metrics/{tenant_uuid}/{agent_id}/{metric_name}" \
  -m '{"value": 100}'
```

## Error Handling
The bridge handles the following error cases:
- Invalid topic format (not matching `metrics/{tenant_id}/{agent_id}/{metric_name}`)
- Unknown tenant ID
- Invalid JSON payload
- Missing required fields

All errors are logged for debugging purposes.

## Monitoring
Check the logs output in the `logs` window when running `composer dev`, or use:
```bash
php artisan pail
```
