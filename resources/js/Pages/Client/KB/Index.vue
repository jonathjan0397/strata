<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    categories: Array,
    articles:   Array,
    search:     String,
})

const query = ref(props.search ?? '')

watch(query, (q) => {
    router.get(route('client.kb.index'), { search: q }, { preserveState: true, replace: true })
})
</script>

<template>
    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Help Center</h1>
        <p class="text-gray-500 mb-6">Find answers to common questions and guides for using our services.</p>

        <!-- Search -->
        <div class="relative mb-8">
            <input v-model="query" type="search" placeholder="Search articles…"
                class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm pl-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 shadow-sm" />
            <svg class="absolute left-3 top-3.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        <!-- Search results -->
        <div v-if="search" class="space-y-3">
            <p class="text-sm text-gray-500">Results for <span class="font-medium text-gray-900">"{{ search }}"</span></p>
            <div v-if="articles.length" class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
                <Link v-for="a in articles" :key="a.id"
                    :href="route('client.kb.show', a.slug)"
                    class="block px-5 py-3.5 hover:bg-gray-50">
                    <p class="font-medium text-gray-900 text-sm">{{ a.title }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ a.category?.name }}</p>
                </Link>
            </div>
            <div v-else class="text-center text-gray-400 text-sm py-8">
                No articles found for "{{ search }}".
            </div>
        </div>

        <!-- Category browse -->
        <div v-else class="space-y-6">
            <div v-for="cat in categories" :key="cat.id"
                class="bg-white rounded-xl border border-gray-200 p-5">
                <h2 class="font-semibold text-gray-900 mb-3">{{ cat.name }}</h2>
                <p v-if="cat.description" class="text-sm text-gray-500 mb-3">{{ cat.description }}</p>
                <ul class="space-y-1.5">
                    <li v-for="a in cat.published_articles" :key="a.id">
                        <Link :href="route('client.kb.show', a.slug)"
                            class="text-sm text-indigo-600 hover:underline">
                            {{ a.title }}
                        </Link>
                    </li>
                </ul>
                <p v-if="!cat.published_articles?.length" class="text-sm text-gray-400">No articles yet.</p>
            </div>
            <div v-if="!categories.length" class="text-center text-gray-400 text-sm py-12">
                No knowledge base articles available yet.
            </div>
        </div>
    </div>
</template>
