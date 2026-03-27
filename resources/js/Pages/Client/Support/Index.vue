<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ tickets: Object })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Support Tickets</h1>
      <Link :href="route('client.support.create')" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg">
        New Ticket
      </Link>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Subject</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Dept</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Last Update</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <Link :href="route('client.support.show', t.id)" class="text-indigo-600 hover:underline">{{ t.subject }}</Link>
            </td>
            <td class="px-4 py-3 text-gray-500 capitalize">{{ t.department }}</td>
            <td class="px-4 py-3 text-right text-gray-400">{{ t.last_reply_at ? new Date(t.last_reply_at).toLocaleDateString() : '—' }}</td>
            <td class="px-4 py-3 text-right"><StatusBadge :status="t.status" /></td>
          </tr>
          <tr v-if="!tickets.data.length">
            <td colspan="4" class="px-4 py-8 text-center text-gray-400">No tickets yet.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
