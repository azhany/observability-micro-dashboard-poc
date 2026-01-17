# 4. Data Models

## Tenant
**Purpose:** Root entity for multi-tenant isolation.
**Key Attributes:**
- `id`: UUID - Primary Key.
- `name`: String - Display name.
- `settings`: JSON - Tenant-specific thresholds or preferences.

## Metric (Raw)
**Purpose:** High-fidelity storage for recent metrics.
**Key Attributes:**
- `id`: BIGINT - Primary Key.
- `tenant_id`: UUID - Foreign Key.
- `agent_id`: String - Identifier for the source.
- `metric_name`: String - e.g., `cpu_usage`.
- `value`: Double - Numeric value.
- `timestamp`: Timestamp - Event time.
- `dedupe_id`: String (Unique) - Prevent duplicate ingestion.

## AlertRule
**Purpose:** Defines conditions for stateful alerting.
**Key Attributes:**
- `id`: UUID - Primary Key.
- `tenant_id`: UUID - Foreign Key.
- `metric_name`: String - Metric to monitor.
- `operator`: Enum (>, <, =, >=, <=).
- `threshold`: Double - Value to check against.
- `duration`: Integer - Seconds condition must persist.

---
