# 8. Go Agent Structure

**Path:** `/agent`
- `main.go`: Entry point, config loading.
- `internal/collector/`: Logic for CPU, Mem, Disk scraping (using `gopsutil`).
- `internal/publisher/`: MQTT client and retry logic.
- `internal/models/`: Shared metric structs.

---
