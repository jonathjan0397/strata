<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    tickets:     Object,
    departments: Array,
    filters:     Object,
})

const search     = ref(props.filters?.search     ?? '')
const status     = ref(props.filters?.status     ?? '')
const priority   = ref(props.filters?.priority   ?? '')
const department = ref(props.filters?.department ?? '')

watch([search, status, priority, department], ([s, st, p, d]) => {
    router.get(route('admin.support.index'),
        { search: s, status: st, priority: p, department: d },
        { preserveState: true, replace: true }
    )
})

const priorityColor = {
    urgent: 'text-red-600 font-semibold',
    high:   'text-orange-500 font-medium',
    medium: 'text-gray-700',
    low:    'text-gray-400',
}
</script>

<template>
    <div>
        <h1 class="text-xl font-bold text-gray-900 mb-6">Support Tickets</h1>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-4">
            <input v-model="search" type="search" placeholder="Search…"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <select v-model="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                <option v-for="s in ['open','answered','customer_reply','on_hold','closed']" :key="s" :value="s">
                    {{ s.replace(/_/g, ' ') }}
                </option>
            </select>
            <select v-model="priority" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Priorities</option>
                <option v-for="p in ['urgent','high','medium','low']" :key="p" :value="p" class="capitalize">{{ p }}</option>
            </select>
            <select v-model="department" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Departments</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Subject</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Client</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Department</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Priority</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Last Reply</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <Link :href="route('admin.support.show', t.id)"
                                class="text-indigo-600 hover:underline font-medium">{{ t.subject }}</Link>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ t.user?.name }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">
                            {{ t.department?.name ?? t.department ?? '—' }}
                        </td>
                        <td class="px-4 py-3 capitalize" :class="priorityColor[t.priority]">{{ t.priority }}</td>
                        <td class="px-4 py-3 text-right"><StatusBadge :status="t.status" /></td>
                        <td class="px-4 py-3 text-right text-gray-400 text-xs">
                            {{ t.last_reply_at ? new Date(t.last_reply_at).toLocaleDateString() : '—' }}
                        </td>
                    </tr>
                    <tr v-if="!tickets.data.length">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">No tickets found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="tickets.last_page > 1" class="mt-4 flex gap-2">
            <Link v-for="link in tickets.links" :key="link.label"
                :href="link.url ?? '#'"
                class="px-3 py-1 text-sm border rounded"
                :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                v-html="link.label" />
        </div>
    </div>
</template>
