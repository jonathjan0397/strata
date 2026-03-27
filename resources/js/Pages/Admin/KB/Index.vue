<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    articles:   Object,
    categories: Array,
    filters:    Object,
})

const search     = ref(props.filters?.search     ?? '')
const category   = ref(props.filters?.category   ?? '')
const published  = ref(props.filters?.published  ?? '')

watch([search, category, published], ([s, c, p]) => {
    router.get(route('admin.kb.index'), { search: s, category: c, published: p }, { preserveState: true, replace: true })
})

function destroy(article) {
    if (confirm(`Delete "${article.title}"?`)) {
        router.delete(route('admin.kb.destroy', article.id))
    }
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-gray-900">Knowledge Base</h1>
            <div class="flex gap-3">
                <Link :href="route('admin.kb.categories')" class="text-sm text-gray-500 hover:text-gray-700 border border-gray-300 px-3 py-1.5 rounded-lg">
                    Categories
                </Link>
                <Link :href="route('admin.kb.create')" class="text-sm bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700">
                    + New Article
                </Link>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-4">
            <input v-model="search" type="search" placeholder="Search articles…"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <select v-model="category" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Categories</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="published" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All</option>
                <option value="1">Published</option>
                <option value="0">Drafts</option>
            </select>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Title</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Category</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500">Views</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="a in articles.data" :key="a.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ a.title }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ a.category?.name }}</td>
                        <td class="px-4 py-3 text-center text-gray-400">{{ a.views }}</td>
                        <td class="px-4 py-3 text-center">
                            <span :class="a.published ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                                {{ a.published ? 'Published' : 'Draft' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <Link :href="route('admin.kb.edit', a.id)" class="text-xs text-indigo-600 hover:underline">Edit</Link>
                            <button @click="destroy(a)" class="text-xs text-red-500 hover:underline">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!articles.data.length">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">No articles found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="articles.last_page > 1" class="mt-4 flex gap-2">
            <Link v-for="link in articles.links" :key="link.label"
                :href="link.url ?? '#'"
                class="px-3 py-1 text-sm border rounded"
                :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                v-html="link.label" />
        </div>
    </div>
</template>
