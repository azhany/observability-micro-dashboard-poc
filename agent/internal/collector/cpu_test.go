package collector

import (
	"testing"
)

func TestCollectCPU(t *testing.T) {
	value, err := CollectCPU()

	if err != nil {
		t.Fatalf("CollectCPU returned error: %v", err)
	}

	// CPU percentage should be between 0 and 100
	if value < 0 || value > 100 {
		t.Errorf("CPU percentage out of valid range: got %f, want 0-100", value)
	}

	t.Logf("CPU usage: %.2f%%", value)
}
