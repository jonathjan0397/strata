<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router, usePage, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    invoice:  Object,
    currency: { type: String, default: '$' },
})

const page  = usePage()
const flash = computed(() => page.props.flash)

const sending      = ref(false)
const showCNForm   = ref(false)

const cnForm = useForm({
    amount:      '',
    reason:      '',
    disposition: 'balance',
    notes:       '',
})

function submitCreditNote() {
    cnForm.post(route('admin.invoices.credit-notes.store', props.invoice.id), {
        onSuccess: () => { showCNForm.value = false; cnForm.reset() },
    })
}

function voidCreditNote(cnId) {
    if (confirm('Void this credit note? This will reverse any balance credited.')) {
        router.post(route('admin.invoices.credit-notes.void', { invoice: props.invoice.id, creditNote: cnId }))
    }
}

function fmt(val) {
    return props.currency + Number(val ?? 0).toFixed(2)
}

function sendEmail() {
    sending.value = true
    router.post(route('admin.invoices.send', props.invoice.id), {}, {
        onFinish: () => sending.value = false,
    })
}
</script>

<template>
    <div class="max-w-3xl">
        <div class="flex items-center gap-3 mb-6">
            <Link :href="route('admin.invoices.index')" class="text-sm text-gray-500 hover:text-gray-700">← Invoices</Link>
            <span class="text-gray-300">/</span>
            <h1 class="text-xl font-bold text-gray-900">Invoice #{{ invoice.id }}</h1>
            <StatusBadge :status="invoice.status" />
        </div>

        <!-- Flash messages -->
        <div v-if="flash?.success" class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ flash.success }}
        </div>
        <div v-if="flash?.error" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
            {{ flash.error }}
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-4">

            <!-- Header band -->
            <div class="bg-indigo-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-indigo-200 text-xs font-semibold uppercase tracking-wider mb-1">Bill To</p>
                        <p class="text-white font-semibold text-sm">{{ invoice.user?.name }}</p>
                        <p class="text-indigo-200 text-xs">{{ invoice.user?.email }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-indigo-200 text-xs mb-1">Invoice #{{ invoice.id }}</p>
                        <p class="text-indigo-100 text-xs">Date: {{ invoice.date }}</p>
                        <p class="text-indigo-100 text-xs">Due: {{ invoice.due_date }}</p>
                    </div>
                </div>
            </div>

            <div class="p-6">

                <!-- Line items -->
                <table class="min-w-full text-sm mb-0">
                    <thead>
                        <tr class="text-left text-gray-500 text-xs uppercase tracking-wider">
                            <th class="pb-2 font-semibold">Description</th>
                            <th class="pb-2 text-right font-semibold w-16">Qty</th>
                            <th class="pb-2 text-right font-semibold w-24">Unit Price</th>
                            <th class="pb-2 text-right font-semibold w-24">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in invoice.items" :key="item.id">
                            <td class="py-2.5 text-gray-800">{{ item.description }}</td>
                            <td class="py-2.5 text-right text-gray-500">{{ item.quantity }}</td>
                            <td class="py-2.5 text-right text-gray-500">{{ fmt(item.unit_price) }}</td>
                            <td class="py-2.5 text-right font-medium text-gray-900">{{ fmt(item.total) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Totals -->
                <div class="flex justify-end mt-4 pt-4 border-t border-gray-100">
                    <table class="text-sm w-64">
                        <tbody>
                            <tr v-if="Number(invoice.tax) > 0">
                                <td class="py-1 text-gray-500">Subtotal</td>
                                <td class="py-1 text-right text-gray-600">{{ fmt(invoice.subtotal) }}</td>
                            </tr>
                            <tr v-if="Number(invoice.tax) > 0">
                                <td class="py-1 text-gray-500">Tax ({{ invoice.tax_rate }}%)</td>
                                <td class="py-1 text-right text-gray-600">{{ fmt(invoice.tax) }}</td>
                            </tr>
                            <tr class="border-t border-gray-200">
                                <td class="pt-2 pb-1 font-semibold text-gray-700">Total</td>
                                <td class="pt-2 pb-1 text-right font-bold text-gray-900 text-base">{{ fmt(invoice.total) }}</td>
                            </tr>
                            <tr v-if="Number(invoice.credit_applied) > 0">
                                <td class="py-1 text-gray-500">Credit Applied</td>
                                <td class="py-1 text-right text-green-600 font-medium">-{{ fmt(invoice.credit_applied) }}</td>
                            </tr>
                            <tr v-if="Number(invoice.credit_applied) > 0 || Number(invoice.tax) > 0">
                                <td class="pt-1 font-semibold text-indigo-700">Amount Due</td>
                                <td class="pt-1 text-right font-bold text-indigo-700 text-base">{{ fmt(invoice.amount_due) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Notes -->
                <div v-if="invoice.notes" class="mt-5 pt-4 border-t border-gray-100">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Notes</p>
                    <p class="text-sm text-gray-600 whitespace-pre-line">{{ invoice.notes }}</p>
                </div>

                <!-- Actions -->
                <div class="flex gap-2 justify-between items-center mt-6 pt-5 border-t border-gray-100 flex-wrap">
                    <!-- Left: PDF + Send -->
                    <div class="flex gap-2">
                        <a :href="route('admin.invoices.download', invoice.id)" target="_blank"
                            class="flex items-center gap-1.5 text-sm border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Download PDF
                        </a>
                        <button @click="sendEmail" :disabled="sending"
                            class="flex items-center gap-1.5 text-sm border border-indigo-300 text-indigo-700 hover:bg-indigo-50 disabled:opacity-50 px-4 py-2 rounded-lg">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                            {{ sending ? 'Sending…' : 'Email to Client' }}
                        </button>
                    </div>

                    <!-- Right: status actions -->
                    <div class="flex gap-2">
                        <template v-if="invoice.status !== 'paid' && invoice.status !== 'cancelled'">
                            <button class="text-sm bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-lg"
                                @click="router.post(route('admin.invoices.mark-paid', invoice.id))">
                                Mark Paid
                            </button>
                            <button class="text-sm border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg"
                                @click="router.post(route('admin.invoices.cancel', invoice.id))">
                                Cancel
                            </button>
                        </template>
                    </div>
                </div>

            </div><!-- /p-6 -->
        </div>

        <!-- Credit Notes -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-700">Credit Notes</h2>
                <button v-if="!showCNForm && invoice.status !== 'cancelled'"
                    @click="showCNForm = true"
                    class="text-xs text-indigo-600 border border-indigo-200 hover:bg-indigo-50 px-3 py-1.5 rounded-lg">
                    Issue Credit Note
                </button>
            </div>

            <!-- Existing credit notes -->
            <table v-if="invoice.credit_notes?.length" class="min-w-full text-sm mb-4">
                <thead>
                    <tr class="text-xs uppercase tracking-wider text-gray-400">
                        <th class="pb-2 text-left font-semibold">Number</th>
                        <th class="pb-2 text-left font-semibold">Reason</th>
                        <th class="pb-2 text-center font-semibold">Applied To</th>
                        <th class="pb-2 text-center font-semibold">Status</th>
                        <th class="pb-2 text-right font-semibold">Amount</th>
                        <th class="pb-2 text-right font-semibold">Date</th>
                        <th class="pb-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="cn in invoice.credit_notes" :key="cn.id" class="text-gray-600">
                        <td class="py-2 font-mono text-xs">{{ cn.credit_note_number }}</td>
                        <td class="py-2 text-gray-700">{{ cn.reason }}</td>
                        <td class="py-2 text-center text-xs capitalize">
                            {{ cn.disposition === 'balance' ? 'Account Balance' : 'Invoice' }}
                        </td>
                        <td class="py-2 text-center">
                            <span :class="{
                                'bg-green-100 text-green-700': cn.status === 'applied',
                                'bg-yellow-100 text-yellow-700': cn.status === 'issued',
                                'bg-gray-100 text-gray-500': cn.status === 'voided',
                            }" class="text-xs px-2 py-0.5 rounded-full font-medium capitalize">{{ cn.status }}</span>
                        </td>
                        <td class="py-2 text-right font-medium text-gray-800">{{ fmt(cn.amount) }}</td>
                        <td class="py-2 text-right text-gray-400 text-xs">
                            {{ cn.issued_at ? new Date(cn.issued_at).toLocaleDateString() : '—' }}
                        </td>
                        <td class="py-2 text-right">
                            <button v-if="cn.status !== 'voided'" @click="voidCreditNote(cn.id)"
                                class="text-xs text-red-500 hover:text-red-700">Void</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else-if="!showCNForm" class="text-sm text-gray-400 mb-3">No credit notes on this invoice.</p>

            <!-- Issue credit note form -->
            <form v-if="showCNForm" @submit.prevent="submitCreditNote"
                class="border border-gray-200 rounded-lg p-4 space-y-3 bg-gray-50 mt-2">
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">New Credit Note</p>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Amount</label>
                        <input v-model="cnForm.amount" type="number" step="0.01" min="0.01"
                            :max="invoice.total"
                            placeholder="0.00"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required />
                        <p v-if="cnForm.errors.amount" class="mt-1 text-xs text-red-600">{{ cnForm.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Apply To</label>
                        <select v-model="cnForm.disposition" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="balance">Client Account Balance</option>
                            <option value="invoice">This Invoice (reduce amount due)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reason <span class="text-red-500">*</span></label>
                    <input v-model="cnForm.reason" type="text" maxlength="500"
                        placeholder="e.g. Service outage compensation, billing error correction…"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required />
                    <p v-if="cnForm.errors.reason" class="mt-1 text-xs text-red-600">{{ cnForm.errors.reason }}</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Internal Notes</label>
                    <textarea v-model="cnForm.notes" rows="2"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none" />
                </div>

                <div class="flex gap-3 justify-end pt-1">
                    <button type="button" @click="showCNForm = false; cnForm.reset()"
                        class="text-sm text-gray-500 px-4 py-2">Cancel</button>
                    <button type="submit" :disabled="cnForm.processing"
                        class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
                        Issue Credit Note
                    </button>
                </div>
            </form>
        </div>

        <!-- Payment history -->
        <div v-if="invoice.payments?.length" class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Payment History</h2>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-xs uppercase tracking-wider text-gray-400">
                        <th class="pb-2 text-left font-semibold">Gateway</th>
                        <th class="pb-2 text-left font-semibold">Transaction</th>
                        <th class="pb-2 text-center font-semibold">Status</th>
                        <th class="pb-2 text-right font-semibold">Amount</th>
                        <th class="pb-2 text-right font-semibold">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="p in invoice.payments" :key="p.id" class="text-gray-600">
                        <td class="py-2 capitalize">{{ p.gateway }}</td>
                        <td class="py-2 font-mono text-xs text-gray-400 truncate max-w-[140px]">{{ p.transaction_id ?? '—' }}</td>
                        <td class="py-2 text-center">
                            <span :class="[
                                'text-xs px-2 py-0.5 rounded-full font-medium capitalize',
                                p.status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                            ]">{{ p.status }}</span>
                        </td>
                        <td class="py-2 text-right font-medium text-gray-800">{{ fmt(p.amount) }}</td>
                        <td class="py-2 text-right text-gray-400 text-xs">
                            {{ p.paid_at ? new Date(p.paid_at).toLocaleDateString() : '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</template>
