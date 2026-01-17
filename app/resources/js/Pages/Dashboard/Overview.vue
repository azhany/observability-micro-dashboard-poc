<template>
  <DashboardLayout>
    <div class="p-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">Dashboard Overview</h1>
        <p class="text-gray-400">Monitor your agents and system health</p>
      </div>

      <!-- Tenant Info -->
      <div v-if="tenant" class="bg-noc-card rounded-lg p-6 mb-8 border border-gray-800">
        <h2 class="text-xl font-semibold text-white mb-2">Current Tenant</h2>
        <p class="text-gray-300 font-mono">{{ tenant.name }}</p>
        <p class="text-sm text-gray-500 mt-1">ID: {{ tenant.id }}</p>
      </div>

      <!-- Agents Grid -->
      <div>
        <h2 class="text-2xl font-bold text-white mb-4">Active Agents</h2>

        <div v-if="agents.length === 0" class="bg-noc-card rounded-lg p-12 border border-gray-800 text-center">
          <Server :size="48" class="mx-auto text-gray-600 mb-4" />
          <p class="text-gray-400 text-lg">No agents found</p>
          <p class="text-gray-500 text-sm mt-2">Start sending metrics to see agents appear here</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <Link
            v-for="agent in agents"
            :key="agent.id"
            :href="`/dashboard/tenants/${tenant.id}?agent_id=${agent.id}`"
            class="bg-noc-card rounded-lg p-6 border border-gray-800 hover:border-noc-primary transition-all cursor-pointer group"
          >
            <div class="flex items-start justify-between mb-4">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-noc-primary/10 rounded-lg flex items-center justify-center">
                  <Server :size="24" class="text-noc-primary" />
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-white group-hover:text-noc-primary transition-colors">
                    Agent {{ agent.id }}
                  </h3>
                  <p class="text-sm text-gray-500 font-mono">{{ agent.id }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2 px-3 py-1 bg-green-500/10 border border-green-500 rounded-full">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                <span class="text-xs text-green-400 font-medium">{{ agent.status }}</span>
              </div>
            </div>

            <div class="flex items-center text-sm text-gray-400 group-hover:text-noc-primary transition-colors">
              <span>View Details</span>
              <ChevronRight :size="16" class="ml-1" />
            </div>
          </Link>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Server, ChevronRight } from 'lucide-vue-next'

interface Agent {
  id: string
  status: string
}

interface Tenant {
  id: string
  name: string
}

defineProps<{
  agents: Agent[]
  tenant: Tenant | null
}>()
</script>
