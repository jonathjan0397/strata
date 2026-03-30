<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    domain:        String,
    price:         Number,
    renewPrice:    Number,
    currency:      { type: String, default: 'USD' },
    creditBalance: { type: Number, default: 0 },
    prefill:       Object,
})

const form = useForm({
    domain:             props.domain,
    years:              1,
    apply_credit:       false,
    registrant_first:   props.prefill?.first  ?? '',
    registrant_last:    props.prefill?.last   ?? '',
    registrant_email:   props.prefill?.email  ?? '',
    registrant_phone:   '',
    registrant_address: '',
    registrant_city:    '',
    registrant_state:   '',
    registrant_zip:     '',
    registrant_country: 'US',
})

const subtotal = computed(() => Number((props.price * form.years).toFixed(2)))

const creditApplied = computed(() =>
    form.apply_credit ? Math.min(props.creditBalance, subtotal.value) : 0
)

const total = computed(() => Math.max(0, subtotal.value - creditApplied.value).toFixed(2))

function place() {
    form.post(route('client.domain-order.place'))
}
</script>

<template>
  <div class="max-w-3xl mx-auto">
    <div class="mb-6">
      <Link :href="route('client.domain-order.search')" class="text-xs text-gray-400 hover:text-gray-600">← Back to search</Link>
      <h1 class="mt-2 text-xl font-bold text-gray-900">Register {{ domain }}</h1>
    </div>

    <form @submit.prevent="place" class="space-y-6">

      <!-- Order summary -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Order Summary</h2>

        <div class="flex items-center justify-between text-sm mb-4">
          <span class="font-mono text-gray-900 font-semibold">{{ domain }}</span>
          <span class="text-gray-700">${{ Number(price).toFixed(2) }} {{ currency }}/yr</span>
        </div>

        <!-- Years selector -->
        <div class="flex items-center gap-3 mb-4">
          <label class="text-sm text-gray-600 shrink-0">Registration period</label>
          <select v-model="form.years"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option :value="1">1 year</option>
            <option :value="2">2 years</option>
            <option :value="3">3 years</option>
            <option :value="5">5 years</option>
          </select>
        </div>

        <div v-if="renewPrice" class="text-xs text-gray-400 mb-4">
          Renews at ${{ Number(renewPrice).toFixed(2) }} {{ currency }}/yr
        </div>

        <div class="border-t border-gray-100 pt-3 space-y-1 text-sm">
          <div class="flex justify-between text-gray-600">
            <span>Subtotal</span>
            <span>${{ subtotal.toFixed(2) }}</span>
          </div>

          <!-- Credit -->
          <div v-if="creditBalance > 0" class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-gray-600 cursor-pointer">
              <input type="checkbox" v-model="form.apply_credit" class="rounded text-indigo-600" />
              Apply credit (${{ Number(creditBalance).toFixed(2) }} available)
            </label>
            <span v-if="form.apply_credit" class="text-green-600">-${{ creditApplied.toFixed(2) }}</span>
          </div>

          <div class="flex justify-between font-semibold text-gray-900 pt-1 border-t border-gray-100">
            <span>Total Due</span>
            <span>${{ total }}</span>
          </div>
        </div>
      </div>

      <!-- Registrant contact -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-800 mb-4">Registrant Contact</h2>
        <p class="text-xs text-gray-500 mb-4">This information will be submitted to the domain registry for WHOIS records. You can update nameservers and privacy settings after registration.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">First Name *</label>
            <input v-model="form.registrant_first" type="text" required
              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <p v-if="form.errors.registrant_first" class="mt-0.5 text-xs text-red-500">{{ form.errors.registrant_first }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Last Name *</label>
            <input v-model="form.registrant_last" type="text" required
              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <p v-if="form.errors.registrant_last" class="mt-0.5 text-xs text-red-500">{{ form.errors.registrant_last }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Email *</label>
            <input v-model="form.registrant_email" type="email" required
              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <p v-if="form.errors.registrant_email" class="mt-0.5 text-xs text-red-500">{{ form.errors.registrant_email }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Phone *</label>
            <input v-model="form.registrant_phone" type="tel" required placeholder="+1.5551234567"
              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <p v-if="form.errors.registrant_phone" class="mt-0.5 text-xs text-red-500">{{ form.errors.registrant_phone }}</p>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Address *</label>
            <input v-model="form.registrant_address" type="text" required
              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <p v-if="form.errors.registrant_address" class="mt-0.5 text-xs text-red-500">{{ form.errors.registrant_address }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">City *</label>
            <input v-model="form.registrant_city" type="text" required
              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <p v-if="form.errors.registrant_city" class="mt-0.5 text-xs text-red-500">{{ form.errors.registrant_city }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">State / Province *</label>
            <input v-model="form.registrant_state" type="text" required
              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <p v-if="form.errors.registrant_state" class="mt-0.5 text-xs text-red-500">{{ form.errors.registrant_state }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Postal / ZIP *</label>
            <input v-model="form.registrant_zip" type="text" required
              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <p v-if="form.errors.registrant_zip" class="mt-0.5 text-xs text-red-500">{{ form.errors.registrant_zip }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Country (2-letter code) *</label>
            <input v-model="form.registrant_country" type="text" required maxlength="2" placeholder="US"
              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            <p v-if="form.errors.registrant_country" class="mt-0.5 text-xs text-red-500">{{ form.errors.registrant_country }}</p>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="flex items-center justify-between">
        <p class="text-xs text-gray-500">
          Payment is due via invoice. Your domain will be registered once the invoice is paid.
        </p>
        <button type="submit" :disabled="form.processing"
          class="px-6 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50 transition-colors">
          {{ form.processing ? 'Placing order…' : 'Place Order — $' + total }}
        </button>
      </div>
    </form>
  </div>
</template>
