<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  code:     Object,  // null = creating
  products: Array,
})

const isEdit = !!props.code

const form = useForm({
  code:             props.code?.code             ?? '',
  type:             props.code?.type             ?? 'percent',
  value:            props.code?.value            ?? '',
  product_id:       props.code?.product_id       ?? '',
  max_uses:         props.code?.max_uses         ?? '',
  applies_once:     props.code?.applies_once     ?? false,
  new_clients_only: props.code?.new_clients_only ?? false,
  recurring_cycles: props.code?.recurring_cycles ?? '',
  is_active:        props.code?.is_active        ?? true,
  starts_at:        props.code?.starts_at  ? props.code.starts_at.slice(0, 10)  : '',
  expires_at:       props.code?.expires_at ? props.code.expires_at.slice(0, 10) : '',
})

const isFreeSetup = computed(() => form.type === 'free_setup')

function submit() {
  if (isEdit) {
    form.patch(route('admin.promo-codes.update', props.code.id))
  } else {
    form.post(route('admin.promo-codes.store'))
  }
}
</script>

<template>
  <div class="max-w-xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.promo-codes.index')" class="text-sm text-gray-500 hover:text-gray-700">← Promo Codes</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ isEdit ? 'Edit Promo Code' : 'New Promo Code' }}</h1>
    </div>

    <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">

      <!-- Code -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
        <input v-model="form.code" type="text" placeholder="SUMMER20"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <p v-if="form.errors.code" class="text-red-500 text-xs mt-1">{{ form.errors.code }}</p>
      </div>

      <!-- Type + Value -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
          <select v-model="form.type"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="percent">Percent (%)</option>
            <option value="fixed">Fixed ($)</option>
            <option value="free_setup">Free Setup Fee</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Value
            <span class="text-gray-400 font-normal">
              {{ isFreeSetup ? '(not needed)' : (form.type === 'percent' ? '(%)' : '($)') }}
            </span>
          </label>
          <input v-model="form.value" type="number" step="0.01" min="0"
            :disabled="isFreeSetup"
            :placeholder="isFreeSetup ? 'N/A' : (form.type === 'percent' ? '20' : '10.00')"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-gray-50 disabled:text-gray-400" />
          <p v-if="form.errors.value" class="text-red-500 text-xs mt-1">{{ form.errors.value }}</p>
        </div>
      </div>

      <!-- Product restriction -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Product Restriction <span class="text-gray-400 font-normal">(leave blank for all products)</span></label>
        <select v-model="form.product_id"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">All Products</option>
          <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>

      <!-- Usage limits -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Max Total Uses <span class="text-gray-400 font-normal">(blank = unlimited)</span></label>
          <input v-model="form.max_uses" type="number" min="1" placeholder="Unlimited"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Recurring Cycles
            <span class="text-gray-400 font-normal text-xs block">(blank=first only, -1=always)</span>
          </label>
          <input v-model="form.recurring_cycles" type="number" min="-1" placeholder="First invoice only"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          <p v-if="form.errors.recurring_cycles" class="text-red-500 text-xs mt-1">{{ form.errors.recurring_cycles }}</p>
        </div>
      </div>

      <!-- Date range -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Valid From <span class="text-gray-400 font-normal">(blank = immediately)</span></label>
          <input v-model="form.starts_at" type="date"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Expires At</label>
          <input v-model="form.expires_at" type="date"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
      </div>

      <!-- Flags -->
      <div class="space-y-2">
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="form.applies_once" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
          <span class="text-sm text-gray-700">One-time use per client</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="form.new_clients_only" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
          <span class="text-sm text-gray-700">New clients only <span class="text-gray-400 font-normal text-xs">(clients with no active services)</span></span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
          <span class="text-sm text-gray-700">Active</span>
        </label>
      </div>

      <div class="flex gap-3 pt-2">
        <Link :href="route('admin.promo-codes.index')"
          class="flex-1 text-center border border-gray-300 text-gray-600 hover:bg-gray-50 rounded-lg py-2 text-sm transition-colors">
          Cancel
        </Link>
        <button type="submit" :disabled="form.processing"
          class="flex-1 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-60 text-white font-medium rounded-lg py-2 text-sm transition-colors">
          {{ isEdit ? 'Save Changes' : 'Create Code' }}
        </button>
      </div>
    </form>
  </div>
</template>
