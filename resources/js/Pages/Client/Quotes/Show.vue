<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ quote: Object })

function accept() {
  if (confirm('Accept this quote? Our team will contact you to finalise the order.')) {
    router.post(route('client.quotes.accept', props.quote.id))
  }
}

function decline() {
  if (confirm('Decline this quote?')) {
    router.post(route('client.quotes.decline', props.quote.id))
  }
}

function fmt(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString()
}

const canRespond = props.quote.status === 'sent'
const isExpired  = props.quote.valid_until && new Date(props.quote.valid_until) < new Date()
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('client.quotes.index')" class="text-sm text-gray-500 hover:text-gray-700">← Quotes</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ quote.quote_number ?? 'Quote #' + quote.id }}</h1>
      <StatusBadge :status="quote.status" />
    </div>

    <!-- Expired notice -->
    <div v-if="isExpired && canRespond" class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800 mb-4">
      <p class="font-medium">This quote expired on {{ fmt(quote.valid_until) }}</p>
      <p class="text-amber-700 mt-1">Please contact us if you are still interested.</p>
    </div>

    <!-- Client message -->
    <div v-if="quote.client_message" class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 text-sm text-indigo-900 mb-4">
      <p class="whitespace-pre-wrap">{{ quote.client_message }}</p>
    </div>

    <!-- Accepted / invoice link -->
    <div v-if="quote.status === 'accepted' && quote.converted_invoice_id"
        class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm text-green-800 mb-4">
      <p class="font-medium">Quote accepted — Invoice created</p>
      <Link :href="route('client.invoices.show', quote.converted_invoice_id)"
        class="text-green-700 underline mt-1 inline-block">
        View Invoice #{{ quote.converted_invoice_id }}
      </Link>
    </div>

    <!-- Line items -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-gray-500 border-b border-gray-100 text-xs">
            <th class="pb-2">Description</th>
            <th class="pb-2 text-right">Qty</th>
            <th class="pb-2 text-right">Unit Price</th>
            <th class="pb-2 text-right">Total</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="item in quote.items" :key="item.id">
            <td class="py-2 text-gray-700">{{ item.description }}</td>
            <td class="py-2 text-right text-gray-600">{{ item.quantity }}</td>
            <td class="py-2 text-right text-gray-600">${{ item.unit_price }}</td>
            <td class="py-2 text-right font-medium">${{ item.total }}</td>
          </tr>
        </tbody>
        <tfoot class="border-t border-gray-200">
          <tr v-if="quote.tax > 0">
            <td colspan="3" class="pt-2 text-right text-gray-500">Tax ({{ quote.tax_rate }}%)</td>
            <td class="pt-2 text-right text-gray-600">${{ quote.tax }}</td>
          </tr>
          <tr>
            <td colspan="3" class="pt-1 text-right font-bold text-gray-900">Total</td>
            <td class="pt-1 text-right font-bold text-gray-900">${{ quote.total }}</td>
          </tr>
        </tfoot>
      </table>
      <p class="text-xs text-gray-400 mt-3">Valid until: {{ fmt(quote.valid_until) }}</p>
    </div>

    <!-- Accept / Decline -->
    <div v-if="canRespond && !isExpired" class="flex gap-3">
      <button @click="accept"
        class="flex-1 px-4 py-2.5 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
        Accept Quote
      </button>
      <button @click="decline"
        class="px-4 py-2.5 border border-red-200 text-red-600 text-sm rounded-lg hover:bg-red-50">
        Decline
      </button>
    </div>
  </div>
</template>
