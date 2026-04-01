<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  sessions: { type: Array, required: true },
  counts:   { type: Object, required: true },
})

const page  = usePage()
const flash = computed(() => page.props.flash)

const tab = ref('all')

const TABS = [
  { key: 'all',    label: 'All' },
  { key: 'admin',  label: 'Admins' },
  { key: 'staff',  label: 'Staff' },
  { key: 'client', label: 'Clients' },
]

const filtered = computed(() => {
  if (tab.value === 'all')    return props.sessions
  if (tab.value === 'admin')  return props.sessions.filter(s => ['super-admin', 'admin'].includes(s.role))
  if (tab.value === 'staff')  return props.sessions.filter(s => s.role === 'staff')
  if (tab.value === 'client') return props.sessions.filter(s => s.role === 'client')
  return props.sessions
})

const ROLE_BADGE = {
  'super-admin': 'bg-purple-100 text-purple-700',
  'admin':       'bg-indigo-100 text-indigo-700',
  'staff':       'bg-blue-100 text-blue-700',
  'client':      'bg-gray-100 text-gray-600',
}

function revokeSession(sessionId) {
  if (!confirm('Revoke this session? The user will be logged out.')) return
  router.delete(route('admin.active-sessions.destroy', sessionId), { preserveScroll: true })
}

function revokeUser(userId, name) {
  if (!confirm(`Revoke all sessions for ${name}? They will be logged out everywhere.`)) return
  router.delete(route('admin.active-sessions.destroy-user', userId), { preserveScroll: true })
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-bold text-gray-900">Active Sessions</h1>
        <p class="text-sm text-gray-500 mt-0.5">All users currently logged in across clients, staff, and admins.</p>
      </div>
      <button @click="router.reload()" class="text-sm text-indigo-600 border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-50 transition-colors">
        Refresh
      </button>
    </div>

    <!-- Flash -->
    <div v-if="flash?.success" class="mb-5 rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
      {{ flash.success }}
    </div>

    <!-- Summary tiles -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-gray-900">{{ counts.total }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Total online</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-purple-600">{{ counts.admin }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Admins</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-blue-600">{{ counts.staff }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Staff</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-gray-700">{{ counts.client }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Clients</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-4 bg-gray-100 rounded-lg p-1 w-fit">
      <button v-for="t in TABS" :key="t.key" @click="tab = t.key"
        :class="tab === t.key
          ? 'bg-white shadow-sm text-gray-900 font-medium'
          : 'text-gray-500 hover:text-gray-700'"
        class="px-4 py-1.5 rounded-md text-sm transition-all">
        {{ t.label }}
        <span class="ml-1 text-xs opacity-60">
          {{ t.key === 'all' ? counts.total
           : t.key === 'admin' ? counts.admin
           : t.key === 'staff' ? counts.staff
           : counts.client }}
        </span>
      </button>
    </div>

    <!-- Sessions table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">User</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Role</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Device / Browser</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">IP Address</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Last Active</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="s in filtered" :key="s.session_id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <!-- Device icon -->
                <div class="h-7 w-7 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                  <svg v-if="!['iPhone','iPad','Android'].includes(s.device)" class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3" />
                  </svg>
                  <svg v-else class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18h3" />
                  </svg>
                </div>
                <div>
                  <p class="font-medium text-gray-900">{{ s.name }}</p>
                  <p class="text-xs text-gray-400">{{ s.email }}</p>
                </div>
              </div>
            </td>
            <td class="px-4 py-3">
              <span :class="[ROLE_BADGE[s.role] ?? 'bg-gray-100 text-gray-600', 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize']">
                {{ s.role }}
              </span>
              <span v-if="s.is_current" class="ml-1 inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                You
              </span>
            </td>
            <td class="px-4 py-3 text-gray-600">
              {{ s.browser }} on {{ s.device }}
            </td>
            <td class="px-4 py-3 font-mono text-xs text-gray-500">
              {{ s.ip_address ?? '—' }}
            </td>
            <td class="px-4 py-3 text-gray-500">
              {{ s.last_active }}
            </td>
            <td class="px-4 py-3 text-right">
              <template v-if="!s.is_current">
                <button @click="revokeSession(s.session_id)"
                  class="text-xs text-red-500 hover:text-red-700 font-medium mr-3 transition-colors">
                  Revoke
                </button>
                <button @click="revokeUser(s.user_id, s.name)"
                  class="text-xs text-gray-400 hover:text-red-600 transition-colors" title="Revoke all sessions for this user">
                  Revoke all
                </button>
              </template>
            </td>
          </tr>
          <tr v-if="!filtered.length">
            <td colspan="6" class="px-4 py-10 text-center text-gray-400">
              No {{ tab === 'all' ? '' : tab }} sessions active.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
