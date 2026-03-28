<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ quotes: Object })

function fmt(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString()
}
</script>

<template>
  <div class="max-w-5xl">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Quotes</h1>
      <Link :href="route('admin.quotes.create')"
        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
        + New Quote
      </Link>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-500 border-b border-gray-100 text-xs uppercase">
            <th class="px-4 py-3">Quote</th>
            <th class="px-4 py-3">Client</th>
            <th class="px-4 py-3 text-right">Total</th>
            <th class="px-4 py-3 text-center">Valid Until</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="q in quotes.data" :key="q.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-gray-800">{{ q.quote_number ?? '#' + q.id }}</td>
            <td class="px-4 py-3">
              <Link :href="route('admin.clients.show', q.user?.id)" class="text-indigo-600 hover:underline">
                {{ q.user?.name }}
              </Link>
              <p class="text-xs text-gray-400">{{ q.user?.email }}</p>
            </td>
            <td class="px-4 py-3 text-right font-medium">${{ q.total }}</td>
            <td class="px-4 py-3 text-center text-gray-500">{{ fmt(q.valid_until) }}</td>
            <td class="px-4 py-3 text-center"><StatusBadge :status="q.status" /></td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('admin.quotes.show', q.id)" class="text-indigo-600 text-xs hover:underline">View</Link>
            </td>
          </tr>
          <tr v-if="!quotes.data?.length">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">No quotes yet.</td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="quotes.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex gap-2 text-sm">
        <Link v-if="quotes.prev_page_url" :href="quotes.prev_page_url"
          class="px-3 py-1 border rounded hover:bg-gray-50">← Prev</Link>
        <span class="px-3 py-1 text-gray-500">Page {{ quotes.current_page }} of {{ quotes.last_page }}</span>
        <Link v-if="quotes.next_page_url" :href="quotes.next_page_url"
          class="px-3 py-1 border rounded hover:bg-gray-50">Next →</Link>
      </div>
    </div>
  </div>
</template>
