<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  invoices:     Object,
  activeFilter: { type: String, default: 'all' },
  from:         { type: String, default: '' },
  to:           { type: String, default: '' },
  summary:      Object,
})

const filters = [
  { key: 'all',     label: 'All' },
  { key: 'unpaid',  label: 'Unpaid' },
  { key: 'overdue', label: 'Overdue' },
  { key: 'paid',    label: 'Paid' },
]

const fromDate = ref(props.from)
const toDate   = ref(props.to)

function applyFilters(status) {
  const params = {}
  if (status && status !== 'all') params.status = status
  if (fromDate.value) params.from = fromDate.value
  if (toDate.value)   params.to   = toDate.value
  router.get(route('client.invoices.index'), params, { preserveState: true, replace: true })
}

function setFilter(key) { applyFilters(key) }

function fmt(n) { return '$' + Number(n ?? 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') }
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-gray-900 mb-6">Billing History</h1>

    <!-- Summary cards -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <p class="text-xs text-gray-400 mb-1">Total Billed</p>
        <p class="text-lg font-bold text-gray-900">{{ fmt(summary?.total_billed) }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <p class="text-xs text-gray-400 mb-1">Total Paid</p>
        <p class="text-lg font-bold text-green-600">{{ fmt(summary?.total_paid) }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
        <p class="text-xs text-gray-400 mb-1">Outstanding</p>
        <p class="text-lg font-bold" :class="Number(summary?.total_outstanding) > 0 ? 'text-red-600' : 'text-gray-400'">
          {{ fmt(summary?.total_outstanding) }}
        </p>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <!-- Status tabs -->
      <div class="flex gap-1 border-b border-gray-200 flex-1">
        <button
          v-for="f in filters" :key="f.key"
          @click="setFilter(f.key)"
          class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
          :class="activeFilter === f.key
            ? 'border-indigo-600 text-indigo-600'
            : 'border-transparent text-gray-500 hover:text-gray-700'"
        >
          {{ f.label }}
        </button>
      </div>
      <!-- Date range -->
      <div class="flex items-center gap-2 text-sm">
        <input v-model="fromDate" type="date" @change="applyFilters(activeFilter)"
          class="border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <span class="text-gray-400">–</span>
        <input v-model="toDate" type="date" @change="applyFilters(activeFilter)"
          class="border border-gray-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <button v-if="fromDate || toDate" @click="fromDate=''; toDate=''; applyFilters(activeFilter)"
          class="text-xs text-gray-400 hover:text-gray-600">Clear</button>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">#</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Due</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Total</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Due Now</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Status</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">PDF</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="inv in invoices.data" :key="inv.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <Link :href="route('client.invoices.show', inv.id)" class="text-indigo-600 hover:underline font-medium">#{{ inv.id }}</Link>
            </td>
            <td class="px-4 py-3 text-gray-500">{{ inv.date }}</td>
            <td class="px-4 py-3 text-gray-500">{{ inv.due_date }}</td>
            <td class="px-4 py-3 text-right font-medium">${{ inv.total }}</td>
            <td class="px-4 py-3 text-right" :class="Number(inv.amount_due) > 0 ? 'text-indigo-600 font-semibold' : 'text-gray-400'">${{ inv.amount_due }}</td>
            <td class="px-4 py-3 text-right"><StatusBadge :status="inv.status" /></td>
            <td class="px-4 py-3 text-right">
              <a :href="route('client.invoices.download', inv.id)" target="_blank" class="text-gray-400 hover:text-gray-600" title="Download PDF">
                <svg class="h-4 w-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
              </a>
            </td>
          </tr>
          <tr v-if="!invoices.data.length">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400">No invoices found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="invoices.last_page > 1" class="mt-4 flex justify-center gap-2 text-sm">
      <Link
        v-for="link in invoices.links" :key="link.label"
        :href="link.url ?? '#'"
        v-html="link.label"
        class="px-3 py-1.5 rounded border"
        :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
      />
    </div>
  </div>
</template>
