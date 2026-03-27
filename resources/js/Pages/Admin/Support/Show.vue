<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    ticket:          Object,
    staff:           Array,
    cannedResponses: Array,
})

const replyForm    = useForm({ message: '', internal: false })
const assignForm   = useForm({ assigned_to: props.ticket.assigned_to ?? '' })
const priorityForm = useForm({ priority: props.ticket.priority ?? 'medium' })

const showCanned = ref(false)

function sendReply() {
    replyForm.post(route('admin.support.reply', props.ticket.id), {
        onSuccess: () => replyForm.reset('message'),
    })
}

function insertCanned(cr) {
    replyForm.message = cr.body
    showCanned.value  = false
}

const priorityClass = {
    low:    'text-gray-400',
    medium: 'text-yellow-600',
    high:   'text-orange-600',
    urgent: 'text-red-600 font-semibold',
}
</script>

<template>
    <div class="max-w-3xl">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-4">
            <Link :href="route('admin.support.index')" class="text-sm text-gray-500 hover:text-gray-700">← Support</Link>
            <span class="text-gray-300">/</span>
            <h1 class="text-xl font-bold text-gray-900 truncate">{{ ticket.subject }}</h1>
            <StatusBadge :status="ticket.status" />
        </div>

        <!-- Meta bar -->
        <div class="flex flex-wrap gap-4 mb-5 text-sm">
            <div class="flex-1 text-gray-500">
                From: <span class="font-medium text-gray-900">{{ ticket.user?.name }}</span>
                &nbsp;·&nbsp;
                <span class="text-gray-600">{{ ticket.department?.name ?? ticket.department ?? 'General' }}</span>
                &nbsp;·&nbsp;
                Priority:
                <select v-model="priorityForm.priority" :class="['text-sm font-medium border-0 bg-transparent focus:outline-none cursor-pointer', priorityClass[priorityForm.priority]]"
                    @change="priorityForm.patch(route('admin.support.priority', ticket.id))">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <select v-model="assignForm.assigned_to"
                    class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm"
                    @change="assignForm.post(route('admin.support.assign', ticket.id))">
                    <option value="">Unassigned</option>
                    <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <button v-if="ticket.status !== 'closed'"
                    class="text-sm text-gray-500 hover:text-gray-700 border border-gray-300 px-3 py-1.5 rounded-lg"
                    @click="router.post(route('admin.support.close', ticket.id))">
                    Close
                </button>
                <button v-else
                    class="text-sm text-indigo-600 hover:text-indigo-800 border border-indigo-200 px-3 py-1.5 rounded-lg"
                    @click="router.post(route('admin.support.reopen', ticket.id))">
                    Reopen
                </button>
            </div>
        </div>

        <!-- Replies thread -->
        <div class="space-y-3 mb-5">
            <div v-for="reply in ticket.replies" :key="reply.id"
                :class="[
                    'rounded-xl p-4 text-sm',
                    reply.internal
                        ? 'bg-amber-50 border border-amber-200 border-dashed'
                        : reply.is_staff
                            ? 'bg-indigo-50 border border-indigo-100 ml-8'
                            : 'bg-white border border-gray-200'
                ]">
                <div class="flex justify-between mb-1">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-900">{{ reply.user?.name }}</span>
                        <span v-if="reply.internal"
                            class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-medium">
                            Internal Note
                        </span>
                        <span v-else-if="reply.is_staff"
                            class="text-xs bg-indigo-100 text-indigo-600 px-1.5 py-0.5 rounded">
                            Staff
                        </span>
                    </div>
                    <span class="text-xs text-gray-400">{{ new Date(reply.created_at).toLocaleString() }}</span>
                </div>
                <p class="text-gray-700 whitespace-pre-wrap">{{ reply.message }}</p>
            </div>
            <div v-if="!ticket.replies?.length" class="text-center text-gray-400 text-sm py-6">
                No replies yet.
            </div>
        </div>

        <!-- Reply form -->
        <div v-if="ticket.status !== 'closed'" class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
            <!-- Canned response picker -->
            <div v-if="cannedResponses?.length" class="relative">
                <button type="button" @click="showCanned = !showCanned"
                    class="text-xs text-indigo-600 hover:underline">
                    Insert canned response ▾
                </button>
                <div v-if="showCanned"
                    class="absolute left-0 top-full mt-1 z-10 bg-white border border-gray-200 rounded-xl shadow-lg w-80 max-h-64 overflow-y-auto">
                    <button v-for="cr in cannedResponses" :key="cr.id"
                        type="button"
                        @click="insertCanned(cr)"
                        class="w-full text-left px-4 py-2.5 text-sm hover:bg-gray-50 border-b border-gray-100 last:border-0">
                        <p class="font-medium text-gray-900">{{ cr.title }}</p>
                        <p v-if="cr.department" class="text-xs text-gray-400">{{ cr.department?.name }}</p>
                    </button>
                </div>
            </div>

            <textarea v-model="replyForm.message" rows="5" placeholder="Write a reply…" required
                :class="['w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 resize-none',
                    replyForm.internal
                        ? 'border-amber-300 bg-amber-50 focus:ring-amber-400'
                        : 'border-gray-300 focus:ring-indigo-500']"
            />

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input v-model="replyForm.internal" type="checkbox"
                        class="rounded border-gray-300 text-amber-500" />
                    <span class="text-sm text-gray-600">Internal note <span class="text-xs text-gray-400">(not visible to client)</span></span>
                </label>
                <button type="button" @click="sendReply" :disabled="replyForm.processing"
                    :class="['text-white text-sm font-medium px-5 py-2 rounded-lg disabled:opacity-50',
                        replyForm.internal ? 'bg-amber-500 hover:bg-amber-600' : 'bg-indigo-600 hover:bg-indigo-500']">
                    {{ replyForm.internal ? 'Add Note' : 'Send Reply' }}
                </button>
            </div>
        </div>

        <div v-else class="bg-gray-50 rounded-xl border border-gray-200 px-4 py-4 text-sm text-gray-500 text-center">
            This ticket is closed.
            <button @click="router.post(route('admin.support.reopen', ticket.id))"
                class="ml-2 text-indigo-600 hover:underline">Reopen it</button>
        </div>
    </div>
</template>
