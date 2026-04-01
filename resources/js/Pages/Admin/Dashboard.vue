<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

defineProps({
  stats:          { type: Object, required: true },
  recent_orders:  { type: Array,  default: () => [] },
  recent_tickets: { type: Array,  default: () => [] },
})

const license = computed(() => usePage().props.license ?? { managed: false, active: true, features: [] })

const PREMIUM_FEATURES = ['workflows', 'affiliates', 'advanced_reports', 'quotes', 'audit_log', 'client_groups']
const missingFeatures  = computed(() => {
  if (!license.value.managed) return []
  return PREMIUM_FEATURES.filter(f => !license.value.features?.includes(f))
})

const showNudge        = computed(() => license.value.managed && missingFeatures.value.length > 0)
const showTrialWarning = computed(() => license.value.managed && license.value.expires_in_days !== null && license.value.expires_in_days <= 7)

const startingTrial = ref(false)
function startTrial() {
  startingTrial.value = true
  router.post(route('admin.settings.license-trial'), {}, {
    preserveScroll: true,
    onFinish: () => { startingTrial.value = false },
  })
}

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

    <!-- Trial expiry warning -->
    <div v-if="showTrialWarning" class="mb-5 flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-3.5 text-sm">
      <svg class="h-5 w-5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
      </svg>
      <span class="text-amber-800">
        <span class="font-semibold">Trial expires in {{ license.expires_in_days }} day{{ license.expires_in_days === 1 ? '' : 's' }}.</span>
        Contact us to add a license key and keep premium features active.
      </span>
    </div>

    <!-- Upgrade nudge -->
    <div v-if="showNudge && !license.trial_used" class="mb-5 rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-4 flex items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <svg class="h-5 w-5 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
        </svg>
        <span class="text-sm text-indigo-900">
          <span class="font-semibold">Unlock premium features</span> — Workflows, Affiliates, Reports, and more.
        </span>
      </div>
      <button
        @click="startTrial"
        :disabled="startingTrial"
        class="shrink-0 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 px-4 py-2 text-xs font-semibold text-white transition-colors whitespace-nowrap shadow-sm"
      >
        {{ startingTrial ? 'Activating…' : 'Start Free Trial' }}
      </button>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div v-for="card in statCards" :key="card.key" :class="['rounded-xl border border-gray-200 p-5', card.bg]">
        <p class="text-sm text-gray-500">{{ card.label }}</p>
        <p :class="['text-3xl font-bold mt-1', card.color]">{{ stats[card.key] }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Recent Orders -->
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

      <!-- Open Tickets -->
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
