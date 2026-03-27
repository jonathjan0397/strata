<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  clients: Object,
  filters: Object,
})

const search = ref(props.filters?.search ?? '')

watch(search, (val) => {
  router.get(route('admin.clients.index'), { search: val }, { preserveState: true, replace: true })
})
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Clients</h1>
      <Link :href="route('admin.clients.create')" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg">
        Add Client
      </Link>
    </div>

    <input
      v-model="search"
      type="search"
      placeholder="Search by name or email…"
      class="mb-4 w-full max-w-sm border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
    />

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Email</th>
            <th class="px-4 py-3 text-center font-medium text-gray-500">Services</th>
            <th class="px-4 py-3 text-center font-medium text-gray-500">Invoices</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Joined</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="client in clients.data" :key="client.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">
              <Link :href="route('admin.clients.show', client.id)" class="text-indigo-600 hover:underline">{{ client.name }}</Link>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ client.email }}</td>
            <td class="px-4 py-3 text-center text-gray-600">{{ client.services_count }}</td>
            <td class="px-4 py-3 text-center text-gray-600">{{ client.invoices_count }}</td>
            <td class="px-4 py-3 text-right text-gray-400">{{ new Date(client.created_at).toLocaleDateString() }}</td>
          </tr>
          <tr v-if="!clients.data.length">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400">No clients found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
