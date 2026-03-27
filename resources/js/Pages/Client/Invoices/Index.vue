<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ invoices: Object })
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-gray-900 mb-6">My Invoices</h1>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">#</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Total</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Due</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="inv in invoices.data" :key="inv.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <Link :href="route('client.invoices.show', inv.id)" class="text-indigo-600 hover:underline">#{{ inv.id }}</Link>
            </td>
            <td class="px-4 py-3 text-right font-medium">${{ inv.total }}</td>
            <td class="px-4 py-3 text-right text-gray-500">{{ inv.due_date }}</td>
            <td class="px-4 py-3 text-right"><StatusBadge :status="inv.status" /></td>
          </tr>
          <tr v-if="!invoices.data.length">
            <td colspan="4" class="px-4 py-8 text-center text-gray-400">No invoices yet.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
