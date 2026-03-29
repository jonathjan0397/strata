<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import TiptapEditor from '@/Components/TiptapEditor.vue'
import { useForm, router, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    ticket:          Object,
    departments:     Array,
    staff:           Array,
    cannedResponses: Array,
})

const replyForm      = useForm({ message: '', internal: false, attachments: [] })
const assignForm     = useForm({ assigned_to: props.ticket.assigned_to ?? '' })
const priorityForm   = useForm({ priority: props.ticket.priority ?? 'medium' })
const deptForm       = useForm({ department_id: props.ticket.department_id ?? '' })
const mergeForm      = useForm({ merge_ticket_id: '' })

const showCanned     = ref(false)
const showMerge      = ref(false)
const fileInput      = ref(null)
const pendingFiles   = ref([])

function onFileChange(e) {
    pendingFiles.value = Array.from(e.target.files)
    replyForm.attachments = pendingFiles.value
}

function removeFile(i) {
    pendingFiles.value.splice(i, 1)
    replyForm.attachments = [...pendingFiles.value]
}

function sendReply() {
    replyForm.post(route('admin.support.reply', props.ticket.id), {
        forceFormData: true,
        onSuccess: () => {
            replyForm.reset('message')
            pendingFiles.value = []
            if (fileInput.value) fileInput.value.value = ''
        },
    })
}

function insertCanned(cr) {
    replyForm.message = cr.body
    showCanned.value  = false
}

function humanSize(bytes) {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / 1048576).toFixed(1)} MB`
}

function fileIcon(mime) {
    if (mime?.startsWith('image/')) return '🖼'
    if (mime?.includes('pdf'))      return '📄'
    if (mime?.includes('zip') || mime?.includes('compressed')) return '📦'
    return '📎'
}

const priorityClass = {
    low:    'text-gray-400',
    medium: 'text-yellow-600',
    high:   'text-orange-600',
    urgent: 'text-red-600 font-semibold',
}

const responseTime = computed(() => {
    if (! props.ticket.first_replied_at) return null
    const ms = new Date(props.ticket.first_replied_at) - new Date(props.ticket.created_at)
    const h  = Math.floor(ms / 3600000)
    const m  = Math.floor((ms % 3600000) / 60000)
    return h > 0 ? `${h}h ${m}m` : `${m}m`
})
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
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 mb-5 text-sm">
            <div class="flex flex-wrap gap-x-6 gap-y-2 items-center">
                <!-- Client -->
                <div class="text-gray-500">
                    Client: <span class="font-medium text-gray-900">{{ ticket.user?.name }}</span>
                </div>

                <!-- Department (editable) -->
                <div class="flex items-center gap-1.5 text-gray-500">
                    Dept:
                    <select v-model="deptForm.department_id"
                        class="border-0 bg-transparent text-gray-700 text-sm focus:outline-none cursor-pointer -ml-1"
                        @change="deptForm.patch(route('admin.support.transfer', ticket.id))">
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>

                <!-- Priority (editable) -->
                <div class="flex items-center gap-1 text-gray-500">
                    Priority:
                    <select v-model="priorityForm.priority"
                        :class="['border-0 bg-transparent text-sm focus:outline-none cursor-pointer -ml-1', priorityClass[priorityForm.priority]]"
                        @change="priorityForm.patch(route('admin.support.priority', ticket.id))">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <!-- Assigned to (editable) -->
                <div class="flex items-center gap-1.5 text-gray-500">
                    Agent:
                    <select v-model="assignForm.assigned_to"
                        class="border-0 bg-transparent text-gray-700 text-sm focus:outline-none cursor-pointer -ml-1"
                        @change="assignForm.post(route('admin.support.assign', ticket.id))">
                        <option value="">Unassigned</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>

                <!-- Response time -->
                <div v-if="responseTime" class="text-gray-400 text-xs">
                    1st reply: {{ responseTime }}
                </div>

                <!-- Rating -->
                <div v-if="ticket.rating" class="flex items-center gap-1 text-amber-500 text-xs">
                    <span v-for="i in 5" :key="i">{{ i <= ticket.rating ? '★' : '☆' }}</span>
                    <span class="text-gray-400 ml-1">{{ ticket.rating }}/5</span>
                </div>

                <!-- Close / Reopen -->
                <div class="ml-auto flex gap-2">
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
                    <button @click="showMerge = !showMerge"
                        class="text-sm text-gray-400 hover:text-gray-600 border border-gray-200 px-3 py-1.5 rounded-lg">
                        Merge
                    </button>
                </div>
            </div>

            <!-- Merge panel -->
            <div v-if="showMerge" class="mt-3 pt-3 border-t border-gray-100 flex items-center gap-3">
                <span class="text-xs text-gray-500">Merge ticket #</span>
                <input v-model="mergeForm.merge_ticket_id" type="number" placeholder="ticket ID"
                    class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-32 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                <span class="text-xs text-gray-400">into this ticket (closes the other)</span>
                <button @click="mergeForm.post(route('admin.support.merge', ticket.id))"
                    :disabled="!mergeForm.merge_ticket_id || mergeForm.processing"
                    class="bg-gray-700 hover:bg-gray-600 disabled:opacity-50 text-white text-xs font-medium px-3 py-1.5 rounded-lg">
                    Merge
                </button>
                <p v-if="mergeForm.errors.merge_ticket_id" class="text-red-500 text-xs">{{ mergeForm.errors.merge_ticket_id }}</p>
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
                <div class="prose prose-sm max-w-none text-gray-700" v-html="reply.message"></div>

                <!-- Reply attachments -->
                <div v-if="reply.attachments?.length" class="mt-2 flex flex-wrap gap-2">
                    <a v-for="att in reply.attachments" :key="att.id"
                        :href="route('admin.support.attachments.download', att.id)"
                        target="_blank"
                        class="flex items-center gap-1.5 text-xs bg-white border border-gray-200 hover:bg-gray-50 rounded-lg px-2.5 py-1.5 text-gray-600 transition-colors">
                        <span>{{ fileIcon(att.mime_type) }}</span>
                        <span class="max-w-[160px] truncate">{{ att.filename }}</span>
                        <span class="text-gray-400">{{ humanSize(att.size) }}</span>
                    </a>
                </div>
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

            <div :class="replyForm.internal ? 'ring-2 ring-amber-400 rounded-lg overflow-hidden' : ''">
                <TiptapEditor
                    v-model="replyForm.message"
                    placeholder="Write a reply…"
                    min-height="140px"
                    :show-images="false"
                />
            </div>

            <!-- File picker -->
            <div>
                <label class="flex items-center gap-2 text-xs text-gray-500 cursor-pointer hover:text-indigo-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                    </svg>
                    Attach files (max 5 · 10 MB each)
                    <input ref="fileInput" type="file" multiple accept="*/*" class="hidden" @change="onFileChange" />
                </label>
                <div v-if="pendingFiles.length" class="mt-2 flex flex-wrap gap-2">
                    <span v-for="(f, i) in pendingFiles" :key="i"
                        class="flex items-center gap-1.5 text-xs bg-gray-100 border border-gray-200 rounded-lg px-2.5 py-1">
                        {{ fileIcon(f.type) }} {{ f.name }}
                        <button type="button" @click="removeFile(i)" class="text-gray-400 hover:text-red-500 ml-0.5">×</button>
                    </span>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input v-model="replyForm.internal" type="checkbox"
                        class="rounded border-gray-300 text-amber-500" />
                    <span class="text-sm text-gray-600">
                        Internal note <span class="text-xs text-gray-400">(not visible to client)</span>
                    </span>
                </label>
                <button type="button" @click="sendReply" :disabled="replyForm.processing"
                    :class="['text-white text-sm font-medium px-5 py-2 rounded-lg disabled:opacity-50',
                        replyForm.internal ? 'bg-amber-500 hover:bg-amber-600' : 'bg-indigo-600 hover:bg-indigo-500']">
                    {{ replyForm.processing ? 'Sending…' : replyForm.internal ? 'Add Note' : 'Send Reply' }}
                </button>
            </div>
        </div>

        <div v-else class="bg-gray-50 rounded-xl border border-gray-200 px-4 py-4 text-sm text-center">
            <span class="text-gray-500">This ticket is closed.</span>
            <button @click="router.post(route('admin.support.reopen', ticket.id))"
                class="ml-2 text-indigo-600 hover:underline">Reopen it</button>
            <div v-if="ticket.rating" class="mt-2 text-xs text-gray-400">
                Client rated: <span v-for="i in 5" :key="i" class="text-amber-400">{{ i <= ticket.rating ? '★' : '☆' }}</span>
                <span v-if="ticket.rating_note" class="ml-2 italic">"{{ ticket.rating_note }}"</span>
            </div>
        </div>
    </div>
</template>
