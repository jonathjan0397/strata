<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    tickets:     Object,
    departments: Array,
    staff:       Array,
    filters:     Object,
})

const search     = ref(props.filters?.search      ?? '')
const status     = ref(props.filters?.status      ?? '')
const priority   = ref(props.filters?.priority    ?? '')
const department = ref(props.filters?.department  ?? '')
const assignedTo = ref(props.filters?.assigned_to ?? '')

watch([search, status, priority, department, assignedTo], ([s, st, p, d, a]) => {
    router.get(route('admin.support.index'),
        { search: s, status: st, priority: p, department: d, assigned_to: a },
        { preserveState: true, replace: true }
    )
})

// Bulk selection
const selected   = ref([])
const selectAll  = ref(false)
const bulkAction = ref('')
const bulkAssign = ref('')
const bulkForm   = useForm({ action: '', ids: [], value: '' })

function toggleAll() {
    selected.value = selectAll.value ? props.tickets.data.map(t => t.id) : []
}

function submitBulk() {
    if (! bulkAction.value || ! selected.value.length) return
    bulkForm.action = bulkAction.value
    bulkForm.ids    = selected.value
    bulkForm.value  = bulkAssign.value
    bulkForm.post(route('admin.support.bulk'), {
        onSuccess: () => { selected.value = []; selectAll.value = false }
    })
}

const priorityColor = {
    urgent: 'text-red-600 font-semibold',
    high:   'text-orange-500 font-medium',
    medium: 'text-gray-700',
    low:    'text-gray-400',
}

// SLA: hours by priority before a ticket is considered overdue
const SLA_HOURS = { urgent: 4, high: 8, medium: 24, low: 72 }

function slaStatus(ticket) {
    if (['closed', 'on_hold'].includes(ticket.status)) return null
    const slaMs = (SLA_HOURS[ticket.priority] ?? 24) * 3600 * 1000
    const ref   = ticket.last_reply_at ? new Date(ticket.last_reply_at) : new Date(ticket.created_at)
    const ratio = (Date.now() - ref.getTime()) / slaMs
    if (ratio >= 1)    return 'overdue'
    if (ratio >= 0.75) return 'warning'
    return 'ok'
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
            <select v-model="assignedTo" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Agents</option>
                <option value="me">Assigned to me</option>
                <option value="0">Unassigned</option>
                <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
        </div>

        <!-- Bulk action bar -->
        <Transition enter-from-class="opacity-0 -translate-y-1" enter-active-class="transition-all duration-150"
                    leave-to-class="opacity-0 -translate-y-1"   leave-active-class="transition-all duration-150">
            <div v-if="selected.length" class="mb-3 flex items-center gap-3 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-2.5 text-sm">
                <span class="text-indigo-700 font-medium">{{ selected.length }} selected</span>
                <select v-model="bulkAction" class="border border-indigo-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none">
                    <option value="">Choose action…</option>
                    <option value="close">Close tickets</option>
                    <option value="reopen">Reopen tickets</option>
                    <option value="assign">Assign to…</option>
                    <option value="delete">Delete tickets</option>
                </select>
                <select v-if="bulkAction === 'assign'" v-model="bulkAssign"
                    class="border border-indigo-300 rounded-lg px-3 py-1.5 text-sm bg-white focus:outline-none">
                    <option value="">Unassign</option>
                    <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <button @click="submitBulk" :disabled="!bulkAction || bulkForm.processing"
                    class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-xs font-medium px-3 py-1.5 rounded-lg">
                    Apply
                </button>
                <button @click="selected = []; selectAll = false" class="text-gray-400 hover:text-gray-600 text-xs ml-auto">
                    Clear
                </button>
            </div>
        </Transition>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="pl-4 py-3 w-8">
                            <input type="checkbox" v-model="selectAll" @change="toggleAll"
                                class="rounded border-gray-300 text-indigo-600" />
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Subject</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Client</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Department</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Priority</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500">Assigned</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-500">Last Reply</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="t in tickets.data" :key="t.id" class="hover:bg-gray-50"
                        :class="{
                            'bg-red-50/40':    slaStatus(t) === 'overdue',
                            'bg-amber-50/40':  slaStatus(t) === 'warning',
                        }">
                        <td class="pl-4 py-3">
                            <input type="checkbox" :value="t.id" v-model="selected"
                                class="rounded border-gray-300 text-indigo-600" />
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span v-if="slaStatus(t) === 'overdue'" title="SLA overdue"
                                    class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0 inline-block"></span>
                                <span v-else-if="slaStatus(t) === 'warning'" title="Approaching SLA"
                                    class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0 inline-block"></span>
                                <Link :href="route('admin.support.show', t.id)"
                                    class="text-indigo-600 hover:underline font-medium truncate max-w-xs">
                                    {{ t.subject }}
                                </Link>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ t.user?.name }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ t.department?.name ?? t.department ?? '—' }}</td>
                        <td class="px-4 py-3 capitalize" :class="priorityColor[t.priority]">{{ t.priority }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ t.assigned_to?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right"><StatusBadge :status="t.status" /></td>
                        <td class="px-4 py-3 text-right text-gray-400 text-xs">
                            {{ t.last_reply_at ? new Date(t.last_reply_at).toLocaleDateString() : '—' }}
                        </td>
                    </tr>
                    <tr v-if="!tickets.data.length">
                        <td colspan="8" class="px-4 py-8 text-center text-gray-400">No tickets found.</td>
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

        <!-- SLA legend -->
        <div class="mt-4 flex items-center gap-4 text-xs text-gray-400">
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span> SLA overdue
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span> Approaching (75%+)
            </span>
            <span class="text-gray-300">· Urgent 4h · High 8h · Medium 24h · Low 72h</span>
        </div>
    </div>
</template>
