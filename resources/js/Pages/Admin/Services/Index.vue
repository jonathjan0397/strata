<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ services: Object, filters: Object })

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

watch([search, status], ([s, st]) => {
  router.get(route('admin.services.index'), { search: s, status: st }, { preserveState: true, replace: true })
})
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-gray-900 mb-6">Services</h1>

    <div class="flex gap-3 mb-4">
      <input v-model="search" type="search" placeholder="Search…" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-60 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      <select v-model="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Statuses</option>
        <option v-for="s in ['pending','active','suspended','cancellation_requested','cancelled','terminated']" :key="s" :value="s">{{ s.replace(/_/g,' ') }}</option>
      </select>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Client</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Product</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Domain</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Due</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="s in services.data" :key="s.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <Link :href="route('admin.clients.show', s.user_id)" class="text-indigo-600 hover:underline">{{ s.user?.name }}</Link>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ s.product?.name }}</td>
            <td class="px-4 py-3">
              <Link :href="route('admin.services.show', s.id)" class="text-indigo-600 hover:underline">{{ s.domain ?? '—' }}</Link>
            </td>
            <td class="px-4 py-3 text-right text-gray-500">{{ s.next_due_date ?? '—' }}</td>
            <td class="px-4 py-3 text-right"><StatusBadge :status="s.status" /></td>
          </tr>
          <tr v-if="!services.data.length">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400">No services found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
