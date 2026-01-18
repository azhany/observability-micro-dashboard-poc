package publisher

import (
	"encoding/json"
	"fmt"
	"log"
	"time"

	mqtt "github.com/eclipse/paho.mqtt.golang"
	"observability-agent/internal/models"
)

// Publisher handles MQTT publishing of metrics
type Publisher struct {
	client mqtt.Client
	config *Config
}

// Config holds MQTT publisher configuration
type Config struct {
	Broker   string
	Port     int
	Username string
	Password string
	ClientID string
}

// New creates a new MQTT publisher with connection retry logic
func New(config *Config) (*Publisher, error) {
	if config == nil {
		return nil, fmt.Errorf("config cannot be nil")
	}

	// Build broker URL
	brokerURL := fmt.Sprintf("tcp://%s:%d", config.Broker, config.Port)

	// Configure MQTT client options
	opts := mqtt.NewClientOptions()
	opts.AddBroker(brokerURL)
	opts.SetClientID(config.ClientID)
	opts.SetUsername(config.Username)
	opts.SetPassword(config.Password)
	opts.SetAutoReconnect(true)
	opts.SetConnectRetry(true)
	opts.SetConnectRetryInterval(5 * time.Second)
	opts.SetMaxReconnectInterval(60 * time.Second)

	// Connection lost handler
	opts.SetConnectionLostHandler(func(client mqtt.Client, err error) {
		log.Printf("MQTT connection lost: %v", err)
	})

	// On connect handler
	opts.SetOnConnectHandler(func(client mqtt.Client) {
		log.Println("MQTT connection established")
	})

	// Create and connect client
	client := mqtt.NewClient(opts)

	log.Printf("Connecting to MQTT broker at %s...", brokerURL)
	if token := client.Connect(); token.Wait() && token.Error() != nil {
		return nil, fmt.Errorf("failed to connect to MQTT broker: %w", token.Error())
	}

	log.Println("Successfully connected to MQTT broker")

	return &Publisher{
		client: client,
		config: config,
	}, nil
}

// Publish publishes a metric to the MQTT broker
// Topic format: metrics/{tenant_id}/{agent_id}/{metric_name}
func (p *Publisher) Publish(metric *models.Metric) error {
	if metric == nil {
		return fmt.Errorf("metric cannot be nil")
	}

	// Build topic: metrics/{tenant_id}/{agent_id}/{metric_name}
	topic := fmt.Sprintf("metrics/%s/%s/%s", metric.TenantID, metric.AgentID, metric.MetricName)

	// Marshal metric to JSON
	payload, err := json.Marshal(metric)
	if err != nil {
		return fmt.Errorf("failed to marshal metric: %w", err)
	}

	// Publish with QoS 1 (at least once delivery)
	token := p.client.Publish(topic, 1, false, payload)
	if token.Wait() && token.Error() != nil {
		return fmt.Errorf("failed to publish metric: %w", token.Error())
	}

	return nil
}

// Close disconnects from the MQTT broker
func (p *Publisher) Close() {
	if p.client != nil && p.client.IsConnected() {
		p.client.Disconnect(250)
		log.Println("Disconnected from MQTT broker")
	}
}

// IsConnected returns true if the publisher is connected to the broker
func (p *Publisher) IsConnected() bool {
	return p.client != nil && p.client.IsConnected()
}
