package collector

import (
	"testing"
)

func TestCollectMemory(t *testing.T) {
	value, err := CollectMemory()

	if err != nil {
		t.Fatalf("CollectMemory returned error: %v", err)
	}

	// Memory usage should be a positive value (in bytes)
	if value <= 0 {
		t.Errorf("Memory usage should be positive: got %f", value)
	}

	t.Logf("Memory used: %.2f bytes (%.2f MB)", value, value/1024/1024)
}
