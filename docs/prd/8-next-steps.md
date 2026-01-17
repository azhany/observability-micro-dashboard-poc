# 8. Next Steps

## UX Expert Prompt
"Please review the 'User Interface Design Goals' in `docs/prd.md`. We need a high-fidelity mock-up for the **Main Multi-Tenant Dashboard** and the **Tenant Detail View**. Focus on the 'Dark Mode' aesthetic for NOC environments. Key elements to detail: the 'Live' indicator, the specific layout of the metric widgets (CPU/Mem/Disk), and the navigation sidebar. Output a `docs/ux-spec.md` with component breakdowns and visual hierarchy guidelines."

## Architect Prompt
"Please analyze `docs/prd.md` to design the system architecture. We have chosen a **Monorepo** structure with a **Hybrid Monolith (Laravel) + Edge (Go)** approach. Your task is to produce `docs/architecture.md`. Critical areas to detail:
1.  **Ingestion Pipeline:** precise data flow from `POST /metrics` -> Redis -> Job -> MariaDB.
2.  **SSE Streaming:** architecture for the `Redis Pub/Sub -> SSE Controller -> Client` bridge.
3.  **Database Schema:** Draft schema for `tenants`, `tokens`, `metrics_raw`, `metrics_1m`, and `alert_rules`.
4.  **Go Agent:** Module structure for the agent's collector and MQTT publisher."
