<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

defineProps({
  orders:  Object,
  filters: Object,
})

const search = ref('')
const status = ref('')

function applyFilters() {
  router.get(route('admin.orders.index'), { search: search.value, status: status.value }, { preserveState: true, replace: true })
}

const statusColors = {
  pending:   'bg-yellow-100 text-yellow-700',
  active:    'bg-green-100 text-green-700',
  fraud:     'bg-red-100 text-red-700',
  cancelled: 'bg-gray-100 text-gray-500',
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Orders</h1>
    </div>

    <!-- Filters -->
    <div class="flex gap-3 mb-4">
      <input
        v-model="search"
        type="text"
        placeholder="Order # or client name / email…"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-72 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        @keydown.enter="applyFilters"
      />
      <select v-model="status" @change="applyFilters"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All statuses</option>
        <option value="pending">Pending</option>
        <option value="active">Active</option>
        <option value="fraud">Fraud</option>
        <option value="cancelled">Cancelled</option>
      </select>
      <button @click="applyFilters"
        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
        Search
      </button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Order #</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Client</th>
            <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Total</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Date</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="order in orders.data" :key="order.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ order.order_number }}</td>
            <td class="px-4 py-3">
              <Link :href="route('admin.clients.show', order.user_id)" class="font-medium text-gray-900 hover:text-indigo-600">
                {{ order.user?.name }}
              </Link>
              <div class="text-xs text-gray-500">{{ order.user?.email }}</div>
            </td>
            <td class="px-4 py-3 text-center">
              <span :class="statusColors[order.status] ?? 'bg-gray-100 text-gray-500'"
                class="text-xs font-medium px-2 py-0.5 rounded-full capitalize">
                {{ order.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-right font-medium">${{ order.total }}</td>
            <td class="px-4 py-3 text-right text-gray-500 text-xs">
              {{ new Date(order.created_at).toLocaleDateString() }}
            </td>
          </tr>
          <tr v-if="!orders.data?.length">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400">No orders found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="orders.last_page > 1" class="mt-4 flex items-center justify-between text-sm text-gray-500">
      <span>Showing {{ orders.from }}–{{ orders.to }} of {{ orders.total }}</span>
      <div class="flex gap-2">
        <Link v-if="orders.prev_page_url" :href="orders.prev_page_url"
          class="px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50">← Prev</Link>
        <Link v-if="orders.next_page_url" :href="orders.next_page_url"
          class="px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50">Next →</Link>
      </div>
    </div>
  </div>
</template>
