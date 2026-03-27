<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ products: Array })

const cycleLabel = {
  monthly: '/mo', quarterly: '/qtr', semi_annual: '/6mo',
  annual: '/yr', biennial: '/2yr', triennial: '/3yr', one_time: ' one-time',
}

const typeColor = {
  shared:    'bg-blue-100 text-blue-700',
  reseller:  'bg-purple-100 text-purple-700',
  vps:       'bg-orange-100 text-orange-700',
  dedicated: 'bg-red-100 text-red-700',
  domain:    'bg-green-100 text-green-700',
  ssl:       'bg-teal-100 text-teal-700',
  other:     'bg-gray-100 text-gray-600',
}
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-gray-900 mb-6">Order a Service</h1>

    <div v-if="products.length" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
      <div
        v-for="p in products"
        :key="p.id"
        class="bg-white rounded-xl border border-gray-200 p-6 flex flex-col"
      >
        <div class="flex items-start justify-between mb-3">
          <h2 class="font-semibold text-gray-900 text-base">{{ p.name }}</h2>
          <span class="text-xs font-medium px-2 py-0.5 rounded-full capitalize" :class="typeColor[p.type] ?? typeColor.other">
            {{ p.type }}
          </span>
        </div>

        <p v-if="p.description" class="text-sm text-gray-500 mb-4 flex-1">{{ p.description }}</p>
        <div class="flex-1" v-else />

        <div class="mt-auto">
          <div class="mb-4">
            <span class="text-2xl font-bold text-gray-900">${{ p.price }}</span>
            <span class="text-sm text-gray-500">{{ cycleLabel[p.billing_cycle] ?? '' }}</span>
            <div v-if="Number(p.setup_fee) > 0" class="text-xs text-gray-400 mt-0.5">${{ p.setup_fee }} setup fee</div>
          </div>

          <Link
            :href="route('client.order.checkout', { product_id: p.id, billing_cycle: p.billing_cycle })"
            class="block w-full text-center bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors"
          >
            Order Now
          </Link>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-16 text-gray-400">
      No products are currently available.
    </div>
  </div>
</template>
