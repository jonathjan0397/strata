<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ announcements: Object })

function destroy(id) {
  if (confirm('Delete this announcement?')) {
    router.delete(route('admin.announcements.destroy', id))
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Announcements</h1>
      <Link :href="route('admin.announcements.create')" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
        + New Announcement
      </Link>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Title</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Published</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="a in announcements.data" :key="a.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900">{{ a.title }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                :class="a.published ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
              >{{ a.published ? 'Published' : 'Draft' }}</span>
            </td>
            <td class="px-4 py-3 text-gray-500">
              {{ a.published_at ? new Date(a.published_at).toLocaleDateString() : '—' }}
            </td>
            <td class="px-4 py-3 text-right space-x-3">
              <Link :href="route('admin.announcements.edit', a.id)" class="text-indigo-600 hover:underline text-xs">Edit</Link>
              <button @click="destroy(a.id)" class="text-red-500 hover:underline text-xs">Delete</button>
            </td>
          </tr>
          <tr v-if="!announcements.data.length">
            <td colspan="4" class="px-4 py-8 text-center text-gray-400">No announcements yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="announcements.last_page > 1" class="flex gap-1 mt-4">
      <Link
        v-for="link in announcements.links"
        :key="link.label"
        :href="link.url ?? '#'"
        v-html="link.label"
        class="px-3 py-1 rounded text-sm border"
        :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
      />
    </div>
  </div>
</template>
