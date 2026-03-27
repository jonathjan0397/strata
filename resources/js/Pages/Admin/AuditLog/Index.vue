<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';

const props = defineProps({
    logs: Object,
    staff: Array,
    filters: Object,
});

const f = reactive({ ...props.filters });

function applyFilters() {
    router.get(route('admin.audit-log.index'), f, { preserveState: true, replace: true });
}

function clearFilters() {
    Object.keys(f).forEach(k => f[k] = '');
    applyFilters();
}

const actionColor = (action) => {
    if (action.includes('delete') || action.includes('suspend') || action.includes('cancel')) return 'bg-red-50 text-red-700';
    if (action.includes('create') || action.includes('register') || action.includes('paid')) return 'bg-green-50 text-green-700';
    return 'bg-gray-100 text-gray-600';
};
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Audit Log</h1>
        </template>

        <div class="max-w-7xl mx-auto space-y-4">

            <!-- Filters -->
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <input v-model="f.action" placeholder="Action…" class="col-span-2 md:col-span-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <select v-model="f.user_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Any actor</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <input v-model="f.target_type" placeholder="Target type…" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <input v-model="f.from" type="date" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <div class="flex gap-2">
                        <input v-model="f.to" type="date" class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        <button @click="applyFilters" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700">Filter</button>
                        <button @click="clearFilters" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Clear</button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">When</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Target</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap text-xs">{{ log.created_at }}</td>
                            <td class="px-4 py-3">
                                <span v-if="log.user" class="font-medium text-gray-900">{{ log.user.name }}</span>
                                <span v-else class="text-gray-400 italic">System</span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex items-center rounded px-2 py-0.5 text-xs font-medium', actionColor(log.action)]">
                                    {{ log.action }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <span v-if="log.target_type">{{ log.target_type }} #{{ log.target_id }}</span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs font-mono">{{ log.ip_address ?? '—' }}</td>
                        </tr>
                        <tr v-if="!logs.data.length">
                            <td colspan="5" class="px-4 py-10 text-center text-gray-500">No log entries found.</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="logs.last_page > 1" class="border-t border-gray-200 px-4 py-3 flex items-center justify-between">
                    <p class="text-xs text-gray-500">{{ logs.total }} entries</p>
                    <div class="flex gap-1">
                        <a
                            v-for="link in logs.links"
                            :key="link.label"
                            :href="link.url"
                            v-html="link.label"
                            :class="['px-3 py-1 text-xs rounded', link.active ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url ? 'opacity-40 pointer-events-none' : '']"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
