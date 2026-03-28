<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ addons: Array })

function destroy(id) {
  if (confirm('Delete this addon?')) {
    router.delete(route('admin.addons.destroy', id))
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Addons</h1>
      <Link :href="route('admin.addons.create')" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg">
        Add Addon
      </Link>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Billing Cycle</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Setup Fee</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Price</th>
            <th class="px-4 py-3 text-center font-medium text-gray-500">Active</th>
            <th class="px-4 py-3 text-center font-medium text-gray-500">Order</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="a in addons" :key="a.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900">{{ a.name }}</td>
            <td class="px-4 py-3 text-gray-500 capitalize">{{ a.billing_cycle?.replace(/_/g, ' ') }}</td>
            <td class="px-4 py-3 text-right text-gray-500">${{ a.setup_fee }}</td>
            <td class="px-4 py-3 text-right font-medium">${{ a.price }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="a.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="text-xs font-medium px-2 py-0.5 rounded-full">
                {{ a.is_active ? 'Yes' : 'No' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center text-gray-500">{{ a.sort_order }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('admin.addons.edit', a.id)" class="text-xs text-indigo-600 hover:underline mr-3">Edit</Link>
              <button class="text-xs text-red-500 hover:text-red-700" @click="destroy(a.id)">Delete</button>
            </td>
          </tr>
          <tr v-if="!addons.length">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400">No addons yet.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
