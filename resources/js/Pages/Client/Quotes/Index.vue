<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ quotes: Array })

function fmt(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString()
}
</script>

<template>
  <div class="max-w-3xl">
    <h1 class="text-xl font-bold text-gray-900 mb-6">Quotes</h1>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-500 border-b border-gray-100 text-xs uppercase">
            <th class="px-4 py-3">Quote</th>
            <th class="px-4 py-3 text-right">Total</th>
            <th class="px-4 py-3 text-center">Valid Until</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="q in quotes" :key="q.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-gray-800">{{ q.quote_number ?? '#' + q.id }}</td>
            <td class="px-4 py-3 text-right font-medium">${{ q.total }}</td>
            <td class="px-4 py-3 text-center text-gray-500">{{ fmt(q.valid_until) }}</td>
            <td class="px-4 py-3 text-center"><StatusBadge :status="q.status" /></td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('client.quotes.show', q.id)" class="text-indigo-600 text-xs hover:underline">View</Link>
            </td>
          </tr>
          <tr v-if="!quotes?.length">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400">No quotes yet.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
