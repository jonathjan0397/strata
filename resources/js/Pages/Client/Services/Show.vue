<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    service:            Object,
    upgradableProducts: { type: Array, default: () => [] },
})

const showCancelForm  = ref(false)
const showUpgradeForm = ref(false)
const cancelForm = useForm({
    reason:            '',
    cancellation_type: 'end_of_period',
})
const upgradeForm = useForm({ product_id: '' })

function submitCancellation() {
    cancelForm.post(route('client.services.cancel', props.service.id), {
        onSuccess: () => { showCancelForm.value = false },
    })
}

function submitUpgrade() {
    upgradeForm.post(route('client.services.upgrade', props.service.id), {
        onSuccess: () => {
            showUpgradeForm.value = false
            upgradeForm.reset()
        },
    })
}

const cancelRequested   = props.service.status === 'cancellation_requested'
const scheduledCancel   = !!props.service.scheduled_cancel_at
const isClosed          = ['cancelled', 'terminated'].includes(props.service.status)
const isInTrial         = props.service.trial_ends_at && new Date(props.service.trial_ends_at) > new Date()

const selectedProduct = computed(() =>
    props.upgradableProducts.find(p => p.id === upgradeForm.product_id)
)
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('client.services.index')" class="text-sm text-gray-500 hover:text-gray-700">← Services</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ service.domain ?? service.product?.name }}</h1>
      <StatusBadge :status="service.status" />
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-2 text-sm mb-4">
      <div class="grid grid-cols-2 gap-y-2">
        <span class="text-gray-500">Product</span>       <span>{{ service.product?.name }}</span>
        <span class="text-gray-500">Domain</span>        <span>{{ service.domain ?? '—' }}</span>
        <span class="text-gray-500">Amount</span>        <span>${{ service.amount }} / {{ service.billing_cycle?.replace(/_/g,' ') }}</span>
        <span class="text-gray-500">Registration</span>  <span>{{ service.registration_date ?? '—' }}</span>
        <template v-if="isInTrial">
          <span class="text-gray-500">Trial Ends</span>
          <span class="text-indigo-700 font-medium">{{ new Date(service.trial_ends_at).toLocaleDateString() }}</span>
        </template>
        <template v-else>
          <span class="text-gray-500">Next Due</span>    <span>{{ service.next_due_date ?? '—' }}</span>
        </template>
        <template v-if="service.server_hostname">
          <span class="text-gray-500">Server</span>      <span class="font-mono">{{ service.server_hostname }}</span>
        </template>
        <template v-if="service.username">
          <span class="text-gray-500">Username</span>    <span class="font-mono">{{ service.username }}</span>
        </template>
      </div>
    </div>

    <!-- Active trial notice -->
    <div v-if="isInTrial" class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 text-sm text-indigo-800 mb-4">
      <p class="font-medium">Free Trial Active</p>
      <p class="text-indigo-700 mt-1">
        Your trial ends on <strong>{{ new Date(service.trial_ends_at).toLocaleDateString() }}</strong>.
        Payment will be required to continue service beyond that date.
      </p>
    </div>

    <!-- Scheduled end-of-period cancellation notice -->
    <div v-else-if="scheduledCancel" class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800 mb-4">
      <p class="font-medium">Cancellation scheduled</p>
      <p class="text-amber-700 mt-1">
        This service will be cancelled on
        <strong>{{ new Date(service.scheduled_cancel_at).toLocaleDateString() }}</strong>
        at the end of the current billing period. You may continue using it until then.
      </p>
    </div>

    <!-- Cancellation requested notice -->
    <div v-else-if="cancelRequested" class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800 mb-4">
      <p class="font-medium">Cancellation requested</p>
      <p class="text-amber-700 mt-1">{{ service.cancellation_reason }}</p>
      <p class="text-xs text-amber-600 mt-1">
        Type: <strong class="capitalize">{{ service.cancellation_type?.replace('_', ' ') ?? 'immediate' }}</strong> —
        submitted {{ new Date(service.cancellation_requested_at).toLocaleDateString() }}
      </p>
    </div>

    <!-- Upgrade / Downgrade plan -->
    <div v-if="service.status === 'active' && upgradableProducts.length && !cancelRequested && !scheduledCancel"
        class="bg-white rounded-xl border border-gray-200 p-5 text-sm mb-4">
      <h2 class="font-semibold text-gray-900 mb-3">Change Plan</h2>

      <div v-if="!showUpgradeForm">
        <p class="text-gray-500 mb-3">Switch to a different plan within the same product category. A prorated invoice or credit will be applied automatically.</p>
        <button @click="showUpgradeForm = true"
            class="px-4 py-2 text-sm text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50">
          View Available Plans
        </button>
      </div>

      <div v-else class="space-y-4">
        <div class="space-y-2">
          <label v-for="p in upgradableProducts" :key="p.id"
            class="flex items-center justify-between gap-3 cursor-pointer p-3 rounded-lg border transition-colors"
            :class="upgradeForm.product_id === p.id
              ? (p.price > service.amount ? 'border-indigo-400 bg-indigo-50' : 'border-green-400 bg-green-50')
              : 'border-gray-200 hover:border-gray-300'">
            <div class="flex items-center gap-3">
              <input v-model="upgradeForm.product_id" type="radio" :value="p.id" class="text-indigo-600" />
              <div>
                <p class="font-medium text-gray-800">{{ p.name }}</p>
                <p v-if="p.short_description" class="text-xs text-gray-500 mt-0.5">{{ p.short_description }}</p>
              </div>
            </div>
            <div class="text-right shrink-0">
              <p class="font-semibold text-gray-900">${{ p.price }}</p>
              <p class="text-xs text-gray-400 capitalize">{{ p.billing_cycle?.replace(/_/g, ' ') }}</p>
              <p v-if="p.price > service.amount" class="text-xs text-indigo-600 mt-0.5">Upgrade</p>
              <p v-else class="text-xs text-green-600 mt-0.5">Downgrade</p>
            </div>
          </label>
        </div>

        <p v-if="upgradeForm.errors.product_id" class="text-red-500 text-xs">{{ upgradeForm.errors.product_id }}</p>

        <div class="flex gap-3">
          <button type="button" @click="showUpgradeForm = false; upgradeForm.reset()"
            class="text-sm text-gray-500 px-4 py-2">Nevermind</button>
          <button type="button" @click="submitUpgrade"
            :disabled="!upgradeForm.product_id || upgradeForm.processing"
            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
            {{ upgradeForm.processing ? 'Processing…' : 'Confirm Plan Change' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Cancel service -->
    <div v-if="!isClosed && !cancelRequested && !scheduledCancel" class="bg-white rounded-xl border border-gray-200 p-5 text-sm">
      <h2 class="font-semibold text-gray-900 mb-3">Cancel Service</h2>
      <div v-if="!showCancelForm">
        <p class="text-gray-500 mb-3">To cancel this service, click below. Our team will review your request and process it.</p>
        <button @click="showCancelForm = true"
            class="px-4 py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
            Request Cancellation
        </button>
      </div>
      <div v-else class="space-y-4">
        <!-- Cancellation type -->
        <div>
          <p class="text-sm font-medium text-gray-700 mb-2">When would you like to cancel?</p>
          <div class="space-y-2">
            <label class="flex items-start gap-3 cursor-pointer p-3 rounded-lg border border-gray-200 hover:border-indigo-300 transition-colors"
                :class="{ 'border-indigo-400 bg-indigo-50': cancelForm.cancellation_type === 'end_of_period' }">
              <input v-model="cancelForm.cancellation_type" type="radio" value="end_of_period" class="mt-0.5 text-indigo-600" />
              <div>
                <p class="font-medium text-gray-800">End of billing period</p>
                <p class="text-xs text-gray-500 mt-0.5">
                  Keep access until {{ service.next_due_date ?? 'the end of the current period' }}, then cancel.
                </p>
              </div>
            </label>
            <label class="flex items-start gap-3 cursor-pointer p-3 rounded-lg border border-gray-200 hover:border-red-300 transition-colors"
                :class="{ 'border-red-400 bg-red-50': cancelForm.cancellation_type === 'immediate' }">
              <input v-model="cancelForm.cancellation_type" type="radio" value="immediate" class="mt-0.5 text-red-600" />
              <div>
                <p class="font-medium text-gray-800">Immediately</p>
                <p class="text-xs text-gray-500 mt-0.5">Cancel as soon as our team processes the request.</p>
              </div>
            </label>
          </div>
          <p v-if="cancelForm.errors.cancellation_type" class="text-red-500 text-xs mt-1">{{ cancelForm.errors.cancellation_type }}</p>
        </div>

        <!-- Reason -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Reason for cancellation</label>
          <textarea v-model="cancelForm.reason" rows="4" required placeholder="Please let us know why you'd like to cancel…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 resize-none"
              :class="{ 'border-red-400': cancelForm.errors.reason }" />
          <p v-if="cancelForm.errors.reason" class="text-red-500 text-xs mt-1">{{ cancelForm.errors.reason }}</p>
        </div>

        <div class="flex gap-3">
          <button type="button" @click="showCancelForm = false" class="text-sm text-gray-500 px-4 py-2">Nevermind</button>
          <button type="button" @click="submitCancellation" :disabled="cancelForm.processing"
              class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 disabled:opacity-50">
              Submit Request
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
