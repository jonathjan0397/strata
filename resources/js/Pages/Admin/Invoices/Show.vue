<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ invoice: Object })
</script>

<template>
  <div class="max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.invoices.index')" class="text-sm text-gray-500 hover:text-gray-700">← Invoices</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">Invoice #{{ invoice.id }}</h1>
      <StatusBadge :status="invoice.status" />
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-4">
      <div class="flex justify-between text-sm mb-6">
        <div>
          <p class="font-semibold text-gray-900">{{ invoice.user?.name }}</p>
          <p class="text-gray-500">{{ invoice.user?.email }}</p>
        </div>
        <div class="text-right">
          <p class="text-gray-500">Date: {{ invoice.date }}</p>
          <p class="text-gray-500">Due: {{ invoice.due_date }}</p>
        </div>
      </div>

      <table class="min-w-full text-sm mb-6">
        <thead>
          <tr class="text-left text-gray-500 border-b border-gray-100">
            <th class="pb-2">Description</th>
            <th class="pb-2 text-right">Qty</th>
            <th class="pb-2 text-right">Unit</th>
            <th class="pb-2 text-right">Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in invoice.items" :key="item.id" class="border-b border-gray-100">
            <td class="py-2">{{ item.description }}</td>
            <td class="py-2 text-right">{{ item.quantity }}</td>
            <td class="py-2 text-right">${{ item.unit_price }}</td>
            <td class="py-2 text-right font-medium">${{ item.total }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" class="pt-3 text-right text-gray-500 font-medium">Total</td>
            <td class="pt-3 text-right font-bold text-lg">${{ invoice.total }}</td>
          </tr>
        </tfoot>
      </table>

      <div v-if="invoice.status !== 'paid' && invoice.status !== 'cancelled'" class="flex gap-2 justify-end">
        <button class="text-sm bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-lg"
          @click="router.post(route('admin.invoices.mark-paid', invoice.id))">
          Mark Paid
        </button>
        <button class="text-sm border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg"
          @click="router.post(route('admin.invoices.cancel', invoice.id))">
          Cancel
        </button>
      </div>
    </div>
  </div>
</template>
