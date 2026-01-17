<template>
  <div class="flex h-screen bg-noc-bg">
    <!-- Sidebar -->
    <aside class="w-64 bg-noc-card border-r border-gray-800 flex flex-col">
      <!-- Logo/Header -->
      <div class="p-6 border-b border-gray-800">
        <h1 class="text-2xl font-bold text-noc-primary">
          ObservabilityPOC
        </h1>
        <p class="text-xs text-gray-500 mt-1">Monitoring Dashboard</p>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 p-4 space-y-2">
        <Link
          href="/dashboard"
          class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors"
          :class="isActive('/dashboard') ? 'bg-noc-primary/10 text-noc-primary' : 'text-gray-400 hover:bg-gray-800 hover:text-white'"
        >
          <Home :size="20" />
          <span class="font-medium">Home</span>
        </Link>

        <Link
          href="/alerts"
          class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors"
          :class="isActive('/alerts') ? 'bg-noc-primary/10 text-noc-primary' : 'text-gray-400 hover:bg-gray-800 hover:text-white'"
        >
          <AlertCircle :size="20" />
          <span class="font-medium">Alerts</span>
        </Link>

        <Link
          href="/settings"
          class="flex items-center gap-3 px-4 py-3 rounded-lg transition-colors"
          :class="isActive('/settings') ? 'bg-noc-primary/10 text-noc-primary' : 'text-gray-400 hover:bg-gray-800 hover:text-white'"
        >
          <Settings :size="20" />
          <span class="font-medium">Settings</span>
        </Link>
      </nav>

      <!-- Footer -->
      <div class="p-4 border-t border-gray-800 text-xs text-gray-500">
        <p>&copy; 2026 Observability PoC</p>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 overflow-auto">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Home, AlertCircle, Settings } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{
  currentPath?: string
}>()

const isActive = (path: string) => {
  // Simple check - could be enhanced with route matching
  if (typeof window !== 'undefined') {
    return window.location.pathname === path || window.location.pathname.startsWith(path)
  }
  return props.currentPath === path || props.currentPath?.startsWith(path)
}
</script>
