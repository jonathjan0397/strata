<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    cannedResponses: Array,
    departments:     Array,
})

const showForm = ref(false)
const editing  = ref(null)

const blankForm = () => ({ title: '', body: '', department_id: null })
const form = useForm(blankForm())

function startEdit(cr) {
    editing.value       = cr.id
    form.title          = cr.title
    form.body           = cr.body
    form.department_id  = cr.department_id
    showForm.value      = false
}

function cancelEdit() {
    editing.value = null
    form.reset()
}

function saveEdit(cr) {
    form.patch(route('admin.canned-responses.update', cr.id), {
        onSuccess: () => { editing.value = null }
    })
}

function create() {
    form.post(route('admin.canned-responses.store'), {
        onSuccess: () => { showForm.value = false; form.reset() }
    })
}

function remove(cr) {
    if (confirm(`Delete "${cr.title}"?`)) {
        router.delete(route('admin.canned-responses.destroy', cr.id))
    }
}

function deptName(id) {
    return props.departments.find(d => d.id === id)?.name ?? 'All Departments'
}
</script>

<template>
    <div class="max-w-3xl">
        <div class="flex items-center gap-3 mb-6">
            <Link :href="route('admin.settings.index')" class="text-sm text-gray-500 hover:text-gray-700">← Settings</Link>
            <span class="text-gray-300">/</span>
            <h1 class="text-xl font-bold text-gray-900">Canned Responses</h1>
            <button @click="showForm = !showForm; editing = null"
                class="ml-auto px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                + Add Response
            </button>
        </div>

        <!-- Create form -->
        <div v-if="showForm" class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 mb-4 space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">New Canned Response</h3>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Title *</label>
                <input v-model="form.title" type="text" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                <p v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Department (optional)</label>
                <select v-model="form.department_id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option :value="null">All Departments</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Body *</label>
                <textarea v-model="form.body" rows="5" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                <p v-if="form.errors.body" class="text-red-500 text-xs mt-1">{{ form.errors.body }}</p>
            </div>
            <div class="flex gap-3">
                <button @click="create" :disabled="form.processing"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    Create
                </button>
                <button @click="showForm = false; form.reset()" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
            </div>
        </div>

        <!-- List -->
        <div class="space-y-3">
            <div v-for="cr in cannedResponses" :key="cr.id"
                class="bg-white rounded-xl border border-gray-200 p-4">
                <template v-if="editing !== cr.id">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ cr.title }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ deptName(cr.department_id) }}</p>
                        </div>
                        <div class="flex gap-3 shrink-0 ml-4">
                            <button @click="startEdit(cr)" class="text-indigo-600 hover:underline text-xs">Edit</button>
                            <button @click="remove(cr)" class="text-red-400 hover:text-red-600 text-xs">Delete</button>
                        </div>
                    </div>
                    <pre class="text-xs text-gray-500 mt-2 whitespace-pre-wrap font-sans">{{ cr.body }}</pre>
                </template>
                <template v-else>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Title</label>
                            <input v-model="form.title" type="text"
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Department</label>
                            <select v-model="form.department_id"
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                                <option :value="null">All Departments</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Body</label>
                            <textarea v-model="form.body" rows="5"
                                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm font-mono" />
                        </div>
                        <div class="flex gap-3">
                            <button @click="saveEdit(cr)" :disabled="form.processing"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                Save
                            </button>
                            <button @click="cancelEdit" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                        </div>
                    </div>
                </template>
            </div>
            <div v-if="!cannedResponses.length" class="bg-white rounded-xl border border-gray-200 px-4 py-8 text-center text-gray-400 text-sm">
                No canned responses yet.
            </div>
        </div>
    </div>
</template>
