package collector

import (
	"time"

	"github.com/shirou/gopsutil/v4/cpu"
)

// CollectCPU collects CPU usage percentage
// Returns the CPU usage as a percentage (0-100) and any error encountered
func CollectCPU() (float64, error) {
	// Get CPU percentage over a 1 second interval
	// The first parameter is the interval, second is whether to get per-CPU stats
	percentages, err := cpu.Percent(time.Second, false)
	if err != nil {
		return 0, err
	}

	// Return the overall CPU percentage (first element when perCPU is false)
	if len(percentages) > 0 {
		return percentages[0], nil
	}

	return 0, nil
}
