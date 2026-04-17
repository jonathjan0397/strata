<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { usePage } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({
    feature: { type: Object, required: true },
})

const license = usePage().props.license
</script>

<template>
    <div class="max-w-2xl mx-auto py-12 px-4">
        <div class="flex flex-col items-center text-center mb-8">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-100 border border-indigo-200 mb-4 shadow-sm">
                <svg class="h-7 w-7 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="feature.icon" />
                </svg>
            </div>
            <div class="flex items-center gap-2 mb-2">
                <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-600">Feature Locked</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ feature.title }}</h1>
            <p class="text-gray-500 text-base max-w-md">{{ feature.tagline }}</p>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-6 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">What this feature includes</p>
            <ul class="space-y-3">
                <li v-for="bullet in feature.bullets" :key="bullet" class="flex items-start gap-3">
                    <svg class="h-5 w-5 text-indigo-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm text-gray-700">{{ bullet }}</span>
                </li>
            </ul>
        </div>

        <div v-if="license.managed" class="mb-6 rounded-xl border px-4 py-3 text-sm flex items-center gap-3"
            :class="license.active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700'">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
            </svg>
            <span>License status: <span class="font-semibold">{{ license.status }}</span></span>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <a
                v-if="license.managed"
                :href="route('admin.settings.index')"
                class="flex-1 inline-flex items-center justify-center rounded-lg border border-gray-300 hover:border-gray-400 hover:bg-gray-50 px-5 py-2.5 text-sm font-medium text-gray-700 transition-colors"
            >
                Review License Status in Settings
            </a>

            <div v-else class="flex-1 text-sm text-gray-500 text-center py-2">
                This installation is not connected to a managed license server.
            </div>
        </div>
    </div>
</template>
