<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  clients:  Array,
  taxRates: Array,
})

const today = new Date().toISOString().split('T')[0]

const form = useForm({
  user_id:   '',
  date:      today,
  due_date:  '',
  status:    'unpaid',
  notes:     '',
  tax_rate:  '0',
  items:     [{ description: '', quantity: 1, unit_price: '' }],
})

// Selected client info
const selectedClient = computed(() => props.clients.find(c => c.id == form.user_id))

// Auto-suggest tax rate based on client
watch(() => form.user_id, (uid) => {
  const client = props.clients.find(c => c.id == uid)
  if (!client || client.tax_exempt) { form.tax_rate = '0'; return }
  const match = props.taxRates.find(r =>
    r.country === client.country && (r.state === client.state || !r.state)
  ) ?? props.taxRates.find(r => r.is_default)
  form.tax_rate = match ? String(match.rate) : '0'
})

function addItem() {
  form.items.push({ description: '', quantity: 1, unit_price: '' })
}

function removeItem(i) {
  if (form.items.length > 1) form.items.splice(i, 1)
}

const subtotal = computed(() =>
  form.items.reduce((sum, i) => sum + (parseFloat(i.quantity || 0) * parseFloat(i.unit_price || 0)), 0)
)

const taxAmount = computed(() => subtotal.value * (parseFloat(form.tax_rate || 0) / 100))
const total     = computed(() => subtotal.value + taxAmount.value)

function submit() {
  form.post(route('admin.invoices.store'))
}
</script>

<template>
  <div class="max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.invoices.index')" class="text-sm text-slate-400 hover:text-slate-600">← Invoices</Link>
      <span class="text-slate-200">/</span>
      <h1 class="text-xl font-bold text-slate-800">New Invoice</h1>
    </div>

    <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-6 shadow-sm">
      <form @submit.prevent="submit" class="space-y-6">

        <!-- Client + Status -->
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2 md:col-span-1">
            <label class="block text-sm font-medium text-slate-700 mb-1">Client</label>
            <select v-model="form.user_id" required
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Select client…</option>
              <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }} ({{ c.email }})</option>
            </select>
            <p v-if="selectedClient" class="mt-1 text-xs text-slate-400">
              Credit balance: <strong>${{ parseFloat(selectedClient.credit_balance ?? 0).toFixed(2) }}</strong>
              <span v-if="selectedClient.tax_exempt" class="ml-2 text-green-600 font-medium">Tax Exempt</span>
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
            <select v-model="form.status"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="unpaid">Unpaid (send to client)</option>
              <option value="draft">Draft (internal only)</option>
            </select>
          </div>
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Invoice Date</label>
            <input v-model="form.date" type="date"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Due Date</label>
            <input v-model="form.due_date" type="date" required
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <!-- Line Items -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Line Items</label>
          <div class="rounded-xl border border-slate-200 overflow-hidden">
            <!-- Header -->
            <div class="grid grid-cols-12 gap-2 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-400 uppercase">
              <div class="col-span-6">Description</div>
              <div class="col-span-2 text-center">Qty</div>
              <div class="col-span-3 text-right">Unit Price</div>
              <div class="col-span-1"></div>
            </div>
            <!-- Items -->
            <div v-for="(item, i) in form.items" :key="i"
              class="grid grid-cols-12 gap-2 px-3 py-2 border-t border-slate-100 items-center">
              <div class="col-span-6">
                <input v-model="item.description" placeholder="Description" required
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <div class="col-span-2">
                <input v-model="item.quantity" type="number" min="0.01" step="0.01" required
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <div class="col-span-3">
                <input v-model="item.unit_price" type="number" step="0.01" min="0" placeholder="0.00" required
                  class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm text-right focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <div class="col-span-1 text-center">
                <button type="button" @click="removeItem(i)" :disabled="form.items.length === 1"
                  class="text-slate-300 hover:text-red-400 disabled:opacity-30 transition-colors">✕</button>
              </div>
            </div>
          </div>
          <button type="button" @click="addItem"
            class="mt-2 text-sm text-blue-600 hover:underline">+ Add line item</button>
        </div>

        <!-- Tax -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Tax Rate (%)</label>
            <div class="flex gap-2">
              <input v-model="form.tax_rate" type="number" step="0.01" min="0" max="100"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <select v-if="taxRates.length"
                @change="form.tax_rate = $event.target.value"
                class="border border-slate-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 text-slate-500">
                <option value="">Pick rate…</option>
                <option v-for="r in taxRates" :key="r.id" :value="r.rate">{{ r.name }} ({{ r.rate }}%)</option>
              </select>
            </div>
          </div>
          <div class="space-y-1 text-sm pt-6">
            <div class="flex justify-between text-slate-500">
              <span>Subtotal</span><span>${{ subtotal.toFixed(2) }}</span>
            </div>
            <div v-if="taxAmount > 0" class="flex justify-between text-slate-500">
              <span>Tax ({{ form.tax_rate }}%)</span><span>${{ taxAmount.toFixed(2) }}</span>
            </div>
            <div class="flex justify-between font-bold text-slate-800 text-base border-t border-slate-200 pt-1 mt-1">
              <span>Total</span><span>${{ total.toFixed(2) }}</span>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Internal Notes</label>
          <textarea v-model="form.notes" rows="2" placeholder="Optional notes (visible on invoice)…"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
          <Link :href="route('admin.invoices.index')" class="text-sm text-slate-500 px-4 py-2 hover:text-slate-700">Cancel</Link>
          <button type="submit" :disabled="form.processing"
            class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg shadow-sm transition-colors">
            {{ form.processing ? 'Creating…' : (form.status === 'draft' ? 'Save Draft' : 'Create Invoice') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
