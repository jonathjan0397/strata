<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ clients: Array })

const form = useForm({
  user_id:  '',
  due_date: '',
  items:    [{ description: '', quantity: 1, unit_price: '' }],
})

function addItem() {
  form.items.push({ description: '', quantity: 1, unit_price: '' })
}

function removeItem(i) {
  form.items.splice(i, 1)
}

const total = computed(() =>
  form.items.reduce((sum, i) => sum + (i.quantity * parseFloat(i.unit_price || 0)), 0).toFixed(2)
)

function submit() {
  form.post(route('admin.invoices.store'))
}

import { computed } from 'vue'
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.invoices.index')" class="text-sm text-gray-500 hover:text-gray-700">← Invoices</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">New Invoice</h1>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <form @submit.prevent="submit" class="space-y-5">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
            <select v-model="form.user_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">Select client…</option>
              <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }} ({{ c.email }})</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
            <input v-model="form.due_date" type="date" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Line Items</label>
          <div v-for="(item, i) in form.items" :key="i" class="flex gap-2 mb-2">
            <input v-model="item.description" placeholder="Description" required class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <input v-model="item.quantity" type="number" min="1" class="w-16 border border-gray-300 rounded-lg px-3 py-2 text-sm text-center focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <input v-model="item.unit_price" type="number" step="0.01" min="0" placeholder="0.00" required class="w-24 border border-gray-300 rounded-lg px-3 py-2 text-sm text-right focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <button type="button" class="text-red-400 hover:text-red-600 px-1" @click="removeItem(i)" :disabled="form.items.length === 1">✕</button>
          </div>
          <button type="button" class="text-sm text-indigo-600 hover:underline mt-1" @click="addItem">+ Add line</button>
        </div>

        <div class="text-right text-sm font-semibold text-gray-900">
          Total: ${{ total }}
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('admin.invoices.index')" class="text-sm text-gray-500 px-4 py-2">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            Create Invoice
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
