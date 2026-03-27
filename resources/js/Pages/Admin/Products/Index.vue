<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ products: Array })

function destroy(id) {
  if (confirm('Delete this product?')) {
    router.delete(route('admin.products.destroy', id))
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Products</h1>
      <Link :href="route('admin.products.create')" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg">
        Add Product
      </Link>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Type</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Cycle</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Price</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Services</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="p in products" :key="p.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900">{{ p.name }}</td>
            <td class="px-4 py-3 text-gray-500 capitalize">{{ p.type }}</td>
            <td class="px-4 py-3 text-gray-500 capitalize">{{ p.billing_cycle?.replace(/_/g,' ') }}</td>
            <td class="px-4 py-3 text-right font-medium">${{ p.price }}</td>
            <td class="px-4 py-3 text-right text-gray-500">{{ p.services_count }}</td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('admin.products.edit', p.id)" class="text-xs text-indigo-600 hover:underline mr-3">Edit</Link>
              <button class="text-xs text-red-500 hover:text-red-700" @click="destroy(p.id)">Delete</button>
            </td>
          </tr>
          <tr v-if="!products.length">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">No products yet.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
