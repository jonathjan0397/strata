<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ modules: Array })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Servers / Modules</h1>
      <Link :href="route('admin.modules.create')" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg">
        Add Server
      </Link>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Type</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Hostname</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Accounts</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="m in modules" :key="m.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900">{{ m.name }}</td>
            <td class="px-4 py-3 text-gray-500 uppercase text-xs">{{ m.type }}</td>
            <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ m.hostname }}:{{ m.port }}</td>
            <td class="px-4 py-3 text-right text-gray-600">
              {{ m.current_accounts }}{{ m.max_accounts ? ' / ' + m.max_accounts : '' }}
            </td>
            <td class="px-4 py-3 text-right">
              <span :class="m.active ? 'text-green-600' : 'text-gray-400'" class="text-xs font-medium">{{ m.active ? 'Active' : 'Inactive' }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('admin.modules.packages.sync', m.id)" class="text-xs text-violet-600 hover:underline mr-3">Sync Packages</Link>
              <Link :href="route('admin.modules.import', m.id)" class="text-xs text-teal-600 hover:underline mr-3">Import Accounts</Link>
              <Link :href="route('admin.modules.edit', m.id)" class="text-xs text-indigo-600 hover:underline mr-3">Edit</Link>
              <button class="text-xs text-red-500 hover:text-red-700"
                @click="router.delete(route('admin.modules.destroy', m.id))">Remove</button>
            </td>
          </tr>
          <tr v-if="!modules.length">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">No servers configured.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
