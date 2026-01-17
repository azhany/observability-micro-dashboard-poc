# 4. Technical Assumptions

## Repository Structure
**Structure:** Monorepo
To simplify development of the PoC and ensure synchronized testing of the Laravel core, Vue frontend, and Go System Agent, a **Monorepo** structure is assumed. This allows shared Docker configurations and easier end-to-end testing with Playwright.

## Service Architecture
A **Hybrid Monolith/Edge** architecture will be used:
*   **Core API (Laravel):** Handles authentication, stateful alerting logic, metadata, and long-term storage (MariaDB).
*   **Edge Agent (Go):** A standalone lightweight binary for system metric collection.
*   **Ingestion Pipeline:** Uses Redis and Laravel Jobs for asynchronous processing to ensure high availability during traffic spikes.
*   **Real-time Layer:** Mosquitto for MQTT ingestion and Redis/SSE for dashboard streaming.

## Testing Requirements
A **Full Testing Pyramid** approach is critical for this PoC:
*   **Unit/Integration:** PHPUnit for Laravel backend logic and Vitest for Vue components.
*   **E2E/BDD:** Playwright will be used for critical path validation (e.g., "Agent sends MQTT message -> Dashboard shows live update").
*   **Performance:** Baseline benchmarks for ingestion throughput (100+ metrics/sec) must be repeatable.

## Additional Technical Assumptions and Requests
*   **Inertia.js:** Will be used as the bridge between Laravel and Vue 3 to maintain a monolith-like developer experience with a modern reactive frontend.
*   **Time-Series Lite:** MariaDB is assumed to be sufficient for the PoC scale, utilizing standard relational indexing for the 1m/5m rollups.
*   **MQTT Topic Structure:** Topics will strictly follow `metrics/{tenant}/{agent_id}/{metric_name}`.
*   **SSE Stability:** We assume Nginx/Web-server configuration will support persistent connections required for SSE.
*   **MCP Compatibility:** All environment variables and configurations must support `USE_MCP=true` for local development.

---
