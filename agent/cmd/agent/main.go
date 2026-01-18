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
)

// Config holds the agent configuration
type Config struct {
	CollectionInterval time.Duration
	AgentID            string
	TenantID           string
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

	return &Config{
		CollectionInterval: interval,
		AgentID:            agentID,
		TenantID:           tenantID,
	}
}

func main() {
	log.Println("Starting Observability Agent...")

	// Load configuration
	config := LoadConfig()
	log.Printf("Configuration: TenantID=%s, AgentID=%s, CollectionInterval=%v",
		config.TenantID, config.AgentID, config.CollectionInterval)

	// Warm-up CPU collector to initialize baseline measurement
	// This prevents the first metric broadcast from showing 0% CPU
	log.Println("Warming up CPU collector...")
	collector.CollectCPU()
	time.Sleep(100 * time.Millisecond)

	// Create a ticker for periodic collection
	ticker := time.NewTicker(config.CollectionInterval)
	defer ticker.Stop()

	// Run collection immediately on startup
	collectAndPrint(config)

	// Main collection loop
	for range ticker.C {
		collectAndPrint(config)
	}
}

// collectAndPrint collects metrics from all collectors and prints them to stdout
func collectAndPrint(config *Config) {
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

	// Print each metric as JSON to stdout
	for _, metric := range metrics {
		if jsonData, err := json.Marshal(metric); err != nil {
			log.Printf("Error marshaling metric: %v", err)
		} else {
			fmt.Println(string(jsonData))
		}
	}
}
