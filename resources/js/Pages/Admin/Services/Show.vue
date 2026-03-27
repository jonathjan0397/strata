<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ service: Object })

function suspend()   { router.post(route('admin.services.suspend',   props.service.id)) }
function unsuspend() { router.post(route('admin.services.unsuspend', props.service.id)) }
function terminate() {
  if (confirm('Terminate this service? This cannot be undone.')) {
    router.post(route('admin.services.terminate', props.service.id))
  }
}
function approveCancellation() {
  if (confirm('Approve cancellation? The service will be marked cancelled.')) {
    router.post(route('admin.services.approve-cancellation', props.service.id))
  }
}
function rejectCancellation() {
  router.post(route('admin.services.reject-cancellation', props.service.id))
}

function fmt(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString()
}
</script>

<template>
  <div class="max-w-5xl">
    <!-- Breadcrumb + title -->
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.services.index')" class="text-sm text-gray-500 hover:text-gray-700">← Services</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ service.domain ?? service.product?.name ?? 'Service #' + service.id }}</h1>
      <StatusBadge :status="service.status" class="ml-1" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Service details -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-2 text-sm">
        <h2 class="font-semibold text-gray-900 mb-3">Service Details</h2>
        <div><span class="text-gray-500">Product:</span> {{ service.product?.name ?? '—' }}</div>
        <div><span class="text-gray-500">Domain:</span> {{ service.domain ?? '—' }}</div>
        <div><span class="text-gray-500">Billing cycle:</span> <span class="capitalize">{{ service.billing_cycle ?? '—' }}</span></div>
        <div><span class="text-gray-500">Amount:</span> ${{ service.amount }}</div>
        <div><span class="text-gray-500">Registered:</span> {{ fmt(service.registration_date) }}</div>
        <div><span class="text-gray-500">Next due:</span> {{ fmt(service.next_due_date) }}</div>
        <div v-if="service.termination_date"><span class="text-gray-500">Terminated:</span> {{ fmt(service.termination_date) }}</div>
      </div>

      <!-- Client info -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-2 text-sm">
        <h2 class="font-semibold text-gray-900 mb-3">Client</h2>
        <div>
          <Link :href="route('admin.clients.show', service.user?.id)" class="text-indigo-600 hover:underline font-medium">
            {{ service.user?.name }}
          </Link>
        </div>
        <div class="text-gray-500">{{ service.user?.email }}</div>
      </div>

      <!-- Actions -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm">
        <h2 class="font-semibold text-gray-900 mb-3">Actions</h2>

        <!-- Cancellation request alert -->
        <div v-if="service.status === 'cancellation_requested'"
            class="mb-3 p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800">
          <p class="font-semibold">Cancellation Requested</p>
          <p class="mt-1">{{ service.cancellation_reason }}</p>
          <p class="mt-1 text-amber-500">{{ new Date(service.cancellation_requested_at).toLocaleDateString() }}</p>
        </div>

        <div class="flex flex-col gap-2">
          <template v-if="service.status === 'cancellation_requested'">
            <button @click="approveCancellation"
              class="w-full px-3 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700"
            >Approve Cancellation</button>
            <button @click="rejectCancellation"
              class="w-full px-3 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700"
            >Reject — Keep Active</button>
          </template>
          <template v-else>
            <button
              v-if="service.status === 'active'"
              @click="suspend"
              class="w-full px-3 py-2 rounded-lg bg-yellow-500 text-white text-sm font-medium hover:bg-yellow-600"
            >Suspend Service</button>
            <button
              v-if="service.status === 'suspended'"
              @click="unsuspend"
              class="w-full px-3 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700"
            >Reactivate Service</button>
            <button
              v-if="!['terminated','cancelled'].includes(service.status)"
              @click="terminate"
              class="w-full px-3 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700"
            >Terminate Service</button>
          </template>
        </div>
      </div>

      <!-- Provisioning -->
      <div v-if="service.username || service.server_hostname" class="bg-white rounded-xl border border-gray-200 p-5 space-y-2 text-sm">
        <h2 class="font-semibold text-gray-900 mb-3">Provisioning</h2>
        <div v-if="service.username"><span class="text-gray-500">Username:</span> {{ service.username }}</div>
        <div v-if="service.server_hostname"><span class="text-gray-500">Server:</span> {{ service.server_hostname }}<span v-if="service.server_port">:{{ service.server_port }}</span></div>
      </div>

      <!-- Notes -->
      <div v-if="service.notes" class="bg-white rounded-xl border border-gray-200 p-5 text-sm">
        <h2 class="font-semibold text-gray-900 mb-3">Notes</h2>
        <p class="text-gray-600 whitespace-pre-wrap">{{ service.notes }}</p>
      </div>

      <!-- Invoice history -->
      <div class="lg:col-span-3 bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-3 text-sm">Invoice History</h2>
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-100">
              <th class="pb-2">Invoice</th>
              <th class="pb-2">Description</th>
              <th class="pb-2 text-right">Amount</th>
              <th class="pb-2 text-right">Invoice Date</th>
              <th class="pb-2 text-right">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="item in service.invoice_items" :key="item.id">
              <td class="py-2">
                <Link :href="route('admin.invoices.show', item.invoice?.id)" class="text-indigo-600 hover:underline">
                  #{{ item.invoice?.id }}
                </Link>
              </td>
              <td class="py-2 text-gray-600">{{ item.description }}</td>
              <td class="py-2 text-right font-medium">${{ item.amount }}</td>
              <td class="py-2 text-right text-gray-500">{{ fmt(item.invoice?.date) }}</td>
              <td class="py-2 text-right"><StatusBadge :status="item.invoice?.status" /></td>
            </tr>
            <tr v-if="!service.invoice_items?.length">
              <td colspan="5" class="py-6 text-center text-gray-400">No invoice history.</td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</template>
