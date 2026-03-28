<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { useForm, Link, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props  = defineProps({ ticket: Object })
const page   = usePage()
const flash  = computed(() => page.props.flash)

const form      = useForm({ message: '', attachments: [] })
const rateForm  = useForm({ rating: 0, rating_note: '' })
const hoveredStar = ref(0)
const fileInput = ref(null)
const pendingFiles = ref([])

function onFileChange(e) {
    pendingFiles.value = Array.from(e.target.files)
    form.attachments   = pendingFiles.value
}

function removeFile(i) {
    pendingFiles.value.splice(i, 1)
    form.attachments = [...pendingFiles.value]
}

function send() {
    form.post(route('client.support.reply', props.ticket.id), {
        forceFormData: true,
        onSuccess: () => {
            form.reset('message')
            pendingFiles.value = []
            if (fileInput.value) fileInput.value.value = ''
        },
    })
}

function submitRating() {
    rateForm.post(route('client.support.rate', props.ticket.id))
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
</script>

<template>
    <div class="max-w-2xl">
        <div class="flex items-center gap-3 mb-4">
            <Link :href="route('client.support.index')" class="text-sm text-gray-500 hover:text-gray-700">← Support</Link>
            <span class="text-gray-300">/</span>
            <h1 class="text-xl font-bold text-gray-900 truncate">{{ ticket.subject }}</h1>
            <StatusBadge :status="ticket.status" />
        </div>

        <!-- Flash messages -->
        <div v-if="flash?.success" class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ flash.success }}
        </div>

        <!-- Reply thread -->
        <div class="space-y-3 mb-5">
            <div v-for="reply in ticket.replies" :key="reply.id"
                :class="['rounded-xl p-4 text-sm', reply.is_staff
                    ? 'bg-indigo-50 border border-indigo-100'
                    : 'bg-white border border-gray-200']"
            >
                <div class="flex justify-between mb-1">
                    <span class="font-medium" :class="reply.is_staff ? 'text-indigo-700' : 'text-gray-900'">
                        {{ reply.is_staff ? 'Support Team' : 'You' }}
                    </span>
                    <span class="text-xs text-gray-400">{{ new Date(reply.created_at).toLocaleString() }}</span>
                </div>
                <p class="text-gray-700 whitespace-pre-wrap">{{ reply.message }}</p>

                <!-- Attachments -->
                <div v-if="reply.attachments?.length" class="mt-2 flex flex-wrap gap-2">
                    <a v-for="att in reply.attachments" :key="att.id"
                        :href="route('client.support.attachments.download', att.id)"
                        target="_blank"
                        class="flex items-center gap-1.5 text-xs bg-white border border-gray-200 hover:bg-gray-50 rounded-lg px-2.5 py-1.5 text-gray-600">
                        <span>{{ fileIcon(att.mime_type) }}</span>
                        <span class="max-w-[160px] truncate">{{ att.filename }}</span>
                        <span class="text-gray-400">{{ humanSize(att.size) }}</span>
                    </a>
                </div>
            </div>
            <div v-if="!ticket.replies?.length" class="text-center text-gray-400 text-sm py-6">
                Your ticket has been submitted. We'll reply soon.
            </div>
        </div>

        <!-- Reply form -->
        <div v-if="ticket.status !== 'closed'" class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
            <form @submit.prevent="send">
                <textarea v-model="form.message" rows="4" placeholder="Write a reply…" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                />

                <!-- File picker -->
                <div class="mt-2">
                    <label class="flex items-center gap-2 text-xs text-gray-400 cursor-pointer hover:text-indigo-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                        </svg>
                        Attach files (max 5 · 10 MB each)
                        <input ref="fileInput" type="file" multiple class="hidden" @change="onFileChange" />
                    </label>
                    <div v-if="pendingFiles.length" class="mt-2 flex flex-wrap gap-2">
                        <span v-for="(f, i) in pendingFiles" :key="i"
                            class="flex items-center gap-1.5 text-xs bg-gray-100 border border-gray-200 rounded-lg px-2.5 py-1">
                            {{ fileIcon(f.type) }} {{ f.name }}
                            <button type="button" @click="removeFile(i)" class="text-gray-400 hover:text-red-500">×</button>
                        </span>
                    </div>
                </div>

                <div class="flex justify-end mt-3">
                    <button type="submit" :disabled="form.processing"
                        class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
                        {{ form.processing ? 'Sending…' : 'Send Reply' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Closed state -->
        <div v-else class="bg-gray-50 rounded-xl border border-gray-200 p-4">
            <p class="text-center text-sm text-gray-500 mb-4">This ticket is closed.</p>

            <!-- Rating section -->
            <div v-if="ticket.rating === null || ticket.rating === undefined" class="border-t border-gray-100 pt-4">
                <p class="text-sm font-medium text-gray-700 mb-3 text-center">How was your experience?</p>
                <div class="flex justify-center gap-1 mb-3">
                    <button v-for="i in 5" :key="i" type="button"
                        @mouseover="hoveredStar = i"
                        @mouseleave="hoveredStar = 0"
                        @click="rateForm.rating = i"
                        class="text-3xl leading-none transition-transform hover:scale-110">
                        <span :class="i <= (hoveredStar || rateForm.rating) ? 'text-amber-400' : 'text-gray-200'">★</span>
                    </button>
                </div>
                <div v-if="rateForm.rating > 0" class="space-y-3">
                    <textarea v-model="rateForm.rating_note" rows="2" placeholder="Optional feedback…"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none" />
                    <div class="flex justify-center">
                        <button @click="submitRating" :disabled="rateForm.processing"
                            class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
                            {{ rateForm.processing ? 'Submitting…' : 'Submit Rating' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Already rated -->
            <div v-else class="border-t border-gray-100 pt-4 text-center">
                <p class="text-xs text-gray-400 mb-1">Your rating:</p>
                <div class="flex justify-center gap-0.5 text-xl">
                    <span v-for="i in 5" :key="i" :class="i <= ticket.rating ? 'text-amber-400' : 'text-gray-200'">★</span>
                </div>
                <p v-if="ticket.rating_note" class="mt-1 text-xs text-gray-500 italic">"{{ ticket.rating_note }}"</p>
            </div>
        </div>
    </div>
</template>
