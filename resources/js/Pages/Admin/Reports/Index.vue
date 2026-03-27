<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    mrr: Number,
    arr: Number,
    revenueByMonth: Array,
    thisMonth: Number,
    lastMonth: Number,
    revenueGrowth: Number,
    unpaidTotal: Number,
    overdueTotal: Number,
    unpaidCount: Number,
    overdueCount: Number,
    totalClients: Number,
    newThisMonth: Number,
    activeClients: Number,
    clientsByMonth: Array,
    topClients: Array,
    serviceStats: Object,
    openTickets: Number,
    avgResolutionHours: Number,
});

const fmt = (n) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n ?? 0);
const fmtNum = (n) => new Intl.NumberFormat('en-US').format(n ?? 0);

// Simple bar chart helper — returns width % relative to max in set
function barWidth(value, arr, key) {
    const max = Math.max(...arr.map(r => r[key] ?? 0), 1);
    return ((value ?? 0) / max) * 100;
}
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Reports &amp; Analytics</h1>
        </template>

        <div class="space-y-8">

            <!-- ── KPI row ── -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">MRR</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ fmt(mrr) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">ARR</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ fmt(arr) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">This Month</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ fmt(thisMonth) }}</p>
                    <p v-if="revenueGrowth !== null" class="mt-1 text-xs" :class="revenueGrowth >= 0 ? 'text-green-600' : 'text-red-600'">
                        {{ revenueGrowth >= 0 ? '▲' : '▼' }} {{ Math.abs(revenueGrowth) }}% vs last month
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Last Month</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ fmt(lastMonth) }}</p>
                </div>
            </div>

            <!-- ── Outstanding ── -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Unpaid Invoices</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-900">{{ fmt(unpaidTotal) }}</p>
                    <p class="mt-1 text-xs text-amber-700">{{ fmtNum(unpaidCount) }} invoice{{ unpaidCount !== 1 ? 's' : '' }}</p>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50 p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-red-700">Overdue Invoices</p>
                    <p class="mt-1 text-2xl font-semibold text-red-900">{{ fmt(overdueTotal) }}</p>
                    <p class="mt-1 text-xs text-red-700">{{ fmtNum(overdueCount) }} invoice{{ overdueCount !== 1 ? 's' : '' }}</p>
                </div>
            </div>

            <!-- ── Revenue by Month ── -->
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-sm font-semibold text-gray-900">Revenue — Last 12 Months</h2>
                <div class="space-y-2">
                    <div v-for="row in revenueByMonth" :key="row.month" class="flex items-center gap-3">
                        <span class="w-16 shrink-0 text-right text-xs text-gray-500">{{ row.label }}</span>
                        <div class="relative h-5 flex-1 rounded bg-gray-100">
                            <div
                                class="h-5 rounded bg-indigo-500 transition-all"
                                :style="{ width: barWidth(row.revenue, revenueByMonth, 'revenue') + '%' }"
                            />
                        </div>
                        <span class="w-24 shrink-0 text-right text-xs font-medium text-gray-700">{{ fmt(row.revenue) }}</span>
                        <span class="w-12 shrink-0 text-right text-xs text-gray-400">{{ row.invoice_count }} inv</span>
                    </div>
                </div>
            </div>

            <!-- ── Client stats ── -->
            <div class="grid grid-cols-3 gap-4">
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Clients</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ fmtNum(totalClients) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Active Clients</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ fmtNum(activeClients) }}</p>
                    <p class="mt-1 text-xs text-gray-500">(have active service)</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">New This Month</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ fmtNum(newThisMonth) }}</p>
                </div>
            </div>

            <!-- ── New Clients by Month ── -->
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-sm font-semibold text-gray-900">New Clients — Last 6 Months</h2>
                <div class="space-y-2">
                    <div v-for="row in clientsByMonth" :key="row.label" class="flex items-center gap-3">
                        <span class="w-16 shrink-0 text-right text-xs text-gray-500">{{ row.label }}</span>
                        <div class="relative h-5 flex-1 rounded bg-gray-100">
                            <div
                                class="h-5 rounded bg-emerald-500 transition-all"
                                :style="{ width: barWidth(row.count, clientsByMonth, 'count') + '%' }"
                            />
                        </div>
                        <span class="w-10 shrink-0 text-right text-xs font-medium text-gray-700">{{ row.count }}</span>
                    </div>
                </div>
            </div>

            <!-- ── Top Clients ── -->
            <div class="rounded-lg border border-gray-200 bg-white">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Top Clients by Lifetime Revenue</h2>
                </div>
                <div class="divide-y divide-gray-50">
                    <div v-for="(row, i) in topClients" :key="row.user?.id ?? i" class="flex items-center justify-between px-6 py-3">
                        <div class="flex items-center gap-3">
                            <span class="w-5 text-xs font-semibold text-gray-400">{{ i + 1 }}</span>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ row.user?.name ?? '—' }}</p>
                                <p class="text-xs text-gray-500">{{ row.user?.email }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">{{ fmt(row.lifetime_revenue) }}</p>
                            <p class="text-xs text-gray-500">{{ row.invoice_count }} paid invoice{{ row.invoice_count !== 1 ? 's' : '' }}</p>
                        </div>
                    </div>
                    <div v-if="!topClients.length" class="px-6 py-8 text-center text-sm text-gray-400">
                        No paid invoices yet.
                    </div>
                </div>
            </div>

            <!-- ── Service + Support ── -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <!-- Service status breakdown -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="mb-4 text-sm font-semibold text-gray-900">Services by Status</h2>
                    <dl class="space-y-2">
                        <div v-for="(count, status) in serviceStats" :key="status" class="flex justify-between text-sm">
                            <dt class="capitalize text-gray-600">{{ status }}</dt>
                            <dd class="font-medium text-gray-900">{{ fmtNum(count) }}</dd>
                        </div>
                        <div v-if="!Object.keys(serviceStats).length" class="text-xs text-gray-400">No services found.</div>
                    </dl>
                </div>

                <!-- Support stats -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="mb-4 text-sm font-semibold text-gray-900">Support</h2>
                    <dl class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <dt class="text-gray-600">Open Tickets</dt>
                            <dd class="font-semibold text-gray-900">{{ fmtNum(openTickets) }}</dd>
                        </div>
                        <div class="flex justify-between text-sm">
                            <dt class="text-gray-600">Avg. Resolution Time</dt>
                            <dd class="font-semibold text-gray-900">
                                {{ avgResolutionHours !== null ? avgResolutionHours + 'h' : '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
