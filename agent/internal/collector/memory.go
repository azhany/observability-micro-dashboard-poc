package collector

import (
	"github.com/shirou/gopsutil/v4/mem"
)

// CollectMemory collects memory usage in bytes
// Returns the used memory in bytes and any error encountered
func CollectMemory() (float64, error) {
	vmStat, err := mem.VirtualMemory()
	if err != nil {
		return 0, err
	}

	// Return used memory in bytes
	return float64(vmStat.Used), nil
}
