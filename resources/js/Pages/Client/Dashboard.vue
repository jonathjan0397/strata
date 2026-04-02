<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({
  stats:           { type: Object, required: true },
  all_services:    { type: Array,  default: () => [] },
  services_due:    { type: Array,  default: () => [] },
  unpaid_invoices: { type: Array,  default: () => [] },
  billing_history: { type: Array,  default: () => [] },
  recent_tickets:  { type: Array,  default: () => [] },
})

const fmt = (n) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n ?? 0)

const statusColor = {
  active:    'bg-green-100 text-green-700',
  suspended: 'bg-red-100 text-red-700',
  pending:   'bg-amber-100 text-amber-700',
  cancelled: 'bg-gray-100 text-gray-500',
}
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-xl font-bold text-gray-900">My Dashboard</h1>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-green-600">{{ stats.active_services }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Active Services</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-amber-600">{{ stats.unpaid_invoices }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Unpaid Invoices</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-indigo-600">{{ stats.open_tickets }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Open Tickets</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
        <p class="text-2xl font-bold text-blue-600">{{ stats.active_domains }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Active Domains</p>
      </div>
    </div>

    <!-- My Services -->
    <div class="bg-white rounded-xl border border-gray-200">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900 text-sm">My Services</h2>
        <Link :href="route('client.services.index')" class="text-xs text-indigo-600 hover:underline">Manage all →</Link>
      </div>
      <div v-if="all_services.length" class="divide-y divide-gray-50">
        <div v-for="s in all_services" :key="s.id"
          class="flex items-center justify-between px-5 py-3 gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full capitalize shrink-0"
              :class="statusColor[s.status] ?? 'bg-gray-100 text-gray-500'">
              {{ s.status }}
            </span>
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ s.domain ?? s.product?.name ?? '—' }}</p>
              <p v-if="s.product" class="text-xs text-gray-400">{{ s.product.name }}</p>
            </div>
          </div>
          <div class="text-right shrink-0">
            <p class="text-sm font-semibold text-gray-900">{{ fmt(s.amount) }}</p>
            <p class="text-xs text-gray-400">{{ s.next_due_date ? 'Due ' + s.next_due_date : '' }}</p>
          </div>
        </div>
      </div>
      <div v-else class="px-5 py-8 text-center text-sm text-gray-400">
        No active services yet.
        <Link :href="route('client.order.catalog')" class="ml-1 text-indigo-600 hover:underline">Browse plans →</Link>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Unpaid invoices -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex justify-between items-center mb-3">
          <h2 class="font-semibold text-gray-900 text-sm">Unpaid Invoices</h2>
          <Link :href="route('client.invoices.index')" class="text-xs text-indigo-600 hover:underline">View all</Link>
        </div>
        <ul class="divide-y divide-gray-100 text-sm">
          <li v-for="inv in unpaid_invoices" :key="inv.id" class="py-2.5 flex justify-between items-center">
            <Link :href="route('client.invoices.show', inv.id)" class="text-indigo-600 hover:underline">
              Invoice #{{ inv.id }}
            </Link>
            <div class="text-right">
              <p class="font-semibold text-gray-900">{{ fmt(inv.total) }}</p>
              <p class="text-xs text-gray-400">Due {{ inv.due_date }}</p>
            </div>
          </li>
          <li v-if="!unpaid_invoices.length" class="py-5 text-center text-gray-400">All invoices paid.</li>
        </ul>
      </div>

      <!-- Billing history -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex justify-between items-center mb-3">
          <h2 class="font-semibold text-gray-900 text-sm">Billing History</h2>
          <Link :href="route('client.invoices.index', { status: 'paid' })" class="text-xs text-indigo-600 hover:underline">View all</Link>
        </div>
        <ul class="divide-y divide-gray-100 text-sm">
          <li v-for="inv in billing_history" :key="inv.id" class="py-2.5 flex justify-between items-center">
            <Link :href="route('client.invoices.show', inv.id)" class="text-indigo-600 hover:underline">
              Invoice #{{ inv.id }}
            </Link>
            <div class="text-right">
              <p class="font-semibold text-green-700">{{ fmt(inv.total) }}</p>
              <p class="text-xs text-gray-400">Paid {{ inv.paid_at ? new Date(inv.paid_at).toLocaleDateString() : '' }}</p>
            </div>
          </li>
          <li v-if="!billing_history.length" class="py-5 text-center text-gray-400">No payment history yet.</li>
        </ul>
      </div>

      <!-- Recent tickets -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 lg:col-span-2">
        <div class="flex justify-between items-center mb-3">
          <h2 class="font-semibold text-gray-900 text-sm">Recent Support Tickets</h2>
          <Link :href="route('client.tickets.index')" class="text-xs text-indigo-600 hover:underline">View all</Link>
        </div>
        <ul class="divide-y divide-gray-100 text-sm">
          <li v-for="t in recent_tickets" :key="t.id" class="py-2.5 flex items-center justify-between gap-2">
            <Link :href="route('client.tickets.show', t.id)" class="truncate text-indigo-600 hover:underline">
              #{{ t.id }} — {{ t.subject }}
            </Link>
            <StatusBadge :status="t.status" class="shrink-0" />
          </li>
          <li v-if="!recent_tickets.length" class="py-5 text-center text-gray-400">No open tickets.</li>
        </ul>
      </div>

    </div>
  </div>
</template>
