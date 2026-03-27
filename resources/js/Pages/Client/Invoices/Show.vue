<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

defineProps({ invoice: Object })

const paying = ref(false)
const payError = ref(null)

const justPaid = computed(() => new URLSearchParams(window.location.search).get('paid') === '1')

async function payNow(invoiceId, amountDue) {
  paying.value = true
  payError.value = null
  try {
    const { data } = await axios.post(route('client.invoices.checkout', invoiceId))
    window.location.href = data.url
  } catch (e) {
    payError.value = e.response?.data?.error ?? 'Payment failed. Please try again.'
    paying.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('client.invoices.index')" class="text-sm text-gray-500 hover:text-gray-700">← Invoices</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">Invoice #{{ invoice.id }}</h1>
      <StatusBadge :status="invoice.status" />
    </div>

    <!-- Paid banner (Stripe redirect back with ?paid=1) -->
    <div v-if="justPaid" class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
      Payment received — thank you! Your invoice will be marked paid shortly.
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
          <tr v-if="invoice.credit_applied > 0">
            <td colspan="2" class="pt-3 text-right text-gray-500">Credit Applied</td>
            <td class="pt-3 text-right text-green-600">-${{ invoice.credit_applied }}</td>
          </tr>
          <tr>
            <td colspan="2" class="pt-3 text-right font-medium text-gray-500">Total</td>
            <td class="pt-3 text-right font-bold text-lg">${{ invoice.total }}</td>
          </tr>
          <tr v-if="invoice.credit_applied > 0">
            <td colspan="2" class="pt-1 text-right font-medium text-gray-500">Amount Due</td>
            <td class="pt-1 text-right font-bold text-indigo-600">${{ invoice.amount_due }}</td>
          </tr>
        </tfoot>
      </table>

      <!-- Payment error -->
      <div v-if="payError" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
        {{ payError }}
      </div>

      <!-- Pay Now -->
      <div v-if="invoice.status === 'unpaid'" class="flex justify-end">
        <button
          @click="payNow(invoice.id, invoice.amount_due)"
          :disabled="paying"
          class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-60 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors"
        >
          <svg v-if="paying" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
          </svg>
          {{ paying ? 'Redirecting…' : 'Pay Now — $' + invoice.amount_due }}
        </button>
      </div>

      <!-- Payment history -->
      <div v-if="invoice.payments?.length" class="mt-6 pt-5 border-t border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Payment History</h2>
        <ul class="space-y-2 text-sm">
          <li v-for="p in invoice.payments" :key="p.id" class="flex justify-between text-gray-600">
            <span class="capitalize">{{ p.gateway }} — {{ p.status }}</span>
            <span>${{ p.amount }}<span v-if="p.paid_at" class="text-gray-400 text-xs ml-2">{{ new Date(p.paid_at).toLocaleDateString() }}</span></span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
