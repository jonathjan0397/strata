<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  product:      Object,
  billingCycle: String,
  domain:       String,
})

const form = useForm({
  product_id:    props.product.id,
  billing_cycle: props.billingCycle,
  domain:        props.domain ?? '',
})

const cycleLabel = {
  monthly: 'Monthly', quarterly: 'Quarterly', semi_annual: 'Semi-Annual',
  annual: 'Annual', biennial: 'Biennial', triennial: 'Triennial', one_time: 'One-Time',
}

const needsDomain = ['shared', 'reseller', 'domain', 'vps', 'dedicated'].includes(props.product.type)
const isDomainProduct = props.product.type === 'domain'

const total = (Number(props.product.price) + Number(props.product.setup_fee)).toFixed(2)

// Domain availability check (only for domain-type products)
const availabilityStatus = ref(null) // null | 'checking' | 'available' | 'taken' | 'error'
let checkTimeout = null

watch(() => form.domain, (val) => {
    if (!isDomainProduct) return
    availabilityStatus.value = null
    clearTimeout(checkTimeout)
    if (!val || !val.includes('.')) return
    availabilityStatus.value = 'checking'
    checkTimeout = setTimeout(async () => {
        try {
            const res = await axios.get(route('client.domains.check'), { params: { domain: val } })
            availabilityStatus.value = res.data.available ? 'available' : 'taken'
        } catch {
            availabilityStatus.value = 'error'
        }
    }, 600)
})
</script>

<template>
  <div class="max-w-lg">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('client.order.catalog')" class="text-sm text-gray-500 hover:text-gray-700">← Products</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">Checkout</h1>
    </div>

    <!-- Order summary -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5">
      <h2 class="font-semibold text-gray-900 mb-3">Order Summary</h2>
      <div class="space-y-2 text-sm">
        <div class="flex justify-between">
          <span class="text-gray-600">{{ product.name }}</span>
          <span class="font-medium">${{ product.price }}</span>
        </div>
        <div v-if="Number(product.setup_fee) > 0" class="flex justify-between text-gray-500">
          <span>Setup Fee</span>
          <span>${{ product.setup_fee }}</span>
        </div>
        <div class="flex justify-between text-gray-500">
          <span>Billing</span>
          <span class="capitalize">{{ cycleLabel[billingCycle] ?? billingCycle }}</span>
        </div>
        <div class="border-t border-gray-100 pt-2 mt-2 flex justify-between font-bold text-gray-900">
          <span>Due Today</span>
          <span>${{ total }}</span>
        </div>
      </div>
    </div>

    <!-- Checkout form -->
    <form @submit.prevent="form.post(route('client.order.place'))" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
      <h2 class="font-semibold text-gray-900">Service Details</h2>

      <div v-if="needsDomain">
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Domain Name <span class="text-gray-400 font-normal">(e.g. example.com)</span>
        </label>
        <div class="relative">
          <input
            v-model="form.domain"
            type="text"
            placeholder="yourdomain.com"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 pr-28"
            :class="{
              'border-red-400': form.errors.domain || availabilityStatus === 'taken',
              'border-green-400': availabilityStatus === 'available',
            }"
          />
          <span v-if="availabilityStatus" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium">
            <span v-if="availabilityStatus === 'checking'" class="text-gray-400">Checking…</span>
            <span v-else-if="availabilityStatus === 'available'" class="text-green-600">Available ✓</span>
            <span v-else-if="availabilityStatus === 'taken'" class="text-red-500">Not available</span>
            <span v-else-if="availabilityStatus === 'error'" class="text-yellow-600">Check failed</span>
          </span>
        </div>
        <p v-if="form.errors.domain" class="text-red-500 text-xs mt-1">{{ form.errors.domain }}</p>
      </div>

      <div v-if="form.errors.product_id || form.errors.billing_cycle" class="text-red-500 text-xs">
        {{ form.errors.product_id || form.errors.billing_cycle }}
      </div>

      <!-- Terms notice -->
      <p class="text-xs text-gray-400">
        By placing this order you agree to our terms of service. An invoice will be generated and you will be able to pay via card or PayPal.
      </p>

      <button
        type="submit"
        :disabled="form.processing"
        class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-60 text-white font-medium py-2.5 rounded-lg transition-colors"
      >
        {{ form.processing ? 'Placing Order…' : 'Place Order — $' + total }}
      </button>
    </form>
  </div>
</template>
