<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({
  stats:          { type: Object, required: true },
  recent_orders:  { type: Array,  default: () => [] },
  recent_tickets: { type: Array,  default: () => [] },
})

const statCards = [
  { key: 'total_clients',    label: 'Total Clients',     color: 'text-blue-600',   bg: 'bg-blue-50' },
  { key: 'active_services',  label: 'Active Services',   color: 'text-green-600',  bg: 'bg-green-50' },
  { key: 'open_invoices',    label: 'Unpaid Invoices',   color: 'text-amber-600',  bg: 'bg-amber-50' },
  { key: 'open_tickets',     label: 'Open Tickets',      color: 'text-purple-600', bg: 'bg-purple-50' },
]
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-gray-900 mb-6">Admin Dashboard</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div v-for="card in statCards" :key="card.key" :class="['rounded-xl border border-gray-200 p-5', card.bg]">
        <p class="text-sm text-gray-500">{{ card.label }}</p>
        <p :class="['text-3xl font-bold mt-1', card.color]">{{ stats[card.key] }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-semibold text-gray-900">Recent Orders</h2>
          <Link :href="route('admin.clients.index')" class="text-xs text-indigo-600 hover:underline">View all</Link>
        </div>
        <ul class="divide-y divide-gray-100">
          <li v-for="order in recent_orders" :key="order.id" class="py-3 flex items-center justify-between text-sm">
            <div>
              <p class="font-medium text-gray-900">{{ order.user?.name }}</p>
              <p class="text-gray-400 text-xs">Order #{{ order.id }}</p>
            </div>
            <div class="text-right">
              <p class="font-medium">${{ order.total }}</p>
              <StatusBadge :status="order.status" />
            </div>
          </li>
          <li v-if="!recent_orders.length" class="py-6 text-center text-sm text-gray-400">No orders yet.</li>
        </ul>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-semibold text-gray-900">Open Tickets</h2>
          <Link :href="route('admin.support.index')" class="text-xs text-indigo-600 hover:underline">View all</Link>
        </div>
        <ul class="divide-y divide-gray-100">
          <li v-for="ticket in recent_tickets" :key="ticket.id" class="py-3">
            <Link :href="route('admin.support.show', ticket.id)" class="group">
              <p class="text-sm font-medium text-gray-900 group-hover:text-indigo-600 truncate">{{ ticket.subject }}</p>
              <div class="flex items-center gap-2 mt-0.5">
                <p class="text-xs text-gray-400">{{ ticket.user?.name }}</p>
                <StatusBadge :status="ticket.status" />
                <StatusBadge :status="ticket.priority" />
              </div>
            </Link>
          </li>
          <li v-if="!recent_tickets.length" class="py-6 text-center text-sm text-gray-400">No open tickets.</li>
        </ul>
      </div>
    </div>
  </div>
</template>
