<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';

const props = defineProps({
    logs:    Object,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');

const doSearch = useDebounceFn(() => {
    router.get(route('admin.email-log.index'), { search: search.value || undefined }, {
        preserveState: true,
        replace: true,
    });
}, 300);

watch(search, doSearch);
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Email Log</h1>
        </template>

        <div class="space-y-4">
            <!-- Search -->
            <div class="flex items-center gap-3">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search by recipient or subject…"
                    class="w-72 rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                />
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">To</th>
                            <th class="px-4 py-3 text-left">Subject</th>
                            <th class="px-4 py-3 text-left">Client</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ log.sent_at }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ log.to }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ log.subject }}</td>
                            <td class="px-4 py-3">
                                <Link
                                    v-if="log.user"
                                    :href="route('admin.clients.show', log.user.id)"
                                    class="text-indigo-600 hover:underline"
                                >{{ log.user.name }}</Link>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="route('admin.email-log.show', log.id)"
                                    class="text-xs text-indigo-600 hover:underline"
                                >View</Link>
                            </td>
                        </tr>
                        <tr v-if="!logs.data.length">
                            <td colspan="5" class="py-10 text-center text-sm text-gray-400">No emails logged yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="logs.last_page > 1" class="flex items-center justify-between text-sm text-gray-600">
                <span>Page {{ logs.current_page }} of {{ logs.last_page }}</span>
                <div class="flex gap-2">
                    <Link
                        v-if="logs.prev_page_url"
                        :href="logs.prev_page_url"
                        class="rounded border border-gray-200 px-3 py-1 hover:bg-gray-50"
                    >Previous</Link>
                    <Link
                        v-if="logs.next_page_url"
                        :href="logs.next_page_url"
                        class="rounded border border-gray-200 px-3 py-1 hover:bg-gray-50"
                    >Next</Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
