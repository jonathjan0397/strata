<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ service: Object })

const showCancelForm = ref(false)
const cancelForm = useForm({ reason: '' })

function submitCancellation() {
    cancelForm.post(route('client.services.cancel', props.service.id), {
        onSuccess: () => { showCancelForm.value = false },
    })
}

const cancelRequested = props.service.status === 'cancellation_requested'
const isClosed = ['cancelled', 'terminated'].includes(props.service.status)
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
        <span class="text-gray-500">Next Due</span>      <span>{{ service.next_due_date ?? '—' }}</span>
        <template v-if="service.server_hostname">
          <span class="text-gray-500">Server</span>      <span class="font-mono">{{ service.server_hostname }}</span>
        </template>
        <template v-if="service.username">
          <span class="text-gray-500">Username</span>    <span class="font-mono">{{ service.username }}</span>
        </template>
      </div>
    </div>

    <!-- Cancellation requested notice -->
    <div v-if="cancelRequested" class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800 mb-4">
      <p class="font-medium">Cancellation requested</p>
      <p class="text-amber-700 mt-1">{{ service.cancellation_reason }}</p>
      <p class="text-xs text-amber-500 mt-1">Submitted {{ new Date(service.cancellation_requested_at).toLocaleDateString() }}</p>
    </div>

    <!-- Cancel service -->
    <div v-if="!isClosed && !cancelRequested" class="bg-white rounded-xl border border-gray-200 p-5 text-sm">
      <h2 class="font-semibold text-gray-900 mb-3">Cancel Service</h2>
      <div v-if="!showCancelForm">
        <p class="text-gray-500 mb-3">To cancel this service, click below. Our team will review your request and process the cancellation.</p>
        <button @click="showCancelForm = true"
            class="px-4 py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
            Request Cancellation
        </button>
      </div>
      <div v-else class="space-y-3">
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
