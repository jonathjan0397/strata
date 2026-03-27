<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ invoices: Object, filters: Object })

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

watch([search, status], ([s, st]) => {
  router.get(route('admin.invoices.index'), { search: s, status: st }, { preserveState: true, replace: true })
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Invoices</h1>
      <Link :href="route('admin.invoices.create')" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg">
        Create Invoice
      </Link>
    </div>

    <div class="flex gap-3 mb-4">
      <input v-model="search" type="search" placeholder="Search client…" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-60 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      <select v-model="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All</option>
        <option v-for="s in ['draft','unpaid','paid','overdue','cancelled','refunded']" :key="s" :value="s" class="capitalize">{{ s }}</option>
      </select>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">#</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Client</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Total</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Due</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="inv in invoices.data" :key="inv.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <Link :href="route('admin.invoices.show', inv.id)" class="text-indigo-600 hover:underline">#{{ inv.id }}</Link>
            </td>
            <td class="px-4 py-3 text-gray-700">{{ inv.user?.name }}</td>
            <td class="px-4 py-3 text-right font-medium">${{ inv.total }}</td>
            <td class="px-4 py-3 text-right text-gray-500">{{ inv.due_date }}</td>
            <td class="px-4 py-3 text-right"><StatusBadge :status="inv.status" /></td>
          </tr>
          <tr v-if="!invoices.data.length">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400">No invoices found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
