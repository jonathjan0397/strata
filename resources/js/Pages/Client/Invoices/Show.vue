<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ invoice: Object })
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('client.invoices.index')" class="text-sm text-gray-500 hover:text-gray-700">← Invoices</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">Invoice #{{ invoice.id }}</h1>
      <StatusBadge :status="invoice.status" />
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <div class="flex justify-between text-sm mb-5 text-gray-500">
        <div><span>Date: </span>{{ invoice.date }}</div>
        <div><span>Due: </span>{{ invoice.due_date }}</div>
      </div>

      <table class="min-w-full text-sm mb-5">
        <thead>
          <tr class="text-left text-gray-500 border-b border-gray-100">
            <th class="pb-2">Description</th>
            <th class="pb-2 text-right">Qty</th>
            <th class="pb-2 text-right">Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in invoice.items" :key="item.id" class="border-b border-gray-100">
            <td class="py-2">{{ item.description }}</td>
            <td class="py-2 text-right">{{ item.quantity }}</td>
            <td class="py-2 text-right font-medium">${{ item.total }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2" class="pt-3 text-right font-medium text-gray-500">Total</td>
            <td class="pt-3 text-right font-bold text-lg">${{ invoice.total }}</td>
          </tr>
        </tfoot>
      </table>

      <div v-if="invoice.status === 'unpaid'" class="flex justify-end">
        <button class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-5 py-2.5 rounded-lg">
          Pay Now — ${{ invoice.amount_due }}
        </button>
      </div>
    </div>
  </div>
</template>
