<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  quote:   { type: Object, default: null },
  clients: Array,
})

const isEdit = !!props.quote

const form = useForm({
  user_id:        props.quote?.user_id        ?? '',
  valid_until:    props.quote?.valid_until    ? props.quote.valid_until.slice(0, 10) : '',
  tax_rate:       props.quote?.tax_rate       ?? 0,
  client_message: props.quote?.client_message ?? '',
  notes:          props.quote?.notes          ?? '',
  items: props.quote?.items?.length
    ? props.quote.items.map(i => ({
        description: i.description,
        quantity:    i.quantity,
        unit_price:  i.unit_price,
      }))
    : [{ description: '', quantity: 1, unit_price: '' }],
})

function addItem() {
  form.items.push({ description: '', quantity: 1, unit_price: '' })
}

function removeItem(i) {
  if (form.items.length > 1) form.items.splice(i, 1)
}

const subtotal = computed(() =>
  form.items.reduce((sum, i) => sum + (parseFloat(i.quantity) || 0) * (parseFloat(i.unit_price) || 0), 0)
)
const tax = computed(() => Math.round(subtotal.value * ((parseFloat(form.tax_rate) || 0) / 100) * 100) / 100)
const total = computed(() => subtotal.value + tax.value)

function submit() {
  if (isEdit) {
    form.patch(route('admin.quotes.update', props.quote.id))
  } else {
    form.post(route('admin.quotes.store'))
  }
}
</script>

<template>
  <div class="max-w-3xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.quotes.index')" class="text-sm text-gray-500 hover:text-gray-700">← Quotes</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ isEdit ? 'Edit Quote' : 'New Quote' }}</h1>
    </div>

    <form @submit.prevent="submit" class="space-y-5">

      <!-- Header -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
        <h2 class="font-semibold text-gray-900 text-sm">Quote Details</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
            <select v-model="form.user_id" required
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">— Select client —</option>
              <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }} ({{ c.email }})</option>
            </select>
            <p v-if="form.errors.user_id" class="text-red-500 text-xs mt-1">{{ form.errors.user_id }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Valid Until <span class="text-gray-400 font-normal">(blank = open)</span></label>
            <input v-model="form.valid_until" type="date"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Message to Client <span class="text-gray-400 font-normal">(shown in the quote email)</span></label>
          <textarea v-model="form.client_message" rows="3" placeholder="e.g. Thank you for your interest. Here's our proposal…"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Internal Notes <span class="text-gray-400 font-normal">(not shown to client)</span></label>
          <textarea v-model="form.notes" rows="2" placeholder="Internal reference…"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y" />
        </div>
      </div>

      <!-- Line items -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-3">
          <h2 class="font-semibold text-gray-900 text-sm">Line Items</h2>
          <button type="button" @click="addItem"
            class="text-xs text-indigo-600 border border-indigo-200 px-2.5 py-1 rounded-lg hover:bg-indigo-50">+ Add Item</button>
        </div>

        <div class="space-y-2">
          <div v-for="(item, i) in form.items" :key="i" class="grid grid-cols-12 gap-2 items-start">
            <div class="col-span-6">
              <input v-model="item.description" type="text" placeholder="Description" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              <p v-if="form.errors[`items.${i}.description`]" class="text-red-500 text-xs mt-0.5">
                {{ form.errors[`items.${i}.description`] }}
              </p>
            </div>
            <div class="col-span-2">
              <input v-model="item.quantity" type="number" step="0.01" min="0.01" placeholder="Qty" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div class="col-span-3">
              <input v-model="item.unit_price" type="number" step="0.01" min="0" placeholder="Unit price" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div class="col-span-1 flex items-center justify-end pt-2">
              <button type="button" @click="removeItem(i)" :disabled="form.items.length === 1"
                class="text-red-400 hover:text-red-600 disabled:opacity-30 text-sm">✕</button>
            </div>
          </div>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-100 space-y-1 text-sm">
          <div class="flex justify-between text-gray-600">
            <span>Subtotal</span><span>${{ subtotal.toFixed(2) }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-gray-600">Tax</span>
            <div class="flex items-center gap-1">
              <input v-model="form.tax_rate" type="number" step="0.01" min="0" max="100" placeholder="0"
                class="w-16 border border-gray-300 rounded px-2 py-1 text-sm text-center focus:outline-none focus:ring-1 focus:ring-indigo-500" />
              <span class="text-gray-500 text-xs">%</span>
            </div>
            <span class="ml-auto">${{ tax.toFixed(2) }}</span>
          </div>
          <div class="flex justify-between font-bold text-gray-900 pt-1">
            <span>Total</span><span>${{ total.toFixed(2) }}</span>
          </div>
        </div>
      </div>

      <div class="flex gap-3 justify-end">
        <Link :href="route('admin.quotes.index')"
          class="text-sm text-gray-500 px-4 py-2 hover:text-gray-700">Cancel</Link>
        <button type="submit" :disabled="form.processing"
          class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
          {{ form.processing ? 'Saving…' : (isEdit ? 'Save Changes' : 'Create Quote') }}
        </button>
      </div>

    </form>
  </div>
</template>
