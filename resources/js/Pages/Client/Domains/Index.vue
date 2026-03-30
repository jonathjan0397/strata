<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

defineProps({ domains: Array })

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
            <h1 class="text-xl font-bold text-gray-900">My Domains</h1>
            <Link :href="route('client.domain-order.search')"
                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                Register New Domain
            </Link>
        </div>

        <div v-if="!domains.length" class="bg-white rounded-xl border border-gray-200 px-6 py-12 text-center">
            <p class="text-gray-500 text-sm mb-4">You don't have any domains yet.</p>
            <Link :href="route('client.domain-order.search')" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                Register a Domain
            </Link>
        </div>

        <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Domain</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Expires</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Auto-Renew</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="d in domains" :key="d.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900 font-mono text-sm">{{ d.name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize"
                                :class="statusClass[d.status] ?? 'bg-gray-100 text-gray-500'">
                                {{ d.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ d.expires_at ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span :class="d.auto_renew ? 'text-green-600' : 'text-gray-400'" class="text-xs font-medium">
                                {{ d.auto_renew ? 'On' : 'Off' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="route('client.domains.show', d.id)" class="text-indigo-600 hover:underline text-xs">Manage</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
