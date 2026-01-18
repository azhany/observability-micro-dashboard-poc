package models

import "time"

// Metric represents a single metric data point collected by the agent
type Metric struct {
	TenantID   string    `json:"tenant_id"`
	AgentID    string    `json:"agent_id"`
	MetricName string    `json:"metric_name"`
	Value      float64   `json:"value"`
	Timestamp  time.Time `json:"timestamp"`
}

// NewMetric creates a new metric instance with the current timestamp
func NewMetric(tenantID, agentID, metricName string, value float64) *Metric {
	return &Metric{
		TenantID:   tenantID,
		AgentID:    agentID,
		MetricName: metricName,
		Value:      value,
		Timestamp:  time.Now().UTC(),
	}
}
