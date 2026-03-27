<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { useForm, router, Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ ticket: Object, staff: Array })

const replyForm  = useForm({ message: '' })
const assignForm = useForm({ assigned_to: props.ticket.assigned_to ?? '' })

function sendReply() {
  replyForm.post(route('admin.support.reply', props.ticket.id), {
    onSuccess: () => replyForm.reset('message'),
  })
}
</script>

<template>
  <div class="max-w-3xl">
    <div class="flex items-center gap-3 mb-4">
      <Link :href="route('admin.support.index')" class="text-sm text-gray-500 hover:text-gray-700">← Support</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900 truncate">{{ ticket.subject }}</h1>
      <StatusBadge :status="ticket.status" />
    </div>

    <div class="flex gap-4 mb-5">
      <div class="flex-1 text-sm text-gray-500">
        From: <span class="font-medium text-gray-900">{{ ticket.user?.name }}</span>
        &nbsp;·&nbsp; {{ ticket.department }} &nbsp;·&nbsp; priority:
        <span class="font-medium">{{ ticket.priority }}</span>
      </div>
      <div class="flex items-center gap-2">
        <select v-model="assignForm.assigned_to"
          class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm"
          @change="assignForm.post(route('admin.support.assign', ticket.id))"
        >
          <option value="">Unassigned</option>
          <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <button v-if="ticket.status !== 'closed'" class="text-sm text-gray-500 hover:text-gray-700 border border-gray-300 px-3 py-1.5 rounded-lg"
          @click="router.post(route('admin.support.close', ticket.id))">
          Close
        </button>
      </div>
    </div>

    <!-- Replies -->
    <div class="space-y-3 mb-5">
      <div v-for="reply in ticket.replies" :key="reply.id"
        :class="['rounded-xl p-4 text-sm', reply.is_staff ? 'bg-indigo-50 border border-indigo-100 ml-8' : 'bg-white border border-gray-200']"
      >
        <div class="flex justify-between mb-1">
          <span class="font-medium text-gray-900">{{ reply.user?.name }}</span>
          <span class="text-xs text-gray-400">{{ new Date(reply.created_at).toLocaleString() }}</span>
        </div>
        <p class="text-gray-700 whitespace-pre-wrap">{{ reply.message }}</p>
      </div>
    </div>

    <!-- Reply form -->
    <div v-if="ticket.status !== 'closed'" class="bg-white rounded-xl border border-gray-200 p-4">
      <form @submit.prevent="sendReply">
        <textarea v-model="replyForm.message" rows="4" placeholder="Write a reply…" required
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
        />
        <div class="flex justify-end mt-2">
          <button type="submit" :disabled="replyForm.processing" class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            Send Reply
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
