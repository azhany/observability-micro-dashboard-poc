package collector

import (
	"testing"
)

func TestCollectDisk(t *testing.T) {
	value, err := CollectDisk()

	if err != nil {
		t.Fatalf("CollectDisk returned error: %v", err)
	}

	// Disk percentage should be between 0 and 100
	if value < 0 || value > 100 {
		t.Errorf("Disk percentage out of valid range: got %f, want 0-100", value)
	}

	t.Logf("Disk usage: %.2f%%", value)
}
