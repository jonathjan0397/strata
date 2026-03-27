<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const justVerified = computed(() => new URLSearchParams(window.location.search).get('verified') === '1');

const stats = [
    { label: 'Active Clients',   value: '—', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', color: 'bg-blue-500' },
    { label: 'Open Invoices',    value: '—', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',                  color: 'bg-amber-500' },
    { label: 'Active Services',  value: '—', icon: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2',         color: 'bg-green-500' },
    { label: 'Open Tickets',     value: '—', icon: 'M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z',                                                     color: 'bg-purple-500' },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Dashboard</h1>
        </template>

        <!-- Email verified banner -->
        <div v-if="justVerified" class="mb-6 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
          Your email address has been verified.
        </div>

        <!-- Stats grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-8">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4"
            >
                <div :class="stat.color" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="stat.icon" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900">{{ stat.value }}</p>
                    <p class="text-sm text-gray-500">{{ stat.label }}</p>
                </div>
            </div>
        </div>

        <!-- Placeholder panels -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Recent Invoices</h2>
                <p class="text-sm text-gray-400 text-center py-8">No invoices yet — billing coming in v0.7</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Open Tickets</h2>
                <p class="text-sm text-gray-400 text-center py-8">No tickets yet — support coming in v2.0</p>
            </div>
        </div>
    </AppLayout>
</template>
