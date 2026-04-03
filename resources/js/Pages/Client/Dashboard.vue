<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  stats:           { type: Object,  required: true },
  creditBalance:   { type: Number,  default: 0 },
  companyName:     { type: String,  default: '' },
  all_services:    { type: Array,   default: () => [] },
  services_due:    { type: Array,   default: () => [] },
  unpaid_invoices: { type: Array,   default: () => [] },
  billing_history: { type: Array,   default: () => [] },
  recent_tickets:  { type: Array,   default: () => [] },
})

const user = computed(() => usePage().props.auth.user)

const fmt = (n) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n ?? 0)

const totalDue = computed(() =>
  props.unpaid_invoices.reduce((sum, inv) => sum + parseFloat(inv.amount_due ?? inv.total ?? 0), 0)
)

const statusColor = {
  active:    'bg-green-100 text-green-700',
  suspended: 'bg-red-100 text-red-700',
  pending:   'bg-amber-100 text-amber-700',
  cancelled: 'bg-gray-100 text-gray-500',
}

const cycleShort = {
  monthly: '/mo', quarterly: '/qtr', semi_annual: '/6mo',
  annual: '/yr', biennial: '/2yr', triennial: '/3yr', one_time: '',
}

function daysUntil(dateStr) {
  if (!dateStr) return null
  const diff = Math.ceil((new Date(dateStr) - new Date()) / 86400000)
  return diff
}

function dueSoonColor(dateStr) {
  const d = daysUntil(dateStr)
  if (d === null) return ''
  if (d <= 7)  return 'text-red-600'
  if (d <= 14) return 'text-amber-600'
  return 'text-gray-400'
}
</script>

<template>
  <div class="space-y-6 max-w-5xl">

    <!-- Welcome banner -->
    <div class="rounded-2xl bg-gradient-to-r from-indigo-600 to-blue-500 p-6 flex items-center justify-between gap-4 shadow-lg shadow-indigo-500/20">
      <div>
        <p class="text-indigo-200 text-sm mb-0.5">Welcome back,</p>
        <h1 class="text-2xl font-bold text-white">{{ user?.name ?? 'there' }}</h1>
        <p v-if="companyName" class="text-indigo-200/70 text-sm mt-1">{{ companyName }}</p>
      </div>
      <Link :href="route('client.order.catalog')"
        class="shrink-0 inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 border border-white/20 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        Order Service
      </Link>
    </div>

    <!-- Outstanding balance alert -->
    <div v-if="totalDue > 0"
      class="flex items-center justify-between gap-4 rounded-xl bg-amber-50 border border-amber-200 px-5 py-3.5">
      <div class="flex items-center gap-3">
        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
        <span class="text-sm text-amber-800">
          You have <strong>{{ fmt(totalDue) }}</strong> outstanding across {{ stats.unpaid_invoices }} unpaid invoice{{ stats.unpaid_invoices !== 1 ? 's' : '' }}.
        </span>
      </div>
      <Link :href="route('client.invoices.index', { status: 'unpaid' })"
        class="shrink-0 text-sm font-semibold text-amber-800 underline hover:text-amber-600">
        Pay Now →
      </Link>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <Link :href="route('client.services.index')"
        class="bg-white rounded-xl border border-gray-200 p-4 text-center hover:shadow-md hover:border-indigo-200 transition-all">
        <p class="text-3xl font-bold text-indigo-600">{{ stats.active_services }}</p>
        <p class="text-xs text-gray-500 mt-1">Active Services</p>
      </Link>
      <Link :href="route('client.invoices.index', { status: 'unpaid' })"
        class="bg-white rounded-xl border border-gray-200 p-4 text-center hover:shadow-md transition-all"
        :class="stats.unpaid_invoices > 0 ? 'border-amber-200 hover:border-amber-300' : 'hover:border-indigo-200'">
        <p class="text-3xl font-bold" :class="stats.unpaid_invoices > 0 ? 'text-amber-600' : 'text-gray-400'">
          {{ stats.unpaid_invoices }}
        </p>
        <p class="text-xs text-gray-500 mt-1">Unpaid Invoices</p>
      </Link>
      <Link :href="route('client.support.index')"
        class="bg-white rounded-xl border border-gray-200 p-4 text-center hover:shadow-md hover:border-indigo-200 transition-all">
        <p class="text-3xl font-bold text-blue-600">{{ stats.open_tickets }}</p>
        <p class="text-xs text-gray-500 mt-1">Open Tickets</p>
      </Link>
      <div class="bg-white rounded-xl border border-gray-200 p-4 text-center"
        :class="creditBalance > 0 ? 'border-green-200' : ''">
        <p class="text-3xl font-bold" :class="creditBalance > 0 ? 'text-green-600' : 'text-gray-400'">
          {{ fmt(creditBalance) }}
        </p>
        <p class="text-xs text-gray-500 mt-1">Account Credit</p>
      </div>
    </div>

    <!-- Services -->
    <div class="bg-white rounded-xl border border-gray-200">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900 text-sm">My Services</h2>
        <Link :href="route('client.services.index')" class="text-xs text-indigo-600 hover:underline">Manage all →</Link>
      </div>

      <div v-if="all_services.length" class="divide-y divide-gray-50">
        <Link
          v-for="s in all_services" :key="s.id"
          :href="route('client.services.show', s.id)"
          class="flex items-center justify-between px-5 py-3.5 gap-3 hover:bg-gray-50/70 transition-colors">
          <div class="flex items-center gap-3 min-w-0">
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full capitalize shrink-0"
              :class="statusColor[s.status] ?? 'bg-gray-100 text-gray-500'">
              {{ s.status }}
            </span>
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ s.domain ?? s.product?.name ?? '—' }}</p>
              <p v-if="s.domain && s.product" class="text-xs text-gray-400 truncate">{{ s.product.name }}</p>
            </div>
          </div>
          <div class="text-right shrink-0">
            <p class="text-sm font-semibold text-gray-900">{{ fmt(s.amount) }}<span class="text-xs font-normal text-gray-400">{{ cycleShort[s.billing_cycle] ?? '' }}</span></p>
            <p v-if="s.next_due_date" class="text-xs mt-0.5" :class="dueSoonColor(s.next_due_date)">
              Due {{ s.next_due_date }}
              <span v-if="daysUntil(s.next_due_date) !== null && daysUntil(s.next_due_date) <= 30">
                ({{ daysUntil(s.next_due_date) }}d)
              </span>
            </p>
          </div>
        </Link>
      </div>

      <div v-else class="px-5 py-10 text-center">
        <svg class="w-10 h-10 mx-auto mb-3 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2"/>
        </svg>
        <p class="text-sm text-gray-400 mb-3">You don't have any active services yet.</p>
        <Link :href="route('client.order.catalog')"
          class="inline-flex items-center gap-1.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 px-4 py-2 rounded-lg transition-colors">
          Browse Plans
        </Link>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Unpaid invoices -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex justify-between items-center mb-4">
          <h2 class="font-semibold text-gray-900 text-sm">Unpaid Invoices</h2>
          <Link :href="route('client.invoices.index')" class="text-xs text-indigo-600 hover:underline">View all</Link>
        </div>
        <ul class="divide-y divide-gray-100 text-sm">
          <li v-for="inv in unpaid_invoices" :key="inv.id"
            class="py-3 flex justify-between items-center gap-3">
            <Link :href="route('client.invoices.show', inv.id)"
              class="text-indigo-600 hover:underline font-medium">
              Invoice #{{ inv.id }}
            </Link>
            <div class="text-right shrink-0">
              <p class="font-semibold text-amber-700">{{ fmt(inv.amount_due ?? inv.total) }}</p>
              <p class="text-xs text-gray-400">Due {{ inv.due_date }}</p>
            </div>
          </li>
          <li v-if="!unpaid_invoices.length" class="py-6 text-center text-gray-400">
            <svg class="w-8 h-8 mx-auto mb-2 text-green-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            All invoices paid — great!
          </li>
        </ul>
      </div>

      <!-- Recent tickets -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex justify-between items-center mb-4">
          <h2 class="font-semibold text-gray-900 text-sm">Support Tickets</h2>
          <Link :href="route('client.support.index')" class="text-xs text-indigo-600 hover:underline">View all</Link>
        </div>
        <ul class="divide-y divide-gray-100 text-sm">
          <li v-for="t in recent_tickets" :key="t.id"
            class="py-3 flex items-center justify-between gap-3">
            <Link :href="route('client.support.show', t.id)"
              class="truncate text-indigo-600 hover:underline font-medium">
              #{{ t.id }} — {{ t.subject }}
            </Link>
            <StatusBadge :status="t.status" class="shrink-0" />
          </li>
          <li v-if="!recent_tickets.length" class="py-6 text-center text-gray-400">
            No open tickets.
            <Link :href="route('client.support.create')" class="ml-1 text-indigo-600 hover:underline">Open one →</Link>
          </li>
        </ul>
      </div>

      <!-- Billing history -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 lg:col-span-2">
        <div class="flex justify-between items-center mb-4">
          <h2 class="font-semibold text-gray-900 text-sm">Billing History</h2>
          <Link :href="route('client.invoices.index', { status: 'paid' })" class="text-xs text-indigo-600 hover:underline">View all</Link>
        </div>
        <ul v-if="billing_history.length" class="divide-y divide-gray-100 text-sm">
          <li v-for="inv in billing_history" :key="inv.id"
            class="py-3 flex justify-between items-center">
            <Link :href="route('client.invoices.show', inv.id)" class="text-indigo-600 hover:underline font-medium">
              Invoice #{{ inv.id }}
            </Link>
            <div class="text-right">
              <p class="font-semibold text-green-700">{{ fmt(inv.total) }}</p>
              <p class="text-xs text-gray-400">{{ inv.paid_at ? new Date(inv.paid_at).toLocaleDateString() : inv.date }}</p>
            </div>
          </li>
        </ul>
        <p v-else class="py-5 text-center text-sm text-gray-400">No payment history yet.</p>
      </div>

    </div>
  </div>
</template>
