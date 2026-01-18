package main

import (
	"encoding/json"
	"fmt"
	"log"
	"os"
	"strconv"
	"time"

	"observability-agent/internal/collector"
	"observability-agent/internal/models"
	"observability-agent/internal/publisher"
)

// Config holds the agent configuration
type Config struct {
	CollectionInterval time.Duration
	AgentID            string
	TenantID           string
	MQTTBroker         string
	MQTTPort           int
	MQTTUser           string
	MQTTPass           string
}

// LoadConfig loads configuration from environment variables
func LoadConfig() *Config {
	// Default collection interval: 5 seconds
	interval := 5 * time.Second

	// Read COLLECTION_INTERVAL from ENV (in seconds)
	if intervalStr := os.Getenv("COLLECTION_INTERVAL"); intervalStr != "" {
		if seconds, err := strconv.Atoi(intervalStr); err == nil && seconds > 0 {
			interval = time.Duration(seconds) * time.Second
		}
	}

	// Read AGENT_ID and TENANT_ID from ENV
	agentID := os.Getenv("AGENT_ID")
	if agentID == "" {
		agentID = "agent-default"
	}

	tenantID := os.Getenv("TENANT_ID")
	if tenantID == "" {
		tenantID = "tenant-default"
	}

	// Read MQTT configuration from ENV
	mqttBroker := os.Getenv("MQTT_BROKER")
	if mqttBroker == "" {
		mqttBroker = "localhost"
	}

	mqttPort := 1883
	if portStr := os.Getenv("MQTT_PORT"); portStr != "" {
		if port, err := strconv.Atoi(portStr); err == nil && port > 0 {
			mqttPort = port
		}
	}

	mqttUser := os.Getenv("MQTT_USER")
	if mqttUser == "" {
		mqttUser = "bridge_user"
	}

	mqttPass := os.Getenv("MQTT_PASS")
	if mqttPass == "" {
		mqttPass = "bridge_pass"
	}

	return &Config{
		CollectionInterval: interval,
		AgentID:            agentID,
		TenantID:           tenantID,
		MQTTBroker:         mqttBroker,
		MQTTPort:           mqttPort,
		MQTTUser:           mqttUser,
		MQTTPass:           mqttPass,
	}
}

func main() {
	log.Println("Starting Observability Agent...")

	// Load configuration
	config := LoadConfig()
	log.Printf("Configuration: TenantID=%s, AgentID=%s, CollectionInterval=%v",
		config.TenantID, config.AgentID, config.CollectionInterval)

	// Initialize MQTT publisher
	pub, err := publisher.New(&publisher.Config{
		Broker:   config.MQTTBroker,
		Port:     config.MQTTPort,
		Username: config.MQTTUser,
		Password: config.MQTTPass,
		ClientID: fmt.Sprintf("agent-%s", config.AgentID),
	})
	if err != nil {
		log.Fatalf("Failed to initialize MQTT publisher: %v", err)
	}
	defer pub.Close()

	// Warm-up CPU collector to initialize baseline measurement
	// This prevents the first metric broadcast from showing 0% CPU
	log.Println("Warming up CPU collector...")
	collector.CollectCPU()
	time.Sleep(100 * time.Millisecond)

	// Create a ticker for periodic collection
	ticker := time.NewTicker(config.CollectionInterval)
	defer ticker.Stop()

	// Run collection immediately on startup
	collectAndPublish(config, pub)

	// Main collection loop
	for range ticker.C {
		collectAndPublish(config, pub)
	}
}

// collectAndPublish collects metrics from all collectors and publishes them to MQTT
func collectAndPublish(config *Config, pub *publisher.Publisher) {
	var metrics []*models.Metric

	// Collect CPU
	if cpuValue, err := collector.CollectCPU(); err != nil {
		log.Printf("Error collecting CPU: %v", err)
	} else {
		metrics = append(metrics, models.NewMetric(
			config.TenantID,
			config.AgentID,
			"cpu.usage.percent",
			cpuValue,
		))
	}

	// Collect Memory
	if memValue, err := collector.CollectMemory(); err != nil {
		log.Printf("Error collecting Memory: %v", err)
	} else {
		metrics = append(metrics, models.NewMetric(
			config.TenantID,
			config.AgentID,
			"memory.used.bytes",
			memValue,
		))
	}

	// Collect Disk
	if diskValue, err := collector.CollectDisk(); err != nil {
		log.Printf("Error collecting Disk: %v", err)
	} else {
		metrics = append(metrics, models.NewMetric(
			config.TenantID,
			config.AgentID,
			"disk.usage.percent",
			diskValue,
		))
	}

	// Publish each metric to MQTT and print to stdout for debugging
	for _, metric := range metrics {
		// Publish to MQTT
		if err := pub.Publish(metric); err != nil {
			log.Printf("Error publishing metric %s: %v", metric.MetricName, err)
		}

		// Also print to stdout for debugging
		if jsonData, err := json.Marshal(metric); err != nil {
			log.Printf("Error marshaling metric: %v", err)
		} else {
			fmt.Println(string(jsonData))
		}
	}
}
