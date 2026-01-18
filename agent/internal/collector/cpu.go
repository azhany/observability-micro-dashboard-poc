package collector

import (
	"github.com/shirou/gopsutil/v4/cpu"
)

// CollectCPU collects CPU usage percentage using non-blocking method
// Returns the CPU usage as a percentage (0-100) and any error encountered
// Note: The first call will return 0 as there is no previous measurement to compare against
func CollectCPU() (float64, error) {
	// Get CPU percentage since last call (non-blocking)
	// Passing 0 as interval makes this non-blocking - it calculates percentage
	// since the last call to cpu.Percent()
	percentages, err := cpu.Percent(0, false)
	if err != nil {
		return 0, err
	}

	// Return the overall CPU percentage (first element when perCPU is false)
	if len(percentages) > 0 {
		return percentages[0], nil
	}

	return 0, nil
}
