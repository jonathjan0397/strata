<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ tickets: Object, filters: Object })

const search   = ref(props.filters?.search   ?? '')
const status   = ref(props.filters?.status   ?? '')
const priority = ref(props.filters?.priority ?? '')

watch([search, status, priority], ([s, st, p]) => {
  router.get(route('admin.support.index'), { search: s, status: st, priority: p }, { preserveState: true, replace: true })
})

const priorityColor = { urgent: 'text-red-600', high: 'text-orange-500', medium: 'text-gray-700', low: 'text-gray-400' }
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-gray-900 mb-6">Support Tickets</h1>

    <div class="flex flex-wrap gap-3 mb-4">
      <input v-model="search" type="search" placeholder="Search…" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-52 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      <select v-model="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Statuses</option>
        <option v-for="s in ['open','answered','customer_reply','on_hold','closed']" :key="s" :value="s">{{ s.replace(/_/g,' ') }}</option>
      </select>
      <select v-model="priority" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Priorities</option>
        <option v-for="p in ['urgent','high','medium','low']" :key="p" :value="p" class="capitalize">{{ p }}</option>
      </select>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Subject</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Client</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Priority</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Status</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Last Reply</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <Link :href="route('admin.support.show', t.id)" class="text-indigo-600 hover:underline font-medium">{{ t.subject }}</Link>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ t.user?.name }}</td>
            <td class="px-4 py-3 font-medium capitalize" :class="priorityColor[t.priority]">{{ t.priority }}</td>
            <td class="px-4 py-3 text-right"><StatusBadge :status="t.status" /></td>
            <td class="px-4 py-3 text-right text-gray-400">{{ t.last_reply_at ? new Date(t.last_reply_at).toLocaleDateString() : '—' }}</td>
          </tr>
          <tr v-if="!tickets.data.length">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400">No tickets found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
