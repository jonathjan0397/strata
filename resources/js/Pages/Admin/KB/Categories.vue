<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ categories: Array })

const createForm = useForm({ name: '', description: '', sort_order: 0 })
const editId     = ref(null)
const editForm   = useForm({ name: '', description: '', sort_order: 0, active: true })

function startEdit(cat) {
    editId.value = cat.id
    editForm.name        = cat.name
    editForm.description = cat.description ?? ''
    editForm.sort_order  = cat.sort_order ?? 0
    editForm.active      = cat.active
}

function saveEdit(cat) {
    editForm.patch(route('admin.kb.categories.update', cat.id), {
        onSuccess: () => { editId.value = null },
    })
}

function destroy(cat) {
    if (confirm(`Delete category "${cat.name}"? Articles will be removed.`)) {
        router.delete(route('admin.kb.categories.destroy', cat.id))
    }
}
</script>

<template>
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-gray-900">KB Categories</h1>
            <Link :href="route('admin.kb.index')" class="text-sm text-indigo-600 hover:underline">← Articles</Link>
        </div>

        <!-- Create form -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">New Category</h2>
            <form @submit.prevent="createForm.post(route('admin.kb.categories.store'), { onSuccess: () => createForm.reset() })"
                class="space-y-3">
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <input v-model="createForm.name" type="text" placeholder="Category name" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <input v-model="createForm.sort_order" type="number" min="0" placeholder="Order"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>
                <input v-model="createForm.description" type="text" placeholder="Description (optional)"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                <div class="flex justify-end">
                    <button type="submit" :disabled="createForm.processing"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                        Add Category
                    </button>
                </div>
            </form>
        </div>

        <!-- Category list -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Name</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500">Articles</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500">Active</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template v-for="cat in categories" :key="cat.id">
                        <tr v-if="editId !== cat.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ cat.name }}</p>
                                <p v-if="cat.description" class="text-xs text-gray-400">{{ cat.description }}</p>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ cat.articles_count }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="cat.active ? 'text-green-600' : 'text-gray-400'" class="text-xs font-medium">
                                    {{ cat.active ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-3">
                                <button @click="startEdit(cat)" class="text-xs text-indigo-600 hover:underline">Edit</button>
                                <button @click="destroy(cat)" class="text-xs text-red-500 hover:underline">Delete</button>
                            </td>
                        </tr>
                        <tr v-else>
                            <td colspan="4" class="px-4 py-3 bg-indigo-50">
                                <div class="space-y-2">
                                    <div class="grid grid-cols-3 gap-2">
                                        <input v-model="editForm.name" type="text"
                                            class="col-span-2 border border-gray-300 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                        <input v-model="editForm.sort_order" type="number" min="0"
                                            class="border border-gray-300 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                    </div>
                                    <input v-model="editForm.description" type="text" placeholder="Description"
                                        class="w-full border border-gray-300 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                    <div class="flex items-center justify-between">
                                        <label class="flex items-center gap-2 text-sm text-gray-600">
                                            <input v-model="editForm.active" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                            Active
                                        </label>
                                        <div class="flex gap-2">
                                            <button type="button" @click="editId = null" class="text-xs text-gray-500">Cancel</button>
                                            <button type="button" @click="saveEdit(cat)" :disabled="editForm.processing"
                                                class="px-3 py-1 bg-indigo-600 text-white text-xs rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                                Save
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="!categories.length">
                        <td colspan="4" class="px-4 py-8 text-center text-gray-400">No categories yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
