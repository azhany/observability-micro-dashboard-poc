# Summary

Goal: ingest metrics (HTTP + MQTT), store + downsample, visualize live charts, evaluate alert rules, notify on breaches — all multi-tenant and testable via Playwright BDD (MCP tools only local/dev). Stack: **Laravel + Inertia + Vue 3 + Pinia + Vite + Tailwind + MariaDB + Redis + MQTT + Go agent + PHPUnit + Vitest + Playwright**. Everything runnable using **docker-compose**.

---

# Epics & Stories (18 total)

## Epic 1 — Ingestion & Auth (4 stories)

1. **API token & tenant auth**
   *AC:* Admin creates tenant and API tokens (hashed). All ingestion endpoints accept `X-API-Token`; invalid token → `401`.
2. **HTTP ingestion endpoint (single + bulk)**
   *AC:* `POST /api/metrics` accepts single JSON or array `{name,value,tags,ts}`; valid → `202` with request id; invalid → `422`.
3. **MQTT bridge subscriber (ingestor service)**
   *AC:* Service subscribes to `metrics/{tenant}/+` topics, validates messages and enqueues them for DB write.
4. **Rate limit & idempotency**
   *AC:* Per-token per-minute quota enforced (`429` on exceed); messages with same `dedupe_id` only stored once.

## Epic 2 — Storage, Rollups & Query API (5 stories)

5. **Raw metrics table + migration**
   *AC:* `metrics` table exists with `(tenant_id,name,value,tags JSON,ts,ingested_at)` and index `(tenant_id,name,ts)`.
6. **Batch write pipeline (fast accept)**
   *AC:* Ingest endpoints buffer writes into job queue (Laravel Jobs) and respond quickly (`202`). Jobs flush in chunks to DB; `Bus::fake()` tests available.
7. **Downsampling (1m / 5m rollups)**
   *AC:* Scheduled job creates `metric_rollups` with `avg,min,max,count` for configured intervals; rollups used for long-range charts.
8. **Query API for charts**
   *AC:* `GET /api/metrics/query?name=&start=&end=&interval=` returns paginated/sampled datapoints (uses rollups when possible).
9. **Retention & partition job**
   *AC:* Admin can set retention (days); scheduled cleanup drops old partitions/rows accordingly.

## Epic 3 — Dashboard & Live Streaming (4 stories)

10. **Dashboard & metric selector (Inertia + Pinia)**
    *AC:* Inertia page lists dashboards; user selects metric + range; Pinia stores selection and persisted in localStorage.
11. **Chart rendering + CSV export**
    *AC:* Chart displays datapoints from query API; user can export visible range to CSV via signed job link.
12. **Live stream (SSE fallback)**
    *AC:* User can toggle live mode; SSE pushes new datapoints for visible metric and chart appends them in real time.
13. **Vitest unit tests for Chart + Pinia store**
    *AC:* Core chart component and store have unit tests covering empty/noise/no-data cases.

## Epic 4 — Alerting & Notifications (3 stories)

14. **Alert rule CRUD & simple evaluator**
    *AC:* UI to create rules like `metric X > threshold for window`; rules persisted and evaluable via engine.
15. **Evaluation scheduler & stateful alerts**
    *AC:* Worker evaluates rules at cadence, persists `alerts` with states `OK/FIRING`, and records sample values; state transitions tracked.
16. **Notification channels & flood protection**
    *AC:* On firing, notifications enqueued for email/webhook/Slack; notifications limited to X/minute per tenant (batch if needed).

## Epic 5 — Go Agent & Local Infra (2 stories)

17. **Go agent publishes system metrics via MQTT**
    *AC:* Agent binary collects CPU/mem/disk/network and publishes JSON to `metrics/{tenant}/system/{agent_id}` at configured interval; supports TLS and local spool on failure.
18. **Docker Compose + dev/prod parity**
    *AC:* `docker-compose.yml` (dev) includes `app`, `nginx`, `worker`, `scheduler`, `mariadb`, `redis`, `mosquitto`, `ingestor`, `go-agent`, `playwright` test runner. `make dev` boots environment and healthchecks pass.

---

# Testing & BDD (cross-cutting rules)

* **Playwright BDD smoke tests** (run in CI as small, deterministic scenarios):

  * Ingest → Chart: given token, when post 10 metrics, then chart shows datapoints.
  * Alert: create threshold rule, inject metrics to trigger, then webhook test endpoint receives notification.
  * Agent: start Go agent in compose, ensure system metric appears in UI.
* **MCP tooling gated**: local dev only with `USE_MCP=true`. Playwright helpers may call local CLI tools (Claude Code / Gemini CLI / Antigravity) to generate synthetic metric bursts — disabled in CI.
* **Unit & integration**: PHPUnit for domain logic & job processing (`Bus::fake()`), Vitest for Vue/Pinia, Playwright for E2E.

---

# Minimal DB model (for PoC)

* `metrics(id, tenant_id, name, value DOUBLE, tags JSON, ts DATETIME, ingested_at)`
* `metric_rollups(tenant_id,name,interval,start_ts,avg,min,max,count)`
* `alert_rules(id,tenant_id,name,metric,condition_json,window,cadence,state)`
* `alerts(id,rule_id,tenant_id,fired_at,resolved_at,sample_values JSON)`
* `api_tokens, users, dashboards, widgets, audit_logs` (basic schemas)

---

# Cross-epic implementation tips (short)

* **State mgmt:** Use **Pinia** (typed). Keep initial page props from Inertia controllers, interactive state lives in Pinia.
* **Inertia pattern:** Controllers = source of truth for page props. Keep heavy interactivity outside Inertia props.
* **Testing:** PHPUnit for business logic & endpoints; `Bus::fake()` for job assertions. Vitest for Vue/Pinia. Playwright for deterministic BDD flows.
* **Scaling:** Cache configs/flagging in Redis; use queues for IO; index `(tenant_id,name,ts)`; paginate all UI lists; use rollups for long ranges.
* **Dev tooling:** Vite + HMR, Tailwind JIT. Consider generating TypeScript types from Laravel Resources for tight frontend-backend sync.
* **Security:** Tenant isolation enforced at controller/middleware boundary (never rely on UI). TLS for MQTT and HTTPS in prod; tokens revocable.

---

# Clean architecture & best-practice rules (concise)

* **Layer separation:** Domain → Use Cases → Interfaces/Adapters → Framework (Laravel). Keep use-cases free of framework code.
* **Dependency direction:** Inner layers do not depend on outer details — use interfaces/repositories.
* **Idempotency:** Ingest operations must support dedupe keys.
* **Short requests:** Keep ingestion fast (`202`) and persist via background jobs.
* **Observability:** Instrument ingestion latency, job lag, alert eval latency — surface these on an internal health dashboard.
* **Small surface for PoC:** Favor correctness & determinism over feature-bloat: deterministic timestamps, seeded tests, limited retention defaults.
