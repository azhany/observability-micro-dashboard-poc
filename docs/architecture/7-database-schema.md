# 7. Database Schema

## Table: `metrics_raw`
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `tenant_id`: BINARY(16) NOT NULL (UUID)
- `agent_id`: VARCHAR(64)
- `metric_name`: VARCHAR(64)
- `value`: DOUBLE(16, 4)
- `timestamp`: TIMESTAMP(6)
- `dedupe_id`: VARCHAR(128) UNIQUE
- INDEX `idx_tenant_metric_time` (`tenant_id`, `metric_name`, `timestamp` DESC)

## Table: `metrics_1m` (Rollup)
- `id`: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `tenant_id`: BINARY(16)
- `metric_name`: VARCHAR(64)
- `avg_value`: DOUBLE
- `window_start`: TIMESTAMP
- INDEX `idx_historical` (`tenant_id`, `window_start`)

---
