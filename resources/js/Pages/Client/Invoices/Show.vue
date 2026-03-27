<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

defineProps({ invoice: Object })

const page = usePage()
const flash = computed(() => page.props.flash)

const payingStripe  = ref(false)
const payingPayPal  = ref(false)
const payError      = ref(null)

// Stripe redirects back with ?paid=1
const justPaid = computed(() => new URLSearchParams(window.location.search).get('paid') === '1')

async function payStripe(invoiceId) {
  payingStripe.value = true
  payError.value = null
  try {
    const { data } = await axios.post(route('client.invoices.checkout', invoiceId))
    window.location.href = data.url
  } catch (e) {
    payError.value = e.response?.data?.error ?? 'Stripe payment failed. Please try again.'
    payingStripe.value = false
  }
}

async function payPayPal(invoiceId) {
  payingPayPal.value = true
  payError.value = null
  try {
    const { data } = await axios.post(route('client.invoices.paypal.checkout', invoiceId))
    window.location.href = data.url
  } catch (e) {
    payError.value = e.response?.data?.error ?? 'PayPal payment failed. Please try again.'
    payingPayPal.value = false
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

    <!-- Success banners -->
    <div v-if="justPaid || flash?.success" class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
      {{ flash?.success ?? 'Payment received — thank you! Your invoice will be marked paid shortly.' }}
    </div>
    <div v-if="flash?.error" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
      {{ flash.error }}
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <div class="flex justify-between text-sm mb-5 text-gray-500">
        <div>Date: {{ invoice.date }}</div>
        <div>Due: {{ invoice.due_date }}</div>
      </div>

      <!-- Line items -->
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
          <tr v-if="invoice.tax > 0">
            <td colspan="2" class="pt-3 text-right text-gray-500">Tax ({{ invoice.tax_rate }}%)</td>
            <td class="pt-3 text-right text-gray-600">${{ invoice.tax }}</td>
          </tr>
          <tr v-if="invoice.credit_applied > 0">
            <td colspan="2" class="pt-1 text-right text-gray-500">Credit Applied</td>
            <td class="pt-1 text-right text-green-600">-${{ invoice.credit_applied }}</td>
          </tr>
          <tr>
            <td colspan="2" class="pt-3 text-right font-semibold text-gray-700">Total</td>
            <td class="pt-3 text-right font-bold text-lg">${{ invoice.total }}</td>
          </tr>
          <tr v-if="Number(invoice.credit_applied) > 0 || Number(invoice.tax) > 0">
            <td colspan="2" class="pt-1 text-right font-semibold text-gray-700">Amount Due</td>
            <td class="pt-1 text-right font-bold text-indigo-600 text-lg">${{ invoice.amount_due }}</td>
          </tr>
        </tfoot>
      </table>

      <!-- Payment error -->
      <div v-if="payError" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
        {{ payError }}
      </div>

      <!-- Payment options -->
      <div v-if="invoice.status === 'unpaid'" class="border-t border-gray-100 pt-5">
        <p class="text-sm text-gray-500 mb-3 text-right">Pay securely with:</p>
        <div class="flex justify-end gap-3 flex-wrap">

          <!-- Stripe -->
          <button
            @click="payStripe(invoice.id)"
            :disabled="payingStripe || payingPayPal"
            class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-60 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors"
          >
            <svg v-if="payingStripe" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
            </svg>
            <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M2 7a2 2 0 012-2h16a2 2 0 012 2v1H2V7zm0 4h20v6a2 2 0 01-2 2H4a2 2 0 01-2-2v-6zm3 3a1 1 0 000 2h3a1 1 0 000-2H5z"/>
            </svg>
            {{ payingStripe ? 'Redirecting…' : 'Pay with Card — $' + invoice.amount_due }}
          </button>

          <!-- PayPal -->
          <button
            @click="payPayPal(invoice.id)"
            :disabled="payingStripe || payingPayPal"
            class="flex items-center gap-2 bg-[#003087] hover:bg-[#002069] disabled:opacity-60 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors"
          >
            <svg v-if="payingPayPal" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
            </svg>
            <!-- PayPal wordmark-style text when not spinning -->
            <span v-else class="font-bold tracking-tight">Pay<span class="text-[#009cde]">Pal</span></span>
            {{ payingPayPal ? 'Redirecting…' : '— $' + invoice.amount_due }}
          </button>

        </div>
      </div>

      <!-- Payment history -->
      <div v-if="invoice.payments?.length" class="mt-6 pt-5 border-t border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Payment History</h2>
        <ul class="space-y-2 text-sm">
          <li v-for="p in invoice.payments" :key="p.id" class="flex justify-between text-gray-600">
            <span class="capitalize">{{ p.gateway }} — {{ p.status }}</span>
            <span>
              ${{ p.amount }}
              <span v-if="p.paid_at" class="text-gray-400 text-xs ml-2">
                {{ new Date(p.paid_at).toLocaleDateString() }}
              </span>
            </span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>
