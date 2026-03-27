<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ services: Array })
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-gray-900 mb-6">My Services</h1>

    <div class="space-y-3">
      <div v-for="s in services" :key="s.id" class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
        <div class="flex-1 min-w-0">
          <p class="font-medium text-gray-900">{{ s.domain ?? s.product?.name }}</p>
          <p class="text-sm text-gray-500 mt-0.5">{{ s.product?.name }} &nbsp;·&nbsp; ${{ s.amount }}/{{ s.billing_cycle?.replace(/_/g,' ') }}</p>
        </div>
        <div class="text-right shrink-0">
          <StatusBadge :status="s.status" />
          <p v-if="s.next_due_date" class="text-xs text-gray-400 mt-1">Due {{ s.next_due_date }}</p>
        </div>
        <Link :href="route('client.services.show', s.id)" class="text-sm text-indigo-600 hover:underline shrink-0">View</Link>
      </div>

      <div v-if="!services.length" class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-400">
        No services yet.
      </div>
    </div>
  </div>
</template>
