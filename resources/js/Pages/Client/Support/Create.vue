<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    departments: Array,
})

const form = useForm({
    subject:       '',
    department_id: props.departments?.[0]?.id ?? null,
    priority:      'medium',
    message:       '',
    attachments:   [],
})

// KB suggestions
const kbSuggestions = ref([])
let kbDebounce = null

watch(() => form.subject, (val) => {
    clearTimeout(kbDebounce)
    if (val.trim().length < 3) { kbSuggestions.value = []; return }
    kbDebounce = setTimeout(async () => {
        try {
            const res = await fetch(route('client.support.kb-suggest') + '?q=' + encodeURIComponent(val.trim()), {
                headers: { Accept: 'application/json' },
            })
            kbSuggestions.value = await res.json()
        } catch { kbSuggestions.value = [] }
    }, 400)
})

const fileInput    = ref(null)
const pendingFiles = ref([])

function onFileChange(e) {
    pendingFiles.value = Array.from(e.target.files)
    form.attachments   = pendingFiles.value
}

function removeFile(i) {
    pendingFiles.value.splice(i, 1)
    form.attachments = [...pendingFiles.value]
}

function fileIcon(mime) {
    if (mime?.startsWith('image/')) return '🖼'
    if (mime?.includes('pdf'))      return '📄'
    if (mime?.includes('zip') || mime?.includes('compressed')) return '📦'
    return '📎'
}

function submit() {
    form.post(route('client.support.store'), { forceFormData: true })
}
</script>

<template>
    <div class="max-w-2xl">
        <div class="flex items-center gap-3 mb-6">
            <Link :href="route('client.support.index')" class="text-sm text-gray-500 hover:text-gray-700">← Support</Link>
            <span class="text-gray-300">/</span>
            <h1 class="text-xl font-bold text-gray-900">New Ticket</h1>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <form @submit.prevent="submit" class="space-y-4">
                <!-- Subject -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input v-model="form.subject" type="text" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.subject }" />
                    <p v-if="form.errors.subject" class="text-red-500 text-xs mt-1">{{ form.errors.subject }}</p>

                    <!-- KB suggestions -->
                    <div v-if="kbSuggestions.length" class="mt-2 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3">
                        <p class="text-xs font-medium text-blue-700 mb-2">
                            <svg class="inline h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            Before you submit — these articles may answer your question:
                        </p>
                        <ul class="space-y-1">
                            <li v-for="a in kbSuggestions" :key="a.id">
                                <a :href="route('client.kb.show', a.slug)" target="_blank"
                                    class="text-sm text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1">
                                    <svg class="h-3 w-3 shrink-0 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ a.title }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Department -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select v-model="form.department_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>

                    <!-- Priority -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select v-model="form.priority"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <!-- Message -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                    <textarea v-model="form.message" rows="6" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                        :class="{ 'border-red-400': form.errors.message }" />
                    <p v-if="form.errors.message" class="text-red-500 text-xs mt-1">{{ form.errors.message }}</p>
                </div>

                <!-- Attachments -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Attachments <span class="font-normal text-gray-400">(optional · max 5 files · 10 MB each)</span></label>
                    <label class="flex items-center gap-2 border border-dashed border-gray-300 rounded-lg px-4 py-3 text-sm text-gray-400 cursor-pointer hover:border-indigo-400 hover:text-indigo-600 transition-colors">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                        </svg>
                        Click to attach files
                        <input ref="fileInput" type="file" multiple class="hidden" @change="onFileChange" />
                    </label>
                    <div v-if="pendingFiles.length" class="mt-2 flex flex-wrap gap-2">
                        <span v-for="(f, i) in pendingFiles" :key="i"
                            class="flex items-center gap-1.5 text-xs bg-gray-100 border border-gray-200 rounded-lg px-2.5 py-1.5">
                            {{ fileIcon(f.type) }}
                            <span class="max-w-[160px] truncate">{{ f.name }}</span>
                            <button type="button" @click="removeFile(i)" class="text-gray-400 hover:text-red-500 ml-0.5">×</button>
                        </span>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-1">
                    <Link :href="route('client.support.index')" class="text-sm text-gray-500 px-4 py-2">Cancel</Link>
                    <button type="submit" :disabled="form.processing"
                        class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
                        {{ form.processing ? 'Submitting…' : 'Submit Ticket' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
