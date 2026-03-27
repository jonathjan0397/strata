<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ announcement: Object })

const form = useForm({
  title:     props.announcement?.title     ?? '',
  body:      props.announcement?.body      ?? '',
  published: props.announcement?.published ?? false,
})

function submit() {
  if (props.announcement) {
    form.patch(route('admin.announcements.update', props.announcement.id))
  } else {
    form.post(route('admin.announcements.store'))
  }
}
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.announcements.index')" class="text-sm text-gray-500 hover:text-gray-700">← Announcements</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ announcement ? 'Edit Announcement' : 'New Announcement' }}</h1>
    </div>

    <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
        <input
          v-model="form.title"
          type="text"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          :class="{ 'border-red-400': form.errors.title }"
        />
        <p v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Body</label>
        <textarea
          v-model="form.body"
          rows="8"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
          :class="{ 'border-red-400': form.errors.body }"
        />
        <p v-if="form.errors.body" class="text-red-500 text-xs mt-1">{{ form.errors.body }}</p>
      </div>

      <div class="flex items-center gap-2">
        <input id="published" v-model="form.published" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
        <label for="published" class="text-sm text-gray-700">Publish immediately</label>
      </div>

      <div class="flex items-center gap-3 pt-2">
        <button
          type="submit"
          :disabled="form.processing"
          class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
        >{{ announcement ? 'Update' : 'Create' }}</button>
        <Link :href="route('admin.announcements.index')" class="text-sm text-gray-500 hover:text-gray-700">Cancel</Link>
      </div>
    </form>
  </div>
</template>
