<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { useForm, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ ticket: Object })

const form = useForm({ message: '' })

function send() {
  form.post(route('client.support.reply', props.ticket.id), {
    onSuccess: () => form.reset('message'),
  })
}
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-4">
      <Link :href="route('client.support.index')" class="text-sm text-gray-500 hover:text-gray-700">← Support</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900 truncate">{{ ticket.subject }}</h1>
      <StatusBadge :status="ticket.status" />
    </div>

    <div class="space-y-3 mb-5">
      <div v-for="reply in ticket.replies" :key="reply.id"
        :class="['rounded-xl p-4 text-sm', reply.is_staff ? 'bg-indigo-50 border border-indigo-100' : 'bg-white border border-gray-200']"
      >
        <div class="flex justify-between mb-1">
          <span class="font-medium" :class="reply.is_staff ? 'text-indigo-700' : 'text-gray-900'">
            {{ reply.is_staff ? 'Support Team' : 'You' }}
          </span>
          <span class="text-xs text-gray-400">{{ new Date(reply.created_at).toLocaleString() }}</span>
        </div>
        <p class="text-gray-700 whitespace-pre-wrap">{{ reply.message }}</p>
      </div>
    </div>

    <div v-if="ticket.status !== 'closed'" class="bg-white rounded-xl border border-gray-200 p-4">
      <form @submit.prevent="send">
        <textarea v-model="form.message" rows="4" placeholder="Write a reply…" required
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
        />
        <div class="flex justify-end mt-2">
          <button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            Send Reply
          </button>
        </div>
      </form>
    </div>
    <div v-else class="bg-gray-50 rounded-xl border border-gray-200 p-4 text-center text-sm text-gray-500">
      This ticket is closed.
    </div>
  </div>
</template>
