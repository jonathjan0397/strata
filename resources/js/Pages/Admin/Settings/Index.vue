<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ settings: Object })

const tab = ref('general')

const s = props.settings ?? {}

// Logo upload
const logoPreview = ref(s.logo_path ? `/storage/${s.logo_path}` : null)
const logoFile    = ref(null)

function onLogoChange(e) {
    const file = e.target.files[0]
    if (!file) return
    logoFile.value = file
    logoPreview.value = URL.createObjectURL(file)
}

function uploadLogo() {
    if (!logoFile.value) return
    const fd = new FormData()
    fd.append('logo', logoFile.value)
    router.post(route('admin.settings.logo'), fd, { forceFormData: true })
}

const form = useForm({
    // General
    company_name:      s.company_name      ?? '',
    timezone:          s.timezone          ?? 'UTC',
    date_format:       s.date_format       ?? 'M d, Y',
    // Company
    company_email:     s.company_email     ?? '',
    company_phone:     s.company_phone     ?? '',
    company_address:   s.company_address   ?? '',
    company_city:      s.company_city      ?? '',
    company_state:     s.company_state     ?? '',
    company_zip:       s.company_zip       ?? '',
    company_country:   s.company_country   ?? '',
    // Billing
    currency:          s.currency          ?? 'USD',
    currency_symbol:   s.currency_symbol   ?? '$',
    invoice_prefix:    s.invoice_prefix    ?? 'INV-',
    invoice_due_days:  s.invoice_due_days  ?? '7',
    grace_period_days: s.grace_period_days ?? '3',
    tax_rate:          s.tax_rate          ?? '0',
    tax_name:          s.tax_name          ?? 'Tax',
})

const tabs = [
    { key: 'general',  label: 'General' },
    { key: 'company',  label: 'Company' },
    { key: 'billing',  label: 'Billing' },
]

const timezones = [
    'UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
    'America/Phoenix', 'America/Anchorage', 'Pacific/Honolulu',
    'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Europe/Amsterdam',
    'Europe/Madrid', 'Europe/Rome', 'Europe/Stockholm',
    'Asia/Dubai', 'Asia/Kolkata', 'Asia/Singapore', 'Asia/Tokyo', 'Asia/Shanghai',
    'Australia/Sydney', 'Australia/Melbourne', 'Pacific/Auckland',
]
</script>

<template>
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-gray-900">Settings</h1>
            <div class="flex gap-4">
                <Link :href="route('admin.departments.index')" class="text-sm text-indigo-600 hover:underline">Departments</Link>
                <Link :href="route('admin.canned-responses.index')" class="text-sm text-indigo-600 hover:underline">Canned Responses</Link>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-200 mb-6">
            <button v-for="t in tabs" :key="t.key"
                @click="tab = t.key"
                :class="['px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
                    tab === t.key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700']">
                {{ t.label }}
            </button>
        </div>

        <form @submit.prevent="form.patch(route('admin.settings.update'))" class="space-y-4">

            <!-- General -->
            <div v-show="tab === 'general'" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <!-- Logo -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Company Logo</label>
                    <div class="flex items-center gap-4">
                        <div class="w-24 h-16 rounded-lg border border-gray-200 flex items-center justify-center bg-gray-50 overflow-hidden">
                            <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="max-h-full max-w-full object-contain" />
                            <span v-else class="text-xs text-gray-400">No logo</span>
                        </div>
                        <div class="flex-1">
                            <input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                @change="onLogoChange"
                                class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                            <p class="text-xs text-gray-400 mt-1">PNG, JPG, WebP or SVG. Max 2 MB.</p>
                        </div>
                        <button v-if="logoFile" type="button" @click="uploadLogo"
                            class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700">
                            Upload
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company / Brand Name</label>
                    <input v-model="form.company_name" type="text"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="text-xs text-gray-400 mt-1">Used in email templates, invoices, and the client portal.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                    <select v-model="form.timezone"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date Format</label>
                    <select v-model="form.date_format"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="M d, Y">Jan 01, 2026</option>
                        <option value="d/m/Y">01/01/2026</option>
                        <option value="m/d/Y">01/01/2026 (US)</option>
                        <option value="Y-m-d">2026-01-01</option>
                    </select>
                </div>
            </div>

            <!-- Company -->
            <div v-show="tab === 'company'" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Support Email</label>
                        <input v-model="form.company_email" type="email"
                            placeholder="support@example.com"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input v-model="form.company_phone" type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input v-model="form.company_address" type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input v-model="form.company_city" type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State / Province</label>
                        <input v-model="form.company_state" type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ZIP / Postal Code</label>
                        <input v-model="form.company_zip" type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                        <input v-model="form.company_country" type="text"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>
            </div>

            <!-- Billing -->
            <div v-show="tab === 'billing'" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Currency Code</label>
                        <input v-model="form.currency" type="text" maxlength="10" placeholder="USD"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 uppercase" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Currency Symbol</label>
                        <input v-model="form.currency_symbol" type="text" maxlength="5" placeholder="$"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Prefix</label>
                        <input v-model="form.invoice_prefix" type="text" maxlength="20" placeholder="INV-"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Due Days</label>
                        <input v-model="form.invoice_due_days" type="number" min="0" max="365"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Suspension Grace Period (days)</label>
                        <input v-model="form.grace_period_days" type="number" min="0" max="365"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tax Rate (%)</label>
                        <input v-model="form.tax_rate" type="number" min="0" max="100" step="0.01"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <p class="text-xs text-gray-400 mt-1">Set to 0 to disable tax on invoices.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tax Label</label>
                        <input v-model="form.tax_name" type="text" maxlength="50" placeholder="Tax"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" :disabled="form.processing"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    Save Settings
                </button>
                <span v-if="form.recentlySuccessful" class="text-sm text-green-600">Saved.</span>
            </div>
        </form>
    </div>
</template>
