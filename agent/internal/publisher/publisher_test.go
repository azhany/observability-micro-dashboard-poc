package publisher

import (
	"testing"
	"time"

	mqtt "github.com/eclipse/paho.mqtt.golang"
	"observability-agent/internal/models"
)

func TestNew_Success(t *testing.T) {
	// Skip this test if MQTT broker is not available
	// This is an integration test that requires a running broker
	t.Skip("Integration test - requires running MQTT broker")

	config := &Config{
		Broker:   "localhost",
		Port:     1883,
		Username: "bridge_user",
		Password: "bridge_pass",
		ClientID: "test-client",
	}

	pub, err := New(config)
	if err != nil {
		t.Fatalf("Failed to create publisher: %v", err)
	}
	defer pub.Close()

	if !pub.IsConnected() {
		t.Error("Publisher should be connected")
	}
}

func TestNew_NilConfig(t *testing.T) {
	_, err := New(nil)
	if err == nil {
		t.Error("Expected error for nil config, got nil")
	}
}

func TestPublish_NilMetric(t *testing.T) {
	// Create a minimal publisher for testing (won't connect)
	config := &Config{
		Broker:   "localhost",
		Port:     1883,
		Username: "test",
		Password: "test",
		ClientID: "test-nil-metric",
	}

	// We'll skip connection for this unit test
	pub := &Publisher{
		client: nil,
		config: config,
	}

	err := pub.Publish(nil)
	if err == nil {
		t.Error("Expected error for nil metric, got nil")
	}
	if err.Error() != "metric cannot be nil" {
		t.Errorf("Expected 'metric cannot be nil' error, got: %v", err)
	}
}

func TestPublish_Integration(t *testing.T) {
	// Skip this test if MQTT broker is not available
	// This is an integration test that requires a running broker
	t.Skip("Integration test - requires running MQTT broker")

	config := &Config{
		Broker:   "localhost",
		Port:     1883,
		Username: "bridge_user",
		Password: "bridge_pass",
		ClientID: "test-publish-client",
	}

	pub, err := New(config)
	if err != nil {
		t.Fatalf("Failed to create publisher: %v", err)
	}
	defer pub.Close()

	// Create a test metric
	metric := models.NewMetric(
		"test-tenant",
		"test-agent",
		"test.metric",
		42.5,
	)

	// Publish the metric
	err = pub.Publish(metric)
	if err != nil {
		t.Fatalf("Failed to publish metric: %v", err)
	}

	// Give some time for the message to be sent
	time.Sleep(100 * time.Millisecond)
}

func TestIsConnected(t *testing.T) {
	// Test with nil client
	pub := &Publisher{
		client: nil,
	}

	if pub.IsConnected() {
		t.Error("IsConnected should return false for nil client")
	}
}

type mockClient struct {
	mqtt.Client
	publishedTopic string
	publishedPayload []byte
}

func (m *mockClient) Publish(topic string, qos byte, retained bool, payload interface{}) mqtt.Token {
	m.publishedTopic = topic
	m.publishedPayload = payload.([]byte)
	return &mockToken{}
}

type mockToken struct {
	mqtt.Token
}

func (m *mockToken) Wait() bool { return true }
func (m *mockToken) Error() error { return nil }

func TestPublish_Logic(t *testing.T) {
	mock := &mockClient{}
	pub := &Publisher{
		client: mock,
	}

	metric := &models.Metric{
		TenantID:   "t1",
		AgentID:    "a1",
		MetricName: "cpu",
		Value:      10.5,
		Timestamp:  time.Now(),
	}

	err := pub.Publish(metric)
	if err != nil {
		t.Fatalf("Publish failed: %v", err)
	}

	expectedTopic := "metrics/t1/a1/cpu"
	if mock.publishedTopic != expectedTopic {
		t.Errorf("Expected topic %s, got %s", expectedTopic, mock.publishedTopic)
	}

	if len(mock.publishedPayload) == 0 {
		t.Error("Payload should not be empty")
	}
}
