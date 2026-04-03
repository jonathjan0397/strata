<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    // Period selector state
    period: String,
    year: Number,
    month: String,
    periodLabel: String,
    periodStart: String,
    periodEnd: String,
    availableYears: Array,
    availableMonths: Array,
    // Revenue
    mrr: Number,
    arr: Number,
    revenueChart: Array,
    chartIsDaily: Boolean,
    periodRevenue: Number,
    periodInvoiceCount: Number,
    thisMonth: Number,
    lastMonth: Number,
    revenueGrowth: Number,
    unpaidTotal: Number,
    overdueTotal: Number,
    unpaidCount: Number,
    overdueCount: Number,
    // Clients
    totalClients: Number,
    newThisMonth: Number,
    activeClients: Number,
    clientsByMonth: Array,
    topClients: Array,
    // Services / Support
    serviceStats: Object,
    openTickets: Number,
    avgResolutionHours: Number,
    avgRating: Number,
    ratingDist: Object,
    totalRated: Number,
    ratingsByStaff: Array,
});

const fmt = (n) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n ?? 0);
const fmtNum = (n) => new Intl.NumberFormat('en-US').format(n ?? 0);

function barWidth(value, arr, key) {
    const max = Math.max(...arr.map(r => r[key] ?? 0), 1);
    return ((value ?? 0) / max) * 100;
}

// ── Period selector ──────────────────────────────────────────────────────────
const selectedPeriod = ref(props.period);
const selectedYear   = ref(props.year);
const selectedMonth  = ref(props.month);

const PRESETS = [
    { value: 'current_month',  label: 'This Month' },
    { value: 'last_month',     label: 'Last Month' },
    { value: 'last_12_months', label: 'Last 12 Months' },
    { value: 'ytd',            label: 'Year to Date' },
    { value: 'last_year',      label: 'Last Year' },
    { value: 'all_time',       label: 'All Time' },
];

const needsYear  = computed(() => selectedPeriod.value === 'specific_year');
const needsMonth = computed(() => selectedPeriod.value === 'specific_month');

function applyPeriod() {
    router.get(route('reports.index'), {
        period: selectedPeriod.value,
        year:   needsYear.value  ? selectedYear.value  : undefined,
        month:  needsMonth.value ? selectedMonth.value : undefined,
    }, { preserveState: false, replace: true });
}

watch(selectedPeriod, (val) => {
    if (!['specific_year', 'specific_month'].includes(val)) {
        applyPeriod();
    }
});

// ── Export URLs ──────────────────────────────────────────────────────────────
const exportParams = computed(() => {
    const p = new URLSearchParams({ period: selectedPeriod.value });
    if (needsYear.value)  p.set('year', selectedYear.value);
    if (needsMonth.value) p.set('month', selectedMonth.value);
    return p.toString();
});

const exportInvoicesUrl = computed(() => `/admin/reports/export?type=invoices&${exportParams.value}`);
const exportSummaryUrl  = computed(() => `/admin/reports/export?type=summary&${exportParams.value}`);

// ── Month label helper ───────────────────────────────────────────────────────
function monthLabel(ym) {
    const [y, m] = ym.split('-');
    return new Date(y, m - 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
}
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Reports &amp; Analytics</h1>
        </template>

        <div class="space-y-8">

            <!-- ── Period selector bar ── -->
            <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Quick presets -->
                    <button
                        v-for="p in PRESETS" :key="p.value"
                        @click="selectedPeriod = p.value"
                        class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                        :class="selectedPeriod === p.value
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    >{{ p.label }}</button>

                    <!-- Specific year -->
                    <button
                        @click="selectedPeriod = 'specific_year'"
                        class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                        :class="selectedPeriod === 'specific_year'
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    >By Year</button>

                    <!-- Specific month -->
                    <button
                        @click="selectedPeriod = 'specific_month'"
                        class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
                        :class="selectedPeriod === 'specific_month'
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    >By Month</button>

                    <!-- Year dropdown (shown when specific_year) -->
                    <template v-if="needsYear">
                        <select
                            v-model="selectedYear"
                            class="rounded border border-gray-300 py-1 pl-2 pr-6 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <button
                            @click="applyPeriod"
                            class="rounded bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700"
                        >Go</button>
                    </template>

                    <!-- Month dropdown (shown when specific_month) -->
                    <template v-if="needsMonth">
                        <select
                            v-model="selectedMonth"
                            class="rounded border border-gray-300 py-1 pl-2 pr-6 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                            <option v-for="m in availableMonths" :key="m" :value="m">{{ monthLabel(m) }}</option>
                        </select>
                        <button
                            @click="applyPeriod"
                            class="rounded bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700"
                        >Go</button>
                    </template>

                    <!-- Export buttons -->
                    <div class="ml-auto flex items-center gap-2">
                        <span class="text-xs text-gray-400">Export:</span>
                        <a
                            :href="exportInvoicesUrl"
                            class="rounded border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50"
                        >CSV — Invoices</a>
                        <a
                            :href="exportSummaryUrl"
                            class="rounded border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50"
                        >CSV — Summary</a>
                    </div>
                </div>
            </div>

            <!-- ── Period revenue KPI ── -->
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-5 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-indigo-600">{{ periodLabel }}</p>
                        <p class="mt-1 text-3xl font-bold text-indigo-900">{{ fmt(periodRevenue) }}</p>
                        <p class="mt-0.5 text-xs text-indigo-600">{{ fmtNum(periodInvoiceCount) }} paid invoice{{ periodInvoiceCount !== 1 ? 's' : '' }}</p>
                    </div>
                    <div class="text-right text-xs text-indigo-500">
                        <p>{{ periodStart }}</p>
                        <p>→ {{ periodEnd }}</p>
                    </div>
                </div>
            </div>

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

            <!-- ── Revenue chart ── -->
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-sm font-semibold text-gray-900">Revenue — {{ periodLabel }}</h2>
                <div class="space-y-1.5">
                    <div v-for="row in revenueChart" :key="row.period" class="flex items-center gap-3">
                        <span class="w-16 shrink-0 text-right text-xs text-gray-500">{{ row.label }}</span>
                        <div class="relative h-5 flex-1 rounded bg-gray-100">
                            <div
                                class="h-5 rounded bg-indigo-500 transition-all"
                                :style="{ width: barWidth(row.revenue, revenueChart, 'revenue') + '%' }"
                            />
                        </div>
                        <span class="w-24 shrink-0 text-right text-xs font-medium text-gray-700">{{ fmt(row.revenue) }}</span>
                        <span class="w-12 shrink-0 text-right text-xs text-gray-400">{{ row.invoice_count }} inv</span>
                    </div>
                    <div v-if="!revenueChart?.length" class="py-6 text-center text-sm text-gray-400">
                        No paid invoices in this period.
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

            <!-- ── Satisfaction Ratings ── -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="mb-4 text-sm font-semibold text-gray-900">Client Satisfaction</h2>
                    <div v-if="totalRated > 0">
                        <div class="flex items-baseline gap-3 mb-4">
                            <span class="text-4xl font-bold text-gray-900">{{ avgRating?.toFixed(1) }}</span>
                            <div>
                                <div class="flex gap-0.5 text-amber-400 text-lg leading-none">
                                    <span v-for="i in 5" :key="i">{{ i <= Math.round(avgRating) ? '★' : '☆' }}</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">{{ fmtNum(totalRated) }} rated tickets</p>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <div v-for="star in [5,4,3,2,1]" :key="star" class="flex items-center gap-2 text-xs">
                                <span class="w-3 text-gray-500 text-right">{{ star }}</span>
                                <span class="text-amber-400 text-xs">★</span>
                                <div class="flex-1 bg-gray-100 rounded-full h-2">
                                    <div class="bg-amber-400 h-2 rounded-full transition-all"
                                        :style="{ width: totalRated > 0 ? ((ratingDist[star] ?? 0) / totalRated * 100).toFixed(1) + '%' : '0%' }">
                                    </div>
                                </div>
                                <span class="w-6 text-gray-500 text-right">{{ ratingDist[star] ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No ratings yet.</p>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="mb-4 text-sm font-semibold text-gray-900">Ratings by Staff</h2>
                    <div v-if="ratingsByStaff?.length" class="space-y-3">
                        <div v-for="row in ratingsByStaff" :key="row.staff"
                            class="flex items-center justify-between text-sm">
                            <span class="text-gray-700 truncate flex-1">{{ row.staff }}</span>
                            <div class="flex items-center gap-2 shrink-0">
                                <div class="flex gap-0.5 text-amber-400 text-xs">
                                    <span v-for="i in 5" :key="i">{{ i <= Math.round(row.avg_rating) ? '★' : '☆' }}</span>
                                </div>
                                <span class="font-semibold text-gray-900 w-8 text-right">{{ row.avg_rating }}</span>
                                <span class="text-xs text-gray-400 w-16 text-right">({{ row.ticket_count }})</span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No staff ratings data yet.</p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
