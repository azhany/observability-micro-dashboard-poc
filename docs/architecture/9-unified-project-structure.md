# 9. Unified Project Structure

```plaintext
observability-poc/
├── app/                        # Laravel + Vue (Inertia)
│   ├── app/                    # PHP Logic
│   ├── resources/js/           # Vue 3 Components
│   │   ├── components/ui/      # Atomic widgets (Shadcn-like)
│   │   ├── pages/              # Dashboard screens
│   │   └── services/           # SSE & API clients
│   ├── database/               # Migrations
│   └── routes/                 # Web/API/SSE definitions
├── agent/                      # Go System Agent
│   ├── cmd/agent/              # Binary entry
│   └── pkg/                    # Reusable packages
├── docker/                     # Service configs (Mosquitto, Redis)
├── docker-compose.yml
└── README.md
```

---
