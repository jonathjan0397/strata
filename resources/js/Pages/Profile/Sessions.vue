<script setup>
import { router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  sessions: { type: Array, required: true },
})

const page    = usePage()
const flash   = computed(() => page.props.flash)

function deviceIcon(device) {
  if (['iPhone', 'iPad', 'Android'].includes(device)) return 'mobile'
  return 'desktop'
}

function revoke(sessionId) {
  router.delete(route('profile.sessions.destroy', sessionId), { preserveScroll: true })
}

function revokeAll() {
  router.delete(route('profile.sessions.destroy-others'), { preserveScroll: true })
}
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">Active sessions</h2>
        <p class="text-sm text-gray-500 mt-0.5">
          Review and revoke sessions on other devices.
        </p>
      </div>
      <button
        v-if="sessions.filter(s => !s.is_current).length > 0"
        class="text-sm text-red-600 hover:text-red-700 font-medium"
        @click="revokeAll"
      >
        Revoke all others
      </button>
    </div>

    <!-- Flash -->
    <div v-if="flash?.success" class="mb-5 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
      {{ flash.success }}
    </div>

    <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white overflow-hidden">
      <li
        v-for="session in sessions"
        :key="session.id"
        class="flex items-center gap-4 px-5 py-4"
      >
        <!-- Device icon -->
        <div class="shrink-0 flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500">
          <!-- Desktop -->
          <svg v-if="deviceIcon(session.device) === 'desktop'" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3" />
          </svg>
          <!-- Mobile -->
          <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18h3" />
          </svg>
        </div>

        <!-- Details -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-gray-900">{{ session.browser }} on {{ session.device }}</span>
            <span
              v-if="session.is_current"
              class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700"
            >
              This device
            </span>
          </div>
          <div class="flex items-center gap-3 mt-0.5 text-xs text-gray-400">
            <span>{{ session.ip_address ?? 'Unknown IP' }}</span>
            <span>·</span>
            <span>{{ session.last_active }}</span>
          </div>
        </div>

        <!-- Revoke -->
        <button
          v-if="!session.is_current"
          class="shrink-0 text-xs text-red-500 hover:text-red-700 font-medium transition-colors"
          @click="revoke(session.id)"
        >
          Revoke
        </button>
      </li>

      <li v-if="sessions.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
        No active sessions found.
      </li>
    </ul>
  </div>
</template>
