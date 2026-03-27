<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({
    article: Object,
    related: Array,
})
</script>

<template>
    <div class="max-w-3xl">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
            <Link :href="route('client.kb.index')" class="hover:text-gray-700">Help Center</Link>
            <span class="text-gray-300">/</span>
            <Link :href="route('client.kb.index')" class="hover:text-gray-700">{{ article.category?.name }}</Link>
            <span class="text-gray-300">/</span>
            <span class="text-gray-700 truncate">{{ article.title }}</span>
        </div>

        <!-- Article -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ article.title }}</h1>
            <div class="flex items-center gap-4 text-xs text-gray-400 mb-6 pb-4 border-b border-gray-100">
                <span>{{ article.category?.name }}</span>
                <span>{{ article.views }} views</span>
                <span>Updated {{ new Date(article.updated_at).toLocaleDateString() }}</span>
            </div>
            <div class="prose prose-sm max-w-none text-gray-700 whitespace-pre-wrap leading-relaxed">{{ article.body }}</div>
        </div>

        <!-- Related articles -->
        <div v-if="related?.length" class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Related Articles</h2>
            <ul class="space-y-1.5">
                <li v-for="a in related" :key="a.id">
                    <Link :href="route('client.kb.show', a.slug)" class="text-sm text-indigo-600 hover:underline">
                        {{ a.title }}
                    </Link>
                </li>
            </ul>
        </div>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-400">Still need help?</p>
            <Link :href="route('client.support.create')" class="text-sm text-indigo-600 hover:underline">Open a support ticket →</Link>
        </div>
    </div>
</template>
