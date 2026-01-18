package collector

import (
	"github.com/shirou/gopsutil/v4/disk"
)

// CollectDisk collects disk usage percentage for the root partition
// Returns the disk usage as a percentage (0-100) and any error encountered
func CollectDisk() (float64, error) {
	// Get disk usage for the root partition
	diskStat, err := disk.Usage("/")
	if err != nil {
		return 0, err
	}

	// Return disk usage percentage
	return diskStat.UsedPercent, nil
}
