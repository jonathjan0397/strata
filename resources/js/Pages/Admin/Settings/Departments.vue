<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ departments: Array })

const showForm = ref(false)
const editing = ref(null)

const blankForm = () => ({ name: '', description: '', email: '', sort_order: 0, active: true })

const form = useForm(blankForm())

function startEdit(dept) {
    editing.value = dept.id
    form.name        = dept.name
    form.description = dept.description ?? ''
    form.email       = dept.email ?? ''
    form.sort_order  = dept.sort_order
    form.active      = dept.active
    showForm.value   = false
}

function cancelEdit() {
    editing.value = null
    form.reset()
}

function saveEdit(dept) {
    form.patch(route('admin.departments.update', dept.id), {
        onSuccess: () => { editing.value = null }
    })
}

function createDept() {
    form.post(route('admin.departments.store'), {
        onSuccess: () => { showForm.value = false; form.reset() }
    })
}

function deleteDept(dept) {
    if (confirm(`Delete department "${dept.name}"? Tickets assigned to it will become unassigned.`)) {
        router.delete(route('admin.departments.destroy', dept.id))
    }
}
</script>

<template>
    <div class="max-w-2xl">
        <div class="flex items-center gap-3 mb-6">
            <Link :href="route('admin.settings.index')" class="text-sm text-gray-500 hover:text-gray-700">← Settings</Link>
            <span class="text-gray-300">/</span>
            <h1 class="text-xl font-bold text-gray-900">Departments</h1>
            <button @click="showForm = !showForm; editing = null"
                class="ml-auto px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                + Add Department
            </button>
        </div>

        <!-- Create form -->
        <div v-if="showForm" class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 mb-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">New Department</h3>
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Name *</label>
                    <input v-model="form.name" type="text" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                    <input v-model="form.description" type="text"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Sort Order</label>
                    <input v-model="form.sort_order" type="number" min="0"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div class="flex items-center gap-2 pt-5">
                    <input id="active-new" v-model="form.active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                    <label for="active-new" class="text-sm text-gray-700">Active</label>
                </div>
            </div>
            <div class="flex gap-3 mt-3">
                <button @click="createDept" :disabled="form.processing"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    Create
                </button>
                <button @click="showForm = false; form.reset()" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
            </div>
        </div>

        <!-- List -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Description</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Sort</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template v-for="dept in departments" :key="dept.id">
                        <!-- View row -->
                        <tr v-if="editing !== dept.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ dept.name }}</td>
                            <td class="px-4 py-3 text-gray-500 text-xs">{{ dept.description ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-400">{{ dept.sort_order }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="dept.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                                    {{ dept.active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <button @click="startEdit(dept)" class="text-indigo-600 hover:underline text-xs">Edit</button>
                                <button @click="deleteDept(dept)" class="text-red-400 hover:text-red-600 text-xs">Delete</button>
                            </td>
                        </tr>
                        <!-- Edit row -->
                        <tr v-else class="bg-indigo-50">
                            <td class="px-4 py-2">
                                <input v-model="form.name" type="text"
                                    class="w-full border border-gray-300 rounded px-2 py-1 text-sm" />
                            </td>
                            <td class="px-4 py-2">
                                <input v-model="form.description" type="text"
                                    class="w-full border border-gray-300 rounded px-2 py-1 text-sm" />
                            </td>
                            <td class="px-4 py-2">
                                <input v-model="form.sort_order" type="number" min="0"
                                    class="w-20 border border-gray-300 rounded px-2 py-1 text-sm" />
                            </td>
                            <td class="px-4 py-2">
                                <input v-model="form.active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                            </td>
                            <td class="px-4 py-2 text-right space-x-2">
                                <button @click="saveEdit(dept)" :disabled="form.processing"
                                    class="text-indigo-600 hover:underline text-xs">Save</button>
                                <button @click="cancelEdit" class="text-gray-400 hover:text-gray-600 text-xs">Cancel</button>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="!departments.length">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">No departments yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
