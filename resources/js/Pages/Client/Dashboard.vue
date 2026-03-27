<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({
  stats:            { type: Object, required: true },
  services_due:     { type: Array,  default: () => [] },
  unpaid_invoices:  { type: Array,  default: () => [] },
  recent_tickets:   { type: Array,  default: () => [] },
})
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-gray-900 mb-6">My Dashboard</h1>

    <!-- Stats -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Services due soon -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex justify-between items-center mb-3">
          <h2 class="font-semibold text-gray-900 text-sm">Services Due Soon</h2>
          <Link :href="route('client.services.index')" class="text-xs text-indigo-600 hover:underline">View all</Link>
        </div>
        <ul class="divide-y divide-gray-100 text-sm">
          <li v-for="s in services_due" :key="s.id" class="py-2.5 flex justify-between">
            <span class="truncate">{{ s.domain ?? s.product?.name }}</span>
            <span class="text-gray-400 text-xs ml-2 shrink-0">{{ s.next_due_date }}</span>
          </li>
          <li v-if="!services_due.length" class="py-5 text-center text-gray-400">No services due soon.</li>
        </ul>
      </div>

      <!-- Unpaid invoices -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex justify-between items-center mb-3">
          <h2 class="font-semibold text-gray-900 text-sm">Unpaid Invoices</h2>
          <Link :href="route('client.invoices.index')" class="text-xs text-indigo-600 hover:underline">View all</Link>
        </div>
        <ul class="divide-y divide-gray-100 text-sm">
          <li v-for="inv in unpaid_invoices" :key="inv.id" class="py-2.5 flex justify-between">
            <Link :href="route('client.invoices.show', inv.id)" class="text-indigo-600 hover:underline">Invoice #{{ inv.id }}</Link>
            <span class="font-medium">${{ inv.total }}</span>
          </li>
          <li v-if="!unpaid_invoices.length" class="py-5 text-center text-gray-400">All invoices paid.</li>
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
