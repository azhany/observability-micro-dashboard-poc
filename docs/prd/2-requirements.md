# 2. Requirements

## Functional Requirements (FR)

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

## Non-Functional Requirements (NFR)

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
