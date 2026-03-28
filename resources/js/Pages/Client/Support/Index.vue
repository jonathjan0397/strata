<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    tickets: Object,
    filters: Object,
})

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

watch([search, status], ([s, st]) => {
    router.get(route('client.support.index'),
        { search: s, status: st },
        { preserveState: true, replace: true }
    )
})

const priorityDot = {
    urgent: 'bg-red-500',
    high:   'bg-orange-400',
    medium: 'bg-yellow-400',
    low:    'bg-gray-300',
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-gray-900">Support Tickets</h1>
            <Link :href="route('client.support.create')"
                class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg">
                New Ticket
            </Link>
        </div>

        <!-- Filters -->
        <div class="flex gap-3 mb-4">
            <input v-model="search" type="search" placeholder="Search tickets…"
                class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <select v-model="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Statuses</option>
                <option v-for="s in ['open','answered','customer_reply','on_hold','closed']" :key="s" :value="s">
                    {{ s.replace(/_/g, ' ') }}
                </option>
            </select>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Subject</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Department</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Priority</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Last Update</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <Link :href="route('client.support.show', t.id)" class="text-indigo-600 hover:underline font-medium">
                                {{ t.subject }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-gray-500 capitalize text-xs">
                            {{ t.department?.name ?? t.department ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="flex items-center gap-1.5">
                                <span :class="['w-2 h-2 rounded-full inline-block', priorityDot[t.priority]]"></span>
                                <span class="capitalize text-xs text-gray-600">{{ t.priority }}</span>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-gray-400 text-xs">
                            {{ t.last_reply_at ? new Date(t.last_reply_at).toLocaleDateString() : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right"><StatusBadge :status="t.status" /></td>
                    </tr>
                    <tr v-if="!tickets.data.length">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">No tickets yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="tickets.last_page > 1" class="mt-4 flex gap-2 flex-wrap">
            <Link v-for="link in tickets.links" :key="link.label"
                :href="link.url ?? '#'"
                class="px-3 py-1 text-sm border rounded"
                :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                v-html="link.label" />
        </div>
    </div>
</template>
