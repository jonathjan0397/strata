<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ domain: Object })

const nsForm = useForm({
    nameservers: [
        props.domain.nameserver_1 ?? '',
        props.domain.nameserver_2 ?? '',
        props.domain.nameserver_3 ?? '',
        props.domain.nameserver_4 ?? '',
    ].filter(Boolean),
})

if (nsForm.nameservers.length < 2) {
    while (nsForm.nameservers.length < 2) nsForm.nameservers.push('')
}

function addNs() {
    if (nsForm.nameservers.length < 6) nsForm.nameservers.push('')
}
function removeNs(i) {
    if (nsForm.nameservers.length > 2) nsForm.nameservers.splice(i, 1)
}

function saveNameservers() {
    nsForm.post(route('client.domains.nameservers', props.domain.id))
}

function toggleAutoRenew() {
    router.post(route('client.domains.auto-renew', props.domain.id))
}

const statusClass = {
    active:      'bg-green-100 text-green-700',
    pending:     'bg-yellow-100 text-yellow-700',
    expired:     'bg-red-100 text-red-700',
    cancelled:   'bg-gray-100 text-gray-500',
    transferred: 'bg-blue-100 text-blue-700',
}
</script>

<template>
    <div class="max-w-2xl">
        <div class="flex items-center gap-3 mb-6">
            <Link :href="route('client.domains.index')" class="text-sm text-gray-500 hover:text-gray-700">← Domains</Link>
            <span class="text-gray-300">/</span>
            <h1 class="text-xl font-bold text-gray-900 font-mono">{{ domain.name }}</h1>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize ml-1"
                :class="statusClass[domain.status] ?? 'bg-gray-100 text-gray-500'">
                {{ domain.status }}
            </span>
        </div>

        <!-- Info card -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5 grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Registrar</p>
                <p class="font-medium capitalize">{{ domain.registrar ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Registered</p>
                <p class="font-medium">{{ domain.registered_at ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-0.5">Expires</p>
                <p class="font-medium">{{ domain.expires_at ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">Auto-Renew</p>
                <div class="flex items-center gap-2">
                    <span :class="domain.auto_renew ? 'text-green-700 font-medium' : 'text-gray-400'" class="text-xs">
                        {{ domain.auto_renew ? 'Enabled' : 'Disabled' }}
                    </span>
                    <button @click="toggleAutoRenew"
                        class="text-xs text-indigo-600 hover:underline">
                        {{ domain.auto_renew ? 'Disable' : 'Enable' }}
                    </button>
                </div>
            </div>
            <div v-if="domain.locked !== null">
                <p class="text-xs text-gray-500 mb-0.5">Transfer Lock</p>
                <p class="font-medium text-xs" :class="domain.locked ? 'text-green-700' : 'text-yellow-600'">
                    {{ domain.locked ? 'Locked' : 'Unlocked' }}
                </p>
            </div>
            <div v-if="domain.privacy !== null">
                <p class="text-xs text-gray-500 mb-0.5">WHOIS Privacy</p>
                <p class="font-medium text-xs" :class="domain.privacy ? 'text-green-700' : 'text-gray-400'">
                    {{ domain.privacy ? 'Enabled' : 'Disabled' }}
                </p>
            </div>
        </div>

        <!-- Nameservers -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Nameservers</h2>
            <p class="text-xs text-gray-400 mb-4">
                Changes take effect at the registrar within minutes, but DNS propagation may take up to 48 hours.
            </p>
            <div class="space-y-2 mb-4">
                <div v-for="(ns, i) in nsForm.nameservers" :key="i" class="flex gap-2 items-center">
                    <span class="text-xs text-gray-400 w-8">NS{{ i + 1 }}</span>
                    <input v-model="nsForm.nameservers[i]" type="text" :placeholder="`ns${i+1}.example.com`"
                        class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <button v-if="nsForm.nameservers.length > 2" @click="removeNs(i)"
                        type="button" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button v-if="nsForm.nameservers.length < 6" type="button" @click="addNs"
                    class="text-xs text-indigo-600 hover:underline">+ Add nameserver</button>
                <button type="button" @click="saveNameservers" :disabled="nsForm.processing"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 ml-auto">
                    Save Nameservers
                </button>
            </div>
            <p v-if="nsForm.errors.nameservers" class="text-red-500 text-xs mt-2">{{ nsForm.errors.nameservers }}</p>
        </div>
    </div>
</template>
