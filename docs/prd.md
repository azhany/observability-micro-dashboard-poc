# Product Requirements Document (PRD)

## 1. Goals and Background Context

### Goals
*   **Demonstrate a functional multi-tenant ingestion pipeline** capable of handling both HTTP and MQTT data sources.
*   **Validate "Live Stream" capabilities** using Server-Sent Events (SSE) to ensure sub-second dashboard updates, proving it as a viable alternative to heavy polling.
*   **Achieve high-performance metrics:** Ingestion latency < 500ms and dashboard load times < 1s.
*   **Provide a unified "micro-dashboard"** that simplifies monitoring for system operators and developers, bridging the gap between complex enterprise suites and fragmented tools.
*   **Deliver a complete full-stack solution**, featuring a lightweight Go-based system agent for collection and a reactive Vue 3 dashboard for visualization.

### Background Context
The current observability landscape is split between complex, expensive enterprise suites (like Datadog) and fragmented open-source tools that require significant "glue" code. This dichotomy leaves a gap for small-to-medium deployments and IoT fleets, where high latency in "live" charts and resource overhead are major pain points.

The Observability Micro-Dashboard PoC aims to fill this gap by providing a unified, lightweight platform that prioritizes ingestion speed and live interactivity. By combining a high-performance Go agent for low-level metrics with a Laravel-based core for multi-tenant management and alerting, this solution offers a streamlined, "test-first" approach to monitoring modern microservices and hybrid HTTP/MQTT environments.

### Change Log
| Date | Version | Description | Author |
| :--- | :--- | :--- | :--- |
| 2026-01-18 | 0.1 | Initial Draft based on Project Brief | PM Agent |

---

## 2. Requirements

### Functional Requirements (FR)

**Ingestion & Data Processing**
*   **FR1:** The system must provide an HTTP API endpoint for single and bulk metric ingestion, protected by API token authentication.
*   **FR2:** The system must act as an MQTT subscriber to ingest metrics from a broker, following the topic pattern `metrics/{tenant}/#`.
*   **FR3:** The system must support `dedupe_id` in ingestion payloads to ensure idempotency.
*   **FR4:** The system must automatically downsample raw metrics into 1-minute and 5-minute rollups for long-term storage.

**Dashboard & Visualization**
*   **FR5:** The dashboard must display real-time metric updates using Server-Sent Events (SSE) as the primary mechanism.
*   **FR6:** The dashboard must support multi-tenant isolation, ensuring users only see data belonging to their specific environment/client.
*   **FR7:** The dashboard must provide visualization widgets for standard metrics (CPU, Memory, Disk) and custom metrics.

**Alerting**
*   **FR8:** The system must allow users to define stateful alerting rules (e.g., threshold breaches).
*   **FR9:** The system must manage state transitions for alerts (e.g., OK -> FIRING) to prevent alert fatigue.
*   **FR10:** The system must support notification dispatch via Webhook and Email channels when an alert fires.

**System Agent**
*   **FR11:** A Go-based system agent must be provided to collect and push CPU, Memory, and Disk usage metrics via MQTT.

### Non-Functional Requirements (NFR)

**Performance & Latency**
*   **NFR1:** Ingestion latency (time from ingestion to query availability) must be less than 500ms (excluding rollups).
*   **NFR2:** Primary dashboard pages must load in under 1 second.
*   **NFR3:** The "Mean Time To Detection" (MTTD) from metric breach to alert notification must be less than 60 seconds.
*   **NFR4:** The system must support an ingestion throughput of at least 100 metrics per second on baseline PoC hardware.

**Reliability & Scalability**
*   **NFR5:** Database writes for ingestion must be processed via background jobs to ensure high availability and quick API response (`202 Accepted`).
*   **NFR6:** The dashboard's live streaming must degrade gracefully (fallback mechanism) if SSE is not supported or stable in the client's browser/proxy.

**Security**
*   **NFR7:** Multi-tenant data must be logically isolated at the application level to prevent data leakage between tenants.
*   **NFR8:** All API access must be authenticated via secure tokens.

**Development & Testing**
*   **NFR9:** The project must be containerized using Docker Compose for single-node deployment.
*   **NFR10:** Development tools and configuration must be compatible with MCP-based local tooling.
*   **NFR11:** The codebase must support a "test-first" approach, including Playwright BDD scenarios for critical paths like "Ingest -> Chart".

---

## 3. User Interface Design Goals

### Overall UX Vision
The dashboard should feel "alive" and responsive. The focus is on high-density information display without clutter, allowing System Operators to spot anomalies instantly. The interface should follow a "dark-mode first" aesthetic typical of monitoring tools, emphasizing high-contrast data visualization.

### Key Interaction Paradigms
*   **Live Stream Toggle:** A global or per-widget control to enable/disable real-time SSE updates.
*   **Time-Range Scrubbing:** Seamlessly switching between live view and historical data (using the 1m/5m rollups).
*   **Drill-Down:** Clicking a metric widget to see detailed logs or expanded view of that specific tenant's metrics.

### Core Screens and Views
*   **Auth/Onboarding:** Token generation and tenant setup view.
*   **Main Multi-Tenant Dashboard:** A high-level view of all active tenants/agents and their last reported status.
*   **Tenant Detail View:** Specific dashboard for a single tenant featuring CPU, Mem, Disk, and custom metrics charts.
*   **Alert Configuration:** A form-based interface for defining thresholds and notification channels.
*   **Alert History/Inbox:** A view showing active "FIRING" alerts and historical state transitions.

### Accessibility
**Level:** WCAG AA
We will aim for WCAG AA compliance, focusing on color contrast for charts and keyboard navigability for critical alert management.

### Branding
Modern, "Technical" aesthetic. Use a monospace font for metric values and a clean sans-serif for UI elements. Tailwind-based styling with a "Micro-Dashboard" theme (minimalist borders, subtle gradients for "live" indicators).

### Target Device and Platforms
**Target:** Web Responsive
Primarily optimized for Desktop (1080p+ dashboards in NOC environments), but fully responsive for mobile triage of alerts.

---

## 4. Technical Assumptions

### Repository Structure
**Structure:** Monorepo
To simplify development of the PoC and ensure synchronized testing of the Laravel core, Vue frontend, and Go System Agent, a **Monorepo** structure is assumed. This allows shared Docker configurations and easier end-to-end testing with Playwright.

### Service Architecture
A **Hybrid Monolith/Edge** architecture will be used:
*   **Core API (Laravel):** Handles authentication, stateful alerting logic, metadata, and long-term storage (MariaDB).
*   **Edge Agent (Go):** A standalone lightweight binary for system metric collection.
*   **Ingestion Pipeline:** Uses Redis and Laravel Jobs for asynchronous processing to ensure high availability during traffic spikes.
*   **Real-time Layer:** Mosquitto for MQTT ingestion and Redis/SSE for dashboard streaming.

### Testing Requirements
A **Full Testing Pyramid** approach is critical for this PoC:
*   **Unit/Integration:** PHPUnit for Laravel backend logic and Vitest for Vue components.
*   **E2E/BDD:** Playwright will be used for critical path validation (e.g., "Agent sends MQTT message -> Dashboard shows live update").
*   **Performance:** Baseline benchmarks for ingestion throughput (100+ metrics/sec) must be repeatable.

### Additional Technical Assumptions and Requests
*   **Inertia.js:** Will be used as the bridge between Laravel and Vue 3 to maintain a monolith-like developer experience with a modern reactive frontend.
*   **Time-Series Lite:** MariaDB is assumed to be sufficient for the PoC scale, utilizing standard relational indexing for the 1m/5m rollups.
*   **MQTT Topic Structure:** Topics will strictly follow `metrics/{tenant}/{agent_id}/{metric_name}`.
*   **SSE Stability:** We assume Nginx/Web-server configuration will support persistent connections required for SSE.
*   **MCP Compatibility:** All environment variables and configurations must support `USE_MCP=true` for local development.

---

## 5. Epic List

*   **Epic 1: Project Foundation & HTTP Ingestion**
    *   Goal: Establish the core multi-tenant framework, containerized environment, and a functional HTTP endpoint for basic metric ingestion.
*   **Epic 2: Real-time Visualization & Live Dashboard**
    *   Goal: Build the reactive Vue dashboard and implement SSE-powered live updates to validate the sub-second visualization requirement.
*   **Epic 3: MQTT Integration & Go System Agent**
    *   Goal: Extend the ingestion pipeline to support MQTT and develop the lightweight Go binary for automated system metric collection.
*   **Epic 4: Data Retention, Alerting & Notifications**
    *   Goal: Implement automated data downsampling (rollups) and stateful alerting rules with Webhook/Email notification channels.

---

## 6. Epic Details

### Epic 1: Project Foundation & HTTP Ingestion
**Goal:** Establish the foundational infrastructure, including a containerized development environment, multi-tenant authentication framework, and a high-performance HTTP API for metric ingestion. This epic ensures that the "Ingest" portion of the "Ingest -> Chart" smoke test is functional.

#### Story 1.1: Project Scaffolding & Docker Environment
**As a** Developer,
**I want** a standardized Docker Compose environment with Laravel 11, MariaDB, and Redis,
**so that** I can develop and test the PoC in a consistent, portable environment.

*   **Acceptance Criteria:**
    1.  `docker-compose.yml` includes containers for PHP (App), MariaDB (DB), Redis (Cache/Queue), and Nginx.
    2.  Laravel 11 project is initialized with Inertia.js and Vue 3 scaffolding.
    3.  A "Health Check" route exists that verifies DB and Redis connectivity.
    4.  The environment supports `USE_MCP=true` for local agent interaction.

#### Story 1.2: Multi-tenant Auth & API Token Management
**As a** System Operator,
**I want** to generate secure API tokens for different tenants,
**so that** metric ingestion is isolated and authenticated.

*   **Acceptance Criteria:**
    1.  Database schema includes a `tenants` table and a `tokens` table (linked to tenants).
    2.  An Artisan command or internal API exists to create a new Tenant and generate an associated API token.
    3.  Middleware validates the `Authorization: Bearer <token>` header on protected routes.
    4.  Requests with invalid tokens return a `401 Unauthorized` response.

#### Story 1.3: HTTP Metric Ingestion API (Single & Bulk)
**As an** Application Developer,
**I want** an API endpoint to push metrics via HTTP (JSON),
**so that** my services can report data to the micro-dashboard.

*   **Acceptance Criteria:**
    1.  Endpoint `POST /api/v1/metrics` accepts a JSON payload containing `metric_name`, `value`, `timestamp`, and optional `tags`.
    2.  Endpoint supports bulk ingestion (array of metric objects).
    3.  The API returns a `202 Accepted` status code for valid payloads.
    4.  Basic validation ensures `metric_name` and `value` are present and correctly formatted.

#### Story 1.4: Ingestion Processing & Idempotency
**As a** System Operator,
**I want** metrics to be processed asynchronously and deduplicated,
**so that** the API remains fast and data remains consistent despite network retries.

*   **Acceptance Criteria:**
    1.  Ingested metrics are dispatched to a Redis-backed Laravel Job.
    2.  Job worker persists metrics to the `metrics_raw` table in MariaDB.
    3.  The system uses a `dedupe_id` (if provided in payload) to prevent duplicate entries for the same event.
    4.  Failed jobs are automatically retried with exponential backoff.

### Epic 2: Real-time Visualization & Live Dashboard
**Goal:** Implement the reactive frontend and real-time streaming layer. By the end of this epic, users will be able to visualize ingested metrics on a live-updating dashboard using Server-Sent Events (SSE), completing the "Ingest -> Chart" smoke test.

#### Story 2.1: Base Dashboard Layout & Tenant View
**As a** System Operator,
**I want** a modern, dark-mode dashboard interface,
**so that** I can view a high-level summary of my tenant's health.

*   **Acceptance Criteria:**
    1.  Vue 3 dashboard features a sidebar for navigation and a main content area for widgets.
    2.  An "Overview" page displays a list of active agents and their last reported status.
    3.  A "Tenant Detail" page exists that accepts a tenant ID and prepares placeholders for metric charts.
    4.  UI is styled using Tailwind CSS with a consistent "Technical/NOC" aesthetic.

#### Story 2.2: SSE Streaming Infrastructure
**As a** System Operator,
**I want** metrics to be streamed to my browser as they are ingested,
**so that** I don't have to manually refresh the page or rely on heavy polling.

*   **Acceptance Criteria:**
    1.  Laravel implementation of an SSE endpoint `GET /api/v1/stream/{tenant}`.
    2.  The stream broadcasts new metric events immediately after they are persisted to the database.
    3.  Redis Pub/Sub or a similar mechanism is used to bridge the background ingestion job to the SSE controller.
    4.  Connection management ensures that streams are closed correctly when a user leaves the page.

#### Story 2.3: Reactive Metric Chart Component (Live Mode)
**As a** System Operator,
**I want** to see metrics visualized on a live-updating line chart,
**so that** I can observe trends in real-time.

*   **Acceptance Criteria:**
    1.  A reusable Vue 3 chart component (e.g., using Chart.js or ApexCharts) is implemented.
    2.  The component listens to the SSE stream and updates its data points without a full re-render.
    3.  The chart supports a "Live" mode indicator (e.g., a pulsing red dot).
    4.  Performance is optimized to handle high-frequency updates (e.g., 1 update/sec) without lag.

#### Story 2.4: E2E Smoke Test (Ingest -> Chart)
**As a** QA Engineer,
**I want** an automated test that simulates the full data flow,
**so that** I can verify the core PoC value proposition.

*   **Acceptance Criteria:**
    1.  A Playwright BDD scenario is created: "GIVEN a tenant exists, WHEN I POST a metric, THEN it appears on the Live Dashboard chart within 500ms."
    2.  The test runs in the containerized environment.
    3.  The test handles the asynchronous nature of SSE and background jobs (using appropriate wait/assertions).

### Epic 3: MQTT Integration & Go System Agent
**Goal:** Expand the ingestion capabilities to support IoT/Edge use cases. This epic involves setting up an MQTT broker, creating a bridge to the Laravel core, and developing the Go-based agent for automated system monitoring.

#### Story 3.1: MQTT Broker & Bridge Infrastructure
**As a** System Operator,
**I want** an MQTT broker (Mosquitto) integrated into the environment,
**so that** I can ingest metrics from edge devices.

*   **Acceptance Criteria:**
    1.  Mosquitto container added to `docker-compose.yml`.
    2.  A "Bridge" service (either a separate Go process or a Laravel Artisan command) subscribes to `metrics/#`.
    3.  The bridge parses the topic structure `metrics/{tenant_id}/{agent_id}/{metric_name}`.
    4.  Parsed messages are forwarded to the existing ingestion pipeline (reusing the same Redis job as HTTP ingestion).

#### Story 3.2: Go System Agent - Metric Collection
**As a** System Operator,
**I want** a lightweight binary that collects system stats (CPU, Memory, Disk),
**so that** I don't have to write custom scripts for basic server monitoring.

*   **Acceptance Criteria:**
    1.  Go 1.2x project initialized in the monorepo (e.g., `/agent`).
    2.  Agent successfully collects CPU percentage, Memory usage (bytes), and Disk usage (percentage).
    3.  Collection interval is configurable via environment variables or a config file.
    4.  The agent outputs metrics to stdout for local debugging.

#### Story 3.3: Go System Agent - MQTT Publisher
**As a** System Operator,
**I want** the Go agent to push its collected metrics to the MQTT broker,
**so that** they can be visualized on the dashboard.

*   **Acceptance Criteria:**
    1.  Go agent implements an MQTT client that connects to the Mosquitto broker.
    2.  Agent publishes metrics using the required topic structure: `metrics/{tenant_id}/{agent_id}/{metric_name}`.
    3.  Agent handles connection retries gracefully.
    4.  Agent includes an `agent_id` and `timestamp` in every published payload.

#### Story 3.4: Multi-Agent Management View
**As a** System Operator,
**I want** to see all active agents for my tenant on the dashboard,
**so that** I can monitor my entire fleet from one place.

*   **Acceptance Criteria:**
    1.  Dashboard includes an "Agents" list showing `agent_id`, `status` (Online/Offline), and `last_seen`.
    2.  "Status" logic is based on the frequency of MQTT messages (e.g., Offline if no message in > 2x the collection interval).
    3.  Clicking an agent filters the main dashboard charts to show only that agent's metrics.

### Epic 4: Data Retention, Alerting & Notifications
**Goal:** Implement the "Intelligent" features of the platform. This includes automated data downsampling for historical analysis and a stateful alerting engine that notifies users when metrics breach defined thresholds.

#### Story 4.1: Automated Downsampling (1m & 5m Rollups)
**As a** System Operator,
**I want** raw metrics to be automatically summarized into 1-minute and 5-minute averages,
**so that** I can view long-term historical trends without loading millions of raw data points.

*   **Acceptance Criteria:**
    1.  Laravel Scheduler or a background worker runs every minute to calculate averages from `metrics_raw`.
    2.  Aggregated data is stored in `metrics_1m` and `metrics_5m` tables.
    3.  A retention policy is implemented (e.g., raw data deleted after 24h, 1m data after 7 days, 5m data after 30 days).
    4.  The API supports a `resolution` parameter to query these rollup tables.

#### Story 4.2: Stateful Alerting Engine (Rule Evaluator)
**As a** System Operator,
**I want** to define thresholds (e.g., CPU > 90%) that trigger alerts,
**so that** I am informed of system issues automatically.

*   **Acceptance Criteria:**
    1.  Database schema for `alert_rules` (metric_name, operator, threshold, duration, tenant_id).
    2.  An evaluator job runs periodically (e.g., every 30s) to check rules against recent metrics.
    3.  Alerts are stateful: they transition from `OK` -> `PENDING` -> `FIRING` based on the rule's duration.
    4.  An `alerts` table tracks the history of these state transitions.

#### Story 4.3: Notification Dispatch (Webhook & Email)
**As a** System Operator,
**I want** to receive notifications when an alert enters the `FIRING` state,
**so that** I can respond to issues immediately even when not looking at the dashboard.

*   **Acceptance Criteria:**
    1.  The system implements a notification dispatcher.
    2.  Webhook channel: Sends a POST request with alert details to a user-defined URL.
    3.  Email channel: Sends a basic alert email (can be simulated with Mailpit/Log for PoC).
    4.  Notifications are only sent on state transitions (e.g., only when an alert *starts* firing, not every time it is evaluated).

#### Story 4.4: Historical Trend Charting
**As a** System Operator,
**I want** to toggle my dashboard charts to show 1-hour, 24-hour, and 7-day views,
**so that** I can perform root cause analysis using historical data.

*   **Acceptance Criteria:**
    1.  Chart components are updated to support a "Time Range" selector.
    2.  Selecting a range > 1 hour automatically switches the data source from the SSE stream/raw table to the 1m or 5m rollup tables.
    3.  The UI provides a seamless transition between "Live" and "Historical" modes.
    4.  Alert markers (e.g., red vertical lines) are overlaid on the chart where state transitions occurred.

---

## 7. Checklist Results Report
### PRD & Epic Validation Summary

#### Executive Summary
The PRD is **95% complete** and ready for architectural design. The MVP scope is **Just Right**, focusing strictly on the core value proposition: ingestion, live visualization, and basic alerting. The primary risk identified is the stability of SSE across different network configurations, but fallback mechanisms are planned.

#### Category Analysis Table

| Category                         | Status  | Critical Issues |
| -------------------------------- | ------- | --------------- |
| 1. Problem Definition & Context  | PASS    | None            |
| 2. MVP Scope Definition          | PASS    | None            |
| 3. User Experience Requirements  | PASS    | None            |
| 4. Functional Requirements       | PASS    | None            |
| 5. Non-Functional Requirements   | PASS    | None            |
| 6. Epic & Story Structure        | PASS    | None            |
| 7. Technical Guidance            | PASS    | None            |
| 8. Cross-Functional Requirements | PASS    | None            |
| 9. Clarity & Communication       | PASS    | None            |

#### Top Issues by Priority
*   **HIGH:** Validation of SSE performance under load (100+ metrics/sec) needs early prototyping in Epic 2.
*   **MEDIUM:** "Test-first" approach requires strict discipline; initial setup of Playwright in Epic 1 is critical.

#### MVP Scope Assessment
*   **Features Cut:** Advanced anomaly detection, native mobile apps, and third-party OIDC auth were correctly identified as out-of-scope.
*   **Timeline Realism:** The 4-epic structure allows for a playable "walking skeleton" by the end of Epic 1, reducing project risk.

#### Technical Readiness
*   **Constraints:** Clear (Laravel + Vue + Go Agent, Monorepo).
*   **Risks:** MQTT bridge overload is a known risk; the decoupled Redis architecture mitigates this.

#### Recommendations
1.  **Action:** Ensure the `docker-compose.yml` in Epic 1 includes a "mock" high-volume publisher to stress-test the ingestion pipeline early.
2.  **Action:** Define strict coding standards for the Go agent to ensuring binary size remains small (Target < 10MB).

### Final Decision
**READY FOR ARCHITECT**

---

## 8. Next Steps

### UX Expert Prompt
"Please review the 'User Interface Design Goals' in `docs/prd.md`. We need a high-fidelity mock-up for the **Main Multi-Tenant Dashboard** and the **Tenant Detail View**. Focus on the 'Dark Mode' aesthetic for NOC environments. Key elements to detail: the 'Live' indicator, the specific layout of the metric widgets (CPU/Mem/Disk), and the navigation sidebar. Output a `docs/ux-spec.md` with component breakdowns and visual hierarchy guidelines."

### Architect Prompt
"Please analyze `docs/prd.md` to design the system architecture. We have chosen a **Monorepo** structure with a **Hybrid Monolith (Laravel) + Edge (Go)** approach. Your task is to produce `docs/architecture.md`. Critical areas to detail:
1.  **Ingestion Pipeline:** precise data flow from `POST /metrics` -> Redis -> Job -> MariaDB.
2.  **SSE Streaming:** architecture for the `Redis Pub/Sub -> SSE Controller -> Client` bridge.
3.  **Database Schema:** Draft schema for `tenants`, `tokens`, `metrics_raw`, `metrics_1m`, and `alert_rules`.
4.  **Go Agent:** Module structure for the agent's collector and MQTT publisher."
