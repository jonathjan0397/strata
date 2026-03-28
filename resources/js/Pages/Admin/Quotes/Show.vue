<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ quote: Object })

function send() {
  if (confirm('Send this quote to the client?')) {
    router.post(route('admin.quotes.send', props.quote.id))
  }
}

function convert() {
  if (confirm('Convert this quote to an invoice? This cannot be undone.')) {
    router.post(route('admin.quotes.convert', props.quote.id))
  }
}

function destroy() {
  if (confirm('Delete this quote? This cannot be undone.')) {
    router.delete(route('admin.quotes.destroy', props.quote.id))
  }
}

function fmt(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString()
}
</script>

<template>
  <div class="max-w-4xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.quotes.index')" class="text-sm text-gray-500 hover:text-gray-700">← Quotes</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ quote.quote_number ?? 'Quote #' + quote.id }}</h1>
      <StatusBadge :status="quote.status" class="ml-1" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      <!-- Quote details -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm space-y-2">
        <h2 class="font-semibold text-gray-900 mb-3">Details</h2>
        <div><span class="text-gray-500">Client:</span>
          <Link :href="route('admin.clients.show', quote.user?.id)" class="text-indigo-600 hover:underline ml-1">
            {{ quote.user?.name }}
          </Link>
        </div>
        <div><span class="text-gray-500">Total:</span> <strong>${{ quote.total }}</strong></div>
        <div><span class="text-gray-500">Valid until:</span> {{ fmt(quote.valid_until) }}</div>
        <div v-if="quote.converted_invoice_id">
          <span class="text-gray-500">Invoice:</span>
          <Link :href="route('admin.invoices.show', quote.converted_invoice_id)" class="text-indigo-600 hover:underline ml-1">
            #{{ quote.converted_invoice_id }}
          </Link>
        </div>
        <div v-if="quote.notes" class="pt-2">
          <p class="text-gray-500 mb-1">Internal notes:</p>
          <p class="text-gray-700 whitespace-pre-wrap text-xs bg-gray-50 rounded p-2">{{ quote.notes }}</p>
        </div>
      </div>

      <!-- Actions -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm">
        <h2 class="font-semibold text-gray-900 mb-3">Actions</h2>
        <div class="flex flex-col gap-2">
          <Link v-if="!['accepted','declined'].includes(quote.status)"
            :href="route('admin.quotes.edit', quote.id)"
            class="w-full text-center px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm">
            Edit Quote
          </Link>
          <button v-if="!['accepted','declined'].includes(quote.status)"
            @click="send"
            class="w-full px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
            {{ quote.status === 'sent' ? 'Re-send to Client' : 'Send to Client' }}
          </button>
          <button v-if="quote.status === 'accepted' && !quote.converted_invoice_id"
            @click="convert"
            class="w-full px-3 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700">
            Convert to Invoice
          </button>
          <button v-if="quote.status !== 'accepted'"
            @click="destroy"
            class="w-full px-3 py-2 rounded-lg border border-red-200 text-red-600 text-sm hover:bg-red-50">
            Delete Quote
          </button>
        </div>
      </div>

      <!-- Client message -->
      <div v-if="quote.client_message" class="bg-white rounded-xl border border-gray-200 p-5 text-sm">
        <h2 class="font-semibold text-gray-900 mb-3">Client Message</h2>
        <p class="text-gray-700 whitespace-pre-wrap">{{ quote.client_message }}</p>
      </div>

      <!-- Line items -->
      <div class="lg:col-span-3 bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-3 text-sm">Line Items</h2>
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
      </div>

    </div>
  </div>
</template>
