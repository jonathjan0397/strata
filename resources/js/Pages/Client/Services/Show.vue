<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ service: Object })
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('client.services.index')" class="text-sm text-gray-500 hover:text-gray-700">← Services</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ service.domain ?? service.product?.name }}</h1>
      <StatusBadge :status="service.status" />
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-2 text-sm">
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
  </div>
</template>
