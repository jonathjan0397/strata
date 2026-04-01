<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, usePage } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    feature:    { type: Object, required: true },
    trial_used: { type: Boolean, default: false },
})

const license  = usePage().props.license
const starting = ref(false)
const trialErr = ref(null)

function startTrial() {
    starting.value = true
    trialErr.value = null
    router.post(route('admin.settings.license-trial'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            // After trial starts, navigate back to the feature
            router.visit(window.location.href)
        },
        onError: () => {
            trialErr.value = 'Could not activate trial — check your license server connection.'
            starting.value = false
        },
        onFinish: () => { starting.value = false },
    })
}
</script>

<template>
    <div class="max-w-2xl mx-auto py-16 px-4">
        <!-- Feature icon + title -->
        <div class="flex flex-col items-center text-center mb-10">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-900/40 border border-indigo-700/50 mb-5 shadow-lg shadow-indigo-900/20">
                <svg class="h-8 w-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="feature.icon" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">{{ feature.title }}</h1>
            <p class="text-gray-400 text-base max-w-md">{{ feature.tagline }}</p>
        </div>

        <!-- Feature bullets -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 mb-8">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4">What's included</p>
            <ul class="space-y-3">
                <li v-for="bullet in feature.bullets" :key="bullet" class="flex items-start gap-3">
                    <svg class="h-5 w-5 text-indigo-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm text-gray-300">{{ bullet }}</span>
                </li>
            </ul>
        </div>

        <!-- License status -->
        <div v-if="license.managed" class="mb-6">
            <div
                class="rounded-xl border px-4 py-3 text-sm flex items-center gap-3"
                :class="license.active
                    ? 'border-emerald-800/50 bg-emerald-900/10 text-emerald-300'
                    : 'border-red-800/50 bg-red-900/10 text-red-300'"
            >
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
                <span>
                    License status: <span class="font-semibold">{{ license.status }}</span>
                    <template v-if="license.expires_in_days !== null">
                        — trial expires in <span class="font-semibold">{{ license.expires_in_days }} day{{ license.expires_in_days === 1 ? '' : 's' }}</span>
                    </template>
                </span>
            </div>
        </div>

        <!-- Error -->
        <div v-if="trialErr" class="mb-4 rounded-lg border border-red-800/50 bg-red-900/10 px-4 py-3 text-sm text-red-300">
            {{ trialErr }}
        </div>

        <!-- CTAs -->
        <div class="flex flex-col sm:flex-row gap-3">
            <!-- Start Free Trial -->
            <button
                v-if="license.managed && !trial_used"
                @click="startTrial"
                :disabled="starting"
                class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 px-5 py-3 text-sm font-semibold text-white transition-colors"
            >
                <svg v-if="starting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                {{ starting ? 'Activating…' : 'Start 14-Day Free Trial' }}
            </button>

            <div
                v-else-if="license.managed && trial_used"
                class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg border border-gray-700 bg-gray-800/50 px-5 py-3 text-sm text-gray-400"
            >
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
                Trial already used — upgrade to a paid license
            </div>

            <!-- Manage license in Settings -->
            <a
                v-if="license.managed"
                :href="route('admin.settings.index')"
                class="flex-1 inline-flex items-center justify-center rounded-lg border border-gray-700 hover:border-gray-500 px-5 py-3 text-sm font-medium text-gray-300 hover:text-white transition-colors"
            >
                Manage License in Settings
            </a>

            <!-- No license server configured — features are free -->
            <div v-if="!license.managed" class="text-sm text-gray-500 text-center">
                All features are included with this installation.
            </div>
        </div>
    </div>
</template>
