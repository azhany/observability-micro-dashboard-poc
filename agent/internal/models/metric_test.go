package models

import (
	"encoding/json"
	"testing"
	"time"
)

func TestMetricJSONSchema(t *testing.T) {
	// Create a test metric
	metric := NewMetric("test-tenant", "test-agent", "cpu.usage.percent", 42.5)

	// Marshal to JSON
	jsonData, err := json.Marshal(metric)
	if err != nil {
		t.Fatalf("Failed to marshal metric to JSON: %v", err)
	}

	// Unmarshal back to verify structure
	var result map[string]interface{}
	if err := json.Unmarshal(jsonData, &result); err != nil {
		t.Fatalf("Failed to unmarshal JSON: %v", err)
	}

	// Verify required fields exist
	requiredFields := []string{"metric_name", "value", "timestamp", "agent_id", "tenant_id"}
	for _, field := range requiredFields {
		if _, exists := result[field]; !exists {
			t.Errorf("Missing required field: %s", field)
		}
	}

	// Verify field types match backend expectations
	if metricName, ok := result["metric_name"].(string); !ok {
		t.Error("metric_name should be a string")
	} else if len(metricName) > 64 {
		t.Errorf("metric_name exceeds 64 character limit: %d chars", len(metricName))
	}

	if _, ok := result["value"].(float64); !ok {
		t.Error("value should be numeric (float64)")
	}

	if timestamp, ok := result["timestamp"].(string); !ok {
		t.Error("timestamp should be a string (ISO 8601)")
	} else {
		// Verify timestamp can be parsed as RFC3339
		if _, err := time.Parse(time.RFC3339, timestamp); err != nil {
			t.Errorf("timestamp is not valid RFC3339 format: %v", err)
		}
	}

	if _, ok := result["agent_id"].(string); !ok {
		t.Error("agent_id should be a string")
	}

	if _, ok := result["tenant_id"].(string); !ok {
		t.Error("tenant_id should be a string")
	}

	t.Logf("Generated JSON: %s", string(jsonData))
}

func TestMetricNameLength(t *testing.T) {
	// Verify all standard metric names are within 64 character limit
	metricNames := []string{
		"cpu.usage.percent",
		"memory.used.bytes",
		"disk.usage.percent",
	}

	for _, name := range metricNames {
		if len(name) > 64 {
			t.Errorf("Metric name exceeds 64 character limit: %s (%d chars)", name, len(name))
		} else {
			t.Logf("✓ %s: %d chars", name, len(name))
		}
	}
}
