<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import Pagination from '@/Components/Pagination.vue'

defineOptions({ layout: AppLayout })

defineProps({ affiliates: Object })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Affiliates</h1>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Affiliate</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Code</th>
            <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Referrals</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Balance</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Total Earned</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="a in affiliates.data" :key="a.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900">{{ a.user?.name }}</div>
              <div class="text-xs text-gray-500">{{ a.user?.email }}</div>
            </td>
            <td class="px-4 py-3 font-mono text-sm">{{ a.code }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="{
                'bg-green-100 text-green-700': a.status === 'active',
                'bg-yellow-100 text-yellow-700': a.status === 'pending',
                'bg-gray-100 text-gray-500': a.status === 'inactive',
              }" class="text-xs font-medium px-2 py-0.5 rounded-full capitalize">{{ a.status }}</span>
            </td>
            <td class="px-4 py-3 text-right text-gray-600">{{ a.referrals_count }}</td>
            <td class="px-4 py-3 text-right font-medium">${{ a.balance }}</td>
            <td class="px-4 py-3 text-right text-gray-500">${{ a.total_earned }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('admin.affiliates.show', a.id)" class="text-xs text-indigo-600 hover:underline">View</Link>
            </td>
          </tr>
          <tr v-if="!affiliates.data?.length">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400">No affiliates yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Pagination :links="affiliates.links" class="mt-4" />
  </div>
</template>
