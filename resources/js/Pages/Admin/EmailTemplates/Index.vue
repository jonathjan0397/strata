<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ templates: Array })
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Email Templates</h1>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Slug</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Subject</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="t in templates" :key="t.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ t.slug }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ t.name }}</td>
            <td class="px-4 py-3 text-gray-600 truncate max-w-xs">{{ t.subject }}</td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                :class="t.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                {{ t.active ? 'Active' : 'Disabled' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <Link :href="route('admin.email-templates.edit', t.id)" class="text-indigo-600 hover:underline text-xs">Edit</Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="mt-4 text-xs text-gray-400">
      Use <code class="bg-gray-100 px-1 rounded">&#123;&#123;variable&#125;&#125;</code> placeholders in subjects and body. Available variables are listed when editing each template.
    </p>
  </div>
</template>
