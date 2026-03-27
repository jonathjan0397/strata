<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({
    article:    Object,
    categories: Array,
})

const isEdit = !!props.article

const form = useForm({
    kb_category_id: props.article?.kb_category_id ?? props.categories?.[0]?.id ?? null,
    title:          props.article?.title          ?? '',
    body:           props.article?.body           ?? '',
    published:      props.article?.published      ?? false,
    sort_order:     props.article?.sort_order     ?? 0,
})

function save() {
    if (isEdit) {
        form.patch(route('admin.kb.update', props.article.id))
    } else {
        form.post(route('admin.kb.store'))
    }
}
</script>

<template>
    <div class="max-w-3xl">
        <div class="flex items-center gap-3 mb-6">
            <Link :href="route('admin.kb.index')" class="text-sm text-gray-500 hover:text-gray-700">← Knowledge Base</Link>
            <span class="text-gray-300">/</span>
            <h1 class="text-xl font-bold text-gray-900">{{ isEdit ? 'Edit Article' : 'New Article' }}</h1>
        </div>

        <form @submit.prevent="save" class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select v-model="form.kb_category_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="form.errors.kb_category_id" class="text-red-500 text-xs mt-1">{{ form.errors.kb_category_id }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input v-model="form.sort_order" type="number" min="0"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                    <input v-model="form.title" type="text" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-400': form.errors.title }" />
                    <p v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Body</label>
                    <textarea v-model="form.body" rows="20" required
                        placeholder="Write article content here. Markdown is supported."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
                        :class="{ 'border-red-400': form.errors.body }" />
                    <p v-if="form.errors.body" class="text-red-500 text-xs mt-1">{{ form.errors.body }}</p>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input v-model="form.published" type="checkbox"
                            class="rounded border-gray-300 text-indigo-600" />
                        <span class="text-sm text-gray-700">Published <span class="text-gray-400 text-xs">(visible to clients)</span></span>
                    </label>

                    <div class="flex items-center gap-3">
                        <span v-if="form.recentlySuccessful" class="text-sm text-green-600">Saved.</span>
                        <button type="submit" :disabled="form.processing"
                            class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {{ isEdit ? 'Save Changes' : 'Create Article' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
