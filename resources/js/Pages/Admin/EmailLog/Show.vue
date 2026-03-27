<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    log: Object,
});
</script>

<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.email-log.index')" class="text-sm text-indigo-600 hover:underline">← Email Log</Link>
                <h1 class="text-lg font-semibold text-gray-900">Email #{{ log.id }}</h1>
            </div>
        </template>

        <div class="max-w-3xl space-y-4">
            <div class="rounded-lg border border-gray-200 bg-white divide-y divide-gray-100">
                <div class="grid grid-cols-3 gap-4 px-6 py-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">To</p>
                        <p class="mt-1 text-sm text-gray-900">{{ log.to }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Subject</p>
                        <p class="mt-1 text-sm text-gray-900">{{ log.subject }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Sent At</p>
                        <p class="mt-1 text-sm text-gray-900">{{ log.sent_at }}</p>
                    </div>
                </div>

                <div v-if="log.user" class="px-6 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Client</p>
                    <Link :href="route('admin.clients.show', log.user.id)" class="mt-1 text-sm text-indigo-600 hover:underline">
                        {{ log.user.name }} ({{ log.user.email }})
                    </Link>
                </div>
            </div>

            <!-- Body -->
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500">Body</p>
                <!-- Render HTML email if present, otherwise plain text -->
                <div
                    v-if="log.body"
                    class="prose prose-sm max-w-none rounded border border-gray-100 bg-gray-50 p-4 text-sm"
                    v-html="log.body"
                />
                <p v-else class="text-sm text-gray-400">No body content.</p>
            </div>
        </div>
    </AppLayout>
</template>
