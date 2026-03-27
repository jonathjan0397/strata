<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ client: Object })
</script>

<template>
  <div class="max-w-5xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.clients.index')" class="text-sm text-gray-500 hover:text-gray-700">← Clients</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ client.name }}</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Client info -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-2 text-sm">
        <h2 class="font-semibold text-gray-900 mb-3">Account Details</h2>
        <div><span class="text-gray-500">Email:</span> {{ client.email }}</div>
        <div><span class="text-gray-500">Balance:</span> ${{ client.credit_balance }}</div>
        <div><span class="text-gray-500">Verified:</span> {{ client.email_verified_at ? 'Yes' : 'No' }}</div>
        <div><span class="text-gray-500">Joined:</span> {{ new Date(client.created_at).toLocaleDateString() }}</div>
      </div>

      <!-- Services -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-3">Services</h2>
        <ul class="divide-y divide-gray-100 text-sm">
          <li v-for="s in client.services" :key="s.id" class="py-2 flex justify-between">
            <span>{{ s.domain ?? s.product?.name }}</span>
            <StatusBadge :status="s.status" />
          </li>
          <li v-if="!client.services?.length" class="py-4 text-gray-400 text-center">No services.</li>
        </ul>
      </div>

      <!-- Invoices -->
      <div class="lg:col-span-3 bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-3">Recent Invoices</h2>
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-100">
              <th class="pb-2">ID</th>
              <th class="pb-2">Date</th>
              <th class="pb-2">Due</th>
              <th class="pb-2 text-right">Total</th>
              <th class="pb-2 text-right">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="inv in client.invoices" :key="inv.id">
              <td class="py-2">
                <Link :href="route('admin.invoices.show', inv.id)" class="text-indigo-600 hover:underline">#{{ inv.id }}</Link>
              </td>
              <td class="py-2 text-gray-500">{{ inv.date }}</td>
              <td class="py-2 text-gray-500">{{ inv.due_date }}</td>
              <td class="py-2 text-right font-medium">${{ inv.total }}</td>
              <td class="py-2 text-right"><StatusBadge :status="inv.status" /></td>
            </tr>
            <tr v-if="!client.invoices?.length">
              <td colspan="5" class="py-4 text-center text-gray-400">No invoices.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
