<template>
  <DashboardLayout>
    <div class="p-8">
      <!-- Header -->
      <div class="mb-8 flex items-center justify-between">
        <div>
          <Link href="/dashboard" class="text-noc-primary hover:text-noc-primary/80 transition-colors mb-2 inline-flex items-center gap-2">
            <ChevronLeft :size="20" />
            <span>Back to Overview</span>
          </Link>
          <h1 class="text-3xl font-bold text-white mb-2">Tenant Details</h1>
          <p class="text-gray-400">{{ tenant.name }}</p>
          <p v-if="agent_id" class="text-sm text-gray-500 mt-1">Filtered by Agent: <span class="font-mono text-noc-primary">{{ agent_id }}</span></p>
        </div>

        <!-- Live/Historical Toggle -->
        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-400">View Mode:</span>
          <div class="flex bg-noc-card border border-gray-800 rounded-lg p-1">
            <button
              @click="viewMode = 'live'"
              :class="[
                'px-4 py-2 rounded-md text-sm font-medium transition-all',
                viewMode === 'live'
                  ? 'bg-noc-primary text-noc-bg'
                  : 'text-gray-400 hover:text-white'
              ]"
            >
              <div class="flex items-center gap-2">
                <div v-if="viewMode === 'live'" class="w-2 h-2 bg-noc-bg rounded-full animate-pulse"></div>
                Live
              </div>
            </button>
            <button
              @click="viewMode = 'historical'"
              :class="[
                'px-4 py-2 rounded-md text-sm font-medium transition-all',
                viewMode === 'historical'
                  ? 'bg-noc-primary text-noc-bg'
                  : 'text-gray-400 hover:text-white'
              ]"
            >
              Historical
            </button>
          </div>
        </div>
      </div>

      <!-- Tenant Info Card -->
      <div class="bg-noc-card rounded-lg p-6 mb-8 border border-gray-800">
        <h2 class="text-xl font-semibold text-white mb-4">Tenant Information</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-sm text-gray-500 mb-1">Tenant ID</p>
            <p class="text-gray-300 font-mono">{{ tenant.id }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-500 mb-1">Name</p>
            <p class="text-gray-300">{{ tenant.name }}</p>
          </div>
        </div>
      </div>

      <!-- Metrics Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- CPU Chart -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <Cpu :size="20" class="text-noc-primary" />
            <h3 class="text-lg font-semibold text-white">CPU Usage</h3>
          </div>
          <RealtimeChart
            :tenantId="tenant.id"
            metricName="cpu.usage.percent"
            label="CPU %"
            color="rgb(59, 130, 246)"
            :agentId="agent_id || undefined"
          />
        </div>

        <!-- Memory Chart -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <MemoryStick :size="20" class="text-noc-primary" />
            <h3 class="text-lg font-semibold text-white">Memory Usage</h3>
          </div>
          <RealtimeChart
            :tenantId="tenant.id"
            metricName="memory.used.bytes"
            label="Memory (bytes)"
            color="rgb(34, 197, 94)"
            :agentId="agent_id || undefined"
          />
        </div>

        <!-- Disk Chart -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <HardDrive :size="20" class="text-noc-primary" />
            <h3 class="text-lg font-semibold text-white">Disk Usage</h3>
          </div>
          <RealtimeChart
            :tenantId="tenant.id"
            metricName="disk.usage.percent"
            label="Disk %"
            color="rgb(168, 85, 247)"
            :agentId="agent_id || undefined"
          />
        </div>
      </div>

      <!-- Additional Info -->
      <div class="bg-noc-card rounded-lg p-6 border border-gray-800">
        <h3 class="text-lg font-semibold text-white mb-4">Metrics Overview</h3>
        <p class="text-gray-400 text-sm">
          Real-time metrics are displayed above for {{ agent_id ? `agent ${agent_id}` : 'all agents' }}.
          Charts automatically update when new data is received via SSE connection.
        </p>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import RealtimeChart from '@/Components/RealtimeChart.vue'
import { ChevronLeft, Cpu, MemoryStick, HardDrive } from 'lucide-vue-next'

interface Tenant {
  id: string
  name: string
}

defineProps<{
  tenant: Tenant
  agent_id?: string | null
}>()

const viewMode = ref<'live' | 'historical'>('live')
</script>
