# 5. API Specification (REST Ingestion)

```yaml
openapi: 3.0.0
info:
  title: Observability Ingestion API
  version: 1.0.0
paths:
  /api/v1/metrics:
    post:
      summary: Ingest single or bulk metrics
      security:
        - BearerAuth: []
      requestBody:
        content:
          application/json:
            schema:
              type: array
              items:
                $ref: '#/components/schemas/Metric'
      responses:
        '202':
          description: Accepted for processing
components:
  schemas:
    Metric:
      type: object
      required: [metric_name, value, timestamp]
      properties:
        metric_name: { type: string }
        value: { type: number }
        timestamp: { type: string, format: date-time }
        agent_id: { type: string }
        dedupe_id: { type: string }
```

---
