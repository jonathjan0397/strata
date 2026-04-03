<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ announcements: Object })

function fmtDate(val) {
  return new Date(val).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
}
</script>

<template>
  <div class="max-w-3xl">
    <h1 class="text-xl font-bold text-gray-900 mb-6">Announcements</h1>

    <div v-if="announcements.data.length" class="space-y-5">
      <article
        v-for="a in announcements.data"
        :key="a.id"
        class="bg-white rounded-xl border border-gray-200 p-6"
      >
        <h2 class="text-base font-semibold text-gray-900 mb-1">{{ a.title }}</h2>
        <p class="text-xs text-gray-400 mb-4">{{ fmtDate(a.published_at) }}</p>
        <div class="prose prose-sm max-w-none" v-html="a.body" />
      </article>
    </div>

    <div v-else class="text-center py-16 text-gray-400">
      No announcements at this time.
    </div>

    <!-- Pagination -->
    <div v-if="announcements.last_page > 1" class="flex gap-1 mt-6">
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
