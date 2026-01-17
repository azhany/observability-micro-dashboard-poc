# 6. Epic Details

## Epic 1: Project Foundation & HTTP Ingestion
**Goal:** Establish the foundational infrastructure, including a containerized development environment, multi-tenant authentication framework, and a high-performance HTTP API for metric ingestion. This epic ensures that the "Ingest" portion of the "Ingest -> Chart" smoke test is functional.

### Story 1.1: Project Scaffolding & Docker Environment
**As a** Developer,
**I want** a standardized Docker Compose environment with Laravel 11, MariaDB, and Redis,
**so that** I can develop and test the PoC in a consistent, portable environment.

*   **Acceptance Criteria:**
    1.  `docker-compose.yml` includes containers for PHP (App), MariaDB (DB), Redis (Cache/Queue), and Nginx.
    2.  Laravel 11 project is initialized with Inertia.js and Vue 3 scaffolding.
    3.  A "Health Check" route exists that verifies DB and Redis connectivity.
    4.  The environment supports `USE_MCP=true` for local agent interaction.

### Story 1.2: Multi-tenant Auth & API Token Management
**As a** System Operator,
**I want** to generate secure API tokens for different tenants,
**so that** metric ingestion is isolated and authenticated.

*   **Acceptance Criteria:**
    1.  Database schema includes a `tenants` table and a `tokens` table (linked to tenants).
    2.  An Artisan command or internal API exists to create a new Tenant and generate an associated API token.
    3.  Middleware validates the `Authorization: Bearer <token>` header on protected routes.
    4.  Requests with invalid tokens return a `401 Unauthorized` response.

### Story 1.3: HTTP Metric Ingestion API (Single & Bulk)
**As an** Application Developer,
**I want** an API endpoint to push metrics via HTTP (JSON),
**so that** my services can report data to the micro-dashboard.

*   **Acceptance Criteria:**
    1.  Endpoint `POST /api/v1/metrics` accepts a JSON payload containing `metric_name`, `value`, `timestamp`, and optional `tags`.
    2.  Endpoint supports bulk ingestion (array of metric objects).
    3.  The API returns a `202 Accepted` status code for valid payloads.
    4.  Basic validation ensures `metric_name` and `value` are present and correctly formatted.

### Story 1.4: Ingestion Processing & Idempotency
**As a** System Operator,
**I want** metrics to be processed asynchronously and deduplicated,
**so that** the API remains fast and data remains consistent despite network retries.

*   **Acceptance Criteria:**
    1.  Ingested metrics are dispatched to a Redis-backed Laravel Job.
    2.  Job worker persists metrics to the `metrics_raw` table in MariaDB.
    3.  The system uses a `dedupe_id` (if provided in payload) to prevent duplicate entries for the same event.
    4.  Failed jobs are automatically retried with exponential backoff.

## Epic 2: Real-time Visualization & Live Dashboard
**Goal:** Implement the reactive frontend and real-time streaming layer. By the end of this epic, users will be able to visualize ingested metrics on a live-updating dashboard using Server-Sent Events (SSE), completing the "Ingest -> Chart" smoke test.

### Story 2.1: Base Dashboard Layout & Tenant View
**As a** System Operator,
**I want** a modern, dark-mode dashboard interface,
**so that** I can view a high-level summary of my tenant's health.

*   **Acceptance Criteria:**
    1.  Vue 3 dashboard features a sidebar for navigation and a main content area for widgets.
    2.  An "Overview" page displays a list of active agents and their last reported status.
    3.  A "Tenant Detail" page exists that accepts a tenant ID and prepares placeholders for metric charts.
    4.  UI is styled using Tailwind CSS with a consistent "Technical/NOC" aesthetic.

### Story 2.2: SSE Streaming Infrastructure
**As a** System Operator,
**I want** metrics to be streamed to my browser as they are ingested,
**so that** I don't have to manually refresh the page or rely on heavy polling.

*   **Acceptance Criteria:**
    1.  Laravel implementation of an SSE endpoint `GET /api/v1/stream/{tenant}`.
    2.  The stream broadcasts new metric events immediately after they are persisted to the database.
    3.  Redis Pub/Sub or a similar mechanism is used to bridge the background ingestion job to the SSE controller.
    4.  Connection management ensures that streams are closed correctly when a user leaves the page.

### Story 2.3: Reactive Metric Chart Component (Live Mode)
**As a** System Operator,
**I want** to see metrics visualized on a live-updating line chart,
**so that** I can observe trends in real-time.

*   **Acceptance Criteria:**
    1.  A reusable Vue 3 chart component (e.g., using Chart.js or ApexCharts) is implemented.
    2.  The component listens to the SSE stream and updates its data points without a full re-render.
    3.  The chart supports a "Live" mode indicator (e.g., a pulsing red dot).
    4.  Performance is optimized to handle high-frequency updates (e.g., 1 update/sec) without lag.

### Story 2.4: E2E Smoke Test (Ingest -> Chart)
**As a** QA Engineer,
**I want** an automated test that simulates the full data flow,
**so that** I can verify the core PoC value proposition.

*   **Acceptance Criteria:**
    1.  A Playwright BDD scenario is created: "GIVEN a tenant exists, WHEN I POST a metric, THEN it appears on the Live Dashboard chart within 500ms."
    2.  The test runs in the containerized environment.
    3.  The test handles the asynchronous nature of SSE and background jobs (using appropriate wait/assertions).

## Epic 3: MQTT Integration & Go System Agent
**Goal:** Expand the ingestion capabilities to support IoT/Edge use cases. This epic involves setting up an MQTT broker, creating a bridge to the Laravel core, and developing the Go-based agent for automated system monitoring.

### Story 3.1: MQTT Broker & Bridge Infrastructure
**As a** System Operator,
**I want** an MQTT broker (Mosquitto) integrated into the environment,
**so that** I can ingest metrics from edge devices.

*   **Acceptance Criteria:**
    1.  Mosquitto container added to `docker-compose.yml`.
    2.  A "Bridge" service (either a separate Go process or a Laravel Artisan command) subscribes to `metrics/#`.
    3.  The bridge parses the topic structure `metrics/{tenant_id}/{agent_id}/{metric_name}`.
    4.  Parsed messages are forwarded to the existing ingestion pipeline (reusing the same Redis job as HTTP ingestion).

### Story 3.2: Go System Agent - Metric Collection
**As a** System Operator,
**I want** a lightweight binary that collects system stats (CPU, Memory, Disk),
**so that** I don't have to write custom scripts for basic server monitoring.

*   **Acceptance Criteria:**
    1.  Go 1.2x project initialized in the monorepo (e.g., `/agent`).
    2.  Agent successfully collects CPU percentage, Memory usage (bytes), and Disk usage (percentage).
    3.  Collection interval is configurable via environment variables or a config file.
    4.  The agent outputs metrics to stdout for local debugging.

### Story 3.3: Go System Agent - MQTT Publisher
**As a** System Operator,
**I want** the Go agent to push its collected metrics to the MQTT broker,
**so that** they can be visualized on the dashboard.

*   **Acceptance Criteria:**
    1.  Go agent implements an MQTT client that connects to the Mosquitto broker.
    2.  Agent publishes metrics using the required topic structure: `metrics/{tenant_id}/{agent_id}/{metric_name}`.
    3.  Agent handles connection retries gracefully.
    4.  Agent includes an `agent_id` and `timestamp` in every published payload.

### Story 3.4: Multi-Agent Management View
**As a** System Operator,
**I want** to see all active agents for my tenant on the dashboard,
**so that** I can monitor my entire fleet from one place.

*   **Acceptance Criteria:**
    1.  Dashboard includes an "Agents" list showing `agent_id`, `status` (Online/Offline), and `last_seen`.
    2.  "Status" logic is based on the frequency of MQTT messages (e.g., Offline if no message in > 2x the collection interval).
    3.  Clicking an agent filters the main dashboard charts to show only that agent's metrics.

## Epic 4: Data Retention, Alerting & Notifications
**Goal:** Implement the "Intelligent" features of the platform. This includes automated data downsampling for historical analysis and a stateful alerting engine that notifies users when metrics breach defined thresholds.

### Story 4.1: Automated Downsampling (1m & 5m Rollups)
**As a** System Operator,
**I want** raw metrics to be automatically summarized into 1-minute and 5-minute averages,
**so that** I can view long-term historical trends without loading millions of raw data points.

*   **Acceptance Criteria:**
    1.  Laravel Scheduler or a background worker runs every minute to calculate averages from `metrics_raw`.
    2.  Aggregated data is stored in `metrics_1m` and `metrics_5m` tables.
    3.  A retention policy is implemented (e.g., raw data deleted after 24h, 1m data after 7 days, 5m data after 30 days).
    4.  The API supports a `resolution` parameter to query these rollup tables.

### Story 4.2: Stateful Alerting Engine (Rule Evaluator)
**As a** System Operator,
**I want** to define thresholds (e.g., CPU > 90%) that trigger alerts,
**so that** I am informed of system issues automatically.

*   **Acceptance Criteria:**
    1.  Database schema for `alert_rules` (metric_name, operator, threshold, duration, tenant_id).
    2.  An evaluator job runs periodically (e.g., every 30s) to check rules against recent metrics.
    3.  Alerts are stateful: they transition from `OK` -> `PENDING` -> `FIRING` based on the rule's duration.
    4.  An `alerts` table tracks the history of these state transitions.

### Story 4.3: Notification Dispatch (Webhook & Email)
**As a** System Operator,
**I want** to receive notifications when an alert enters the `FIRING` state,
**so that** I can respond to issues immediately even when not looking at the dashboard.

*   **Acceptance Criteria:**
    1.  The system implements a notification dispatcher.
    2.  Webhook channel: Sends a POST request with alert details to a user-defined URL.
    3.  Email channel: Sends a basic alert email (can be simulated with Mailpit/Log for PoC).
    4.  Notifications are only sent on state transitions (e.g., only when an alert *starts* firing, not every time it is evaluated).

### Story 4.4: Historical Trend Charting
**As a** System Operator,
**I want** to toggle my dashboard charts to show 1-hour, 24-hour, and 7-day views,
**so that** I can perform root cause analysis using historical data.

*   **Acceptance Criteria:**
    1.  Chart components are updated to support a "Time Range" selector.
    2.  Selecting a range > 1 hour automatically switches the data source from the SSE stream/raw table to the 1m or 5m rollup tables.
    3.  The UI provides a seamless transition between "Live" and "Historical" modes.
    4.  Alert markers (e.g., red vertical lines) are overlaid on the chart where state transitions occurred.

---
