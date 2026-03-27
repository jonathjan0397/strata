<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    domains: Object,
    filters: Object,
})

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

watch([search, status], () => {
    router.get(route('admin.domains.index'), { search: search.value, status: status.value }, {
        preserveState: true, replace: true,
    })
})

const statusClass = {
    active:      'bg-green-100 text-green-700',
    pending:     'bg-yellow-100 text-yellow-700',
    expired:     'bg-red-100 text-red-700',
    cancelled:   'bg-gray-100 text-gray-500',
    transferred: 'bg-blue-100 text-blue-700',
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-gray-900">Domains</h1>
        </div>

        <!-- Filters -->
        <div class="flex gap-3 mb-4">
            <input v-model="search" type="text" placeholder="Search domain…"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <select v-model="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="expired">Expired</option>
                <option value="cancelled">Cancelled</option>
                <option value="transferred">Transferred</option>
            </select>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Domain</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Client</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Registrar</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Expires</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="d in domains.data" :key="d.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900 font-mono text-xs">{{ d.name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ d.user?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500 capitalize">{{ d.registrar ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize"
                                :class="statusClass[d.status] ?? 'bg-gray-100 text-gray-500'">
                                {{ d.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ d.expires_at ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="route('admin.domains.show', d.id)" class="text-indigo-600 hover:underline text-xs">View</Link>
                        </td>
                    </tr>
                    <tr v-if="!domains.data.length">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">No domains found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="domains.last_page > 1" class="mt-4 flex gap-2">
            <Link v-for="link in domains.links" :key="link.label"
                :href="link.url ?? '#'"
                class="px-3 py-1 text-sm border rounded"
                :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                v-html="link.label" />
        </div>
    </div>
</template>
