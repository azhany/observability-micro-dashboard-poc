# 10. Security and Performance

## Performance Targets
- **Ingestion:** < 50ms for API response, < 450ms for DB persistence.
- **SSE Broadcast:** < 100ms from DB write to browser receipt.
- **Dashboard FPS:** 60 FPS during live updates.

## Security
- **Multi-tenancy:** Scoped queries in Laravel (`TenantScope`).
- **Auth:** Token-based authentication for ingestion; Session-based for Dashboard.
- **MQTT:** Basic Auth or TLS (optional for PoC).

---
