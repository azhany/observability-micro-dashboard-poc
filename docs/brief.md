# Project Brief: Observability Micro-Dashboard PoC

## 1. Executive Summary
The **Observability Micro-Dashboard PoC** is a lightweight, multi-tenant monitoring platform designed to ingest, process, and visualize metrics from diverse sources (HTTP and MQTT). It provides a full-stack solution—from a high-performance Go-based system agent to a reactive Vue 3 dashboard—enabling real-time monitoring and stateful alerting for modern microservice and IoT environments.

## 2. Problem Statement
Existing observability solutions often fall into two extremes: overly complex enterprise suites (e.g., Datadog, New Relic) that are expensive and difficult to self-host, or fragmented open-source tools that require significant "glue" code to integrate MQTT ingestion with live web dashboards.
- **Pain Points:** High latency in "live" charts, complex multi-tenant setup, and high resource overhead for small-scale deployments.
- **Impact:** Developers and operators lack a unified, low-overhead way to monitor hybrid HTTP/MQTT environments with built-in alerting.

## 3. Proposed Solution
A unified observability "micro-dashboard" that prioritizes ingestion speed and live interactivity.
- **Core Concept:** A Laravel-based core handles multi-tenant auth and storage management, while a Go-based agent provides low-level system metrics via MQTT.
- **Key Differentiators:** Native MQTT support for IoT/Edge cases, SSE-powered live streaming for sub-second dashboard updates, and a "test-first" DNA using Playwright BDD scenarios.

## 4. Target Users

### Primary User Segment: System Operators / DevOps Engineers
- **Profile:** Managing small-to-medium clusters or IoT fleets.
- **Needs:** Quick visibility into system health, simple alerting rules, and multi-tenant isolation for different clients or environments.
- **Goal:** Minimize "Mean Time To Detection" (MTTD) with live streaming metrics.

### Secondary User Segment: Application Developers
- **Profile:** Building microservices that need to push custom metrics via HTTP.
- **Needs:** Easy-to-use API for metric ingestion and pre-built dashboard widgets.

## 5. Goals & Success Metrics

### Business Objectives
- Demonstrate a functional multi-tenant ingestion pipeline.
- Validate the "Live Stream" feature as a viable alternative to heavy polling.

### User Success Metrics
- **Ingestion Latency:** Metrics should be available for query within < 500ms of ingestion (excluding rollups).
- **Dashboard Load Time:** Primary dashboard pages should load in < 1s.

### Key Performance Indicators (KPIs)
- **MTTD:** Time from metric breach to alert notification < 60s.
- **Ingestion Throughput:** Support 100+ metrics/sec on baseline PoC hardware.

## 6. MVP Scope

### Core Features (Must Have)
- **Multi-tenant Auth:** API token-based ingestion with strict tenant isolation.
- **Hybrid Ingestion:** HTTP (Single/Bulk) and MQTT Bridge subscriber.
- **Automated Downsampling:** 1m and 5m rollups for efficient long-term storage.
- **Live Dashboard:** Reactive charts with SSE (Server-Sent Events) fallback for real-time updates.
- **Stateful Alerting:** Rule evaluator with state transitions (OK -> FIRING) and notification channels (Webhook/Email).
- **Go System Agent:** Lightweight binary for CPU/Mem/Disk collection.

### Out of Scope for MVP
- Advanced anomaly detection (ML-based).
- Native Mobile Applications.
- Integration with third-party IDPs (OIDC/SAML) beyond basic Laravel auth.

### MVP Success Criteria
Successfully running the Playwright BDD "Ingest -> Chart" smoke test in a containerized environment.

## 7. Post-MVP Vision
- **Phase 2:** Advanced dashboard builder (drag-and-drop widgets), public "status page" sharing, and Prometheus/OpenTelemetry exporter support.
- **Long-term:** Evolution into a "headless" observability engine where the UI is just one of many consumers.

## 8. Technical Considerations

### Platform Requirements
- **Deployment:** Docker Compose (Single-node PoC).
- **Stack:** PHP 8.x (Laravel 11), Go 1.2x, MariaDB 10.x, Redis 7.x, Mosquitto (MQTT).

### Technology Preferences
- **Frontend:** Vue 3, Pinia, Inertia.js, Tailwind CSS, Vite.
- **Backend:** Laravel (API/Jobs/Scheduler).
- **Testing:** PHPUnit (Backend), Vitest (Frontend), Playwright (E2E/BDD).

### Architecture Considerations
- **Layered Design:** Clean separation between Domain, Use Cases, and Infrastructure.
- **Scalability:** Background job processing for all DB writes to ensure high ingestion availability (`202 Accepted`).
- **Idempotency:** Support for `dedupe_id` in ingestion payloads.

## 9. Constraints & Assumptions
- **Assumption:** MariaDB is sufficient for PoC scale; future iterations might require TimeScaleDB or ClickHouse.
- **Constraint:** Development must be compatible with MCP-based local tooling (`USE_MCP=true`).
- **Assumption:** MQTT messages will follow a specific `metrics/{tenant}/#` topic structure.

## 10. Risks & Open Questions
- **Risk:** High-frequency MQTT ingestion might overwhelm the Laravel Job queue if not batched correctly.
- **Risk:** SSE stability across different browser/proxy configurations.
- **Open Question:** Should the Go Agent support local spooling (disk buffer) in the PoC version?

## 11. Appendices

### A. References
- Original Project Overview (`overview.md`)
- Technical Stack Specification (Laravel, Inertia, Vue, Go)

## 12. Next Steps

### Immediate Actions
1. Initialize Laravel project with Inertia/Vue scaffolding.
2. Setup Docker Compose with MariaDB, Redis, and Mosquitto.
3. Implement API Token middleware and basic ingestion endpoint.

### PM Handoff
This Project Brief provides the full context for **Observability Micro-Dashboard PoC**. Please start in 'PRD Generation Mode', review the brief thoroughly to work with the user to create the PRD section by section as the template indicates, asking for any necessary clarification or suggesting improvements.
