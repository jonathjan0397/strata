<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ settings: Object, appUrl: String })

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
    site_title:         s.site_title         ?? '',
    company_name:       s.company_name       ?? '',
    tagline:            s.tagline            ?? '',
    portal_theme:       s.portal_theme       ?? 'blue',
    domain_search_tlds: s.domain_search_tlds ?? '.com,.net,.org,.io',
    // Two-Factor Authentication
    otp_enabled:        s.otp_enabled    !== undefined ? !!s.otp_enabled    : true,
    otp_lifetime:       s.otp_lifetime   ?? 0,
    otp_keep_alive:     s.otp_keep_alive !== undefined ? !!s.otp_keep_alive : true,
    timezone:           s.timezone           ?? 'UTC',
    date_format:        s.date_format        ?? 'M d, Y',
    // Company
    company_email:     s.company_email     ?? '',
    company_phone:     s.company_phone     ?? '',
    company_address:   s.company_address   ?? '',
    company_city:      s.company_city      ?? '',
    company_state:     s.company_state     ?? '',
    company_zip:       s.company_zip       ?? '',
    company_country:   s.company_country   ?? '',
    // Billing
    currency:                    s.currency                    ?? 'USD',
    currency_symbol:             s.currency_symbol             ?? '$',
    invoice_prefix:              s.invoice_prefix              ?? 'INV-',
    invoice_due_days:            s.invoice_due_days            ?? '7',
    grace_period_days:           s.grace_period_days           ?? '3',
    tax_rate:                    s.tax_rate                    ?? '0',
    tax_name:                    s.tax_name                    ?? 'Tax',
    bank_transfer_instructions:  s.bank_transfer_instructions  ?? '',
    // Affiliate defaults
    affiliate_default_commission_type:  s.affiliate_default_commission_type  ?? 'percent',
    affiliate_default_commission_value: s.affiliate_default_commission_value ?? '10',
    affiliate_default_payout_threshold: s.affiliate_default_payout_threshold ?? '50',
})

// Mail settings form
const mailForm = useForm({
    mail_mailer:        s.mail_mailer        ?? 'sendmail',
    mail_from_address:  s.mail_from_address  ?? '',
    mail_from_name:     s.mail_from_name     ?? '',
    mail_host:          s.mail_host          ?? '',
    mail_port:          s.mail_port          ?? '587',
    mail_username:      s.mail_username      ?? '',
    mail_password:      s.mail_password      ?? '',
    mail_encryption:    s.mail_encryption    ?? 'auto',
    mail_sendmail_path: s.mail_sendmail_path ?? '/usr/sbin/sendmail -t -i',
})

// Auto-detect encryption from port
const encryptionHint = computed(() => {
    if (mailForm.mail_encryption !== 'auto') return null
    const port = parseInt(mailForm.mail_port)
    if (port === 465)              return { label: 'SSL/TLS (implicit)',  color: 'text-green-600' }
    if (port === 587 || port === 2525) return { label: 'STARTTLS',            color: 'text-blue-600' }
    if (port === 25)               return { label: 'Plain / STARTTLS opportunistic', color: 'text-yellow-600' }
    return { label: 'Auto-negotiate with server', color: 'text-gray-500' }
})

// When port changes while encryption is "auto", keep it on auto (hint updates automatically)
// Also: when user picks a preset port, ensure encryption stays on auto
function setPresetPort(port) {
    mailForm.mail_port       = String(port)
    mailForm.mail_encryption = 'auto'
}

const testTo        = ref('')
const testResult    = ref(null)
const testLoading   = ref(false)

async function sendTestMail() {
    testResult.value  = null
    testLoading.value = true
    try {
        const xsrf = decodeURIComponent(document.cookie.split(';').find(c => c.trim().startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '')
        const res = await fetch(route('admin.settings.mail.test'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf },
            body: JSON.stringify({ to: testTo.value }),
        })
        testResult.value = await res.json()
    } catch {
        testResult.value = { success: false, message: 'Request failed.' }
    } finally {
        testLoading.value = false
    }
}

// Integrations form
const intForm = useForm({
    integration_google_client_id:             s.integration_google_client_id             ?? '',
    integration_google_client_secret:         s.integration_google_client_secret         ?? '',
    integration_microsoft_client_id:          s.integration_microsoft_client_id          ?? '',
    integration_microsoft_client_secret:      s.integration_microsoft_client_secret      ?? '',
    integration_microsoft_tenant:             s.integration_microsoft_tenant             ?? 'common',
    integration_stripe_key:                   s.integration_stripe_key                   ?? '',
    integration_stripe_secret:                s.integration_stripe_secret                ?? '',
    integration_stripe_webhook_secret:        s.integration_stripe_webhook_secret        ?? '',
    integration_paypal_client_id:             s.integration_paypal_client_id             ?? '',
    integration_paypal_client_secret:         s.integration_paypal_client_secret         ?? '',
    integration_paypal_mode:                  s.integration_paypal_mode                  ?? 'sandbox',
    integration_authorizenet_login_id:        s.integration_authorizenet_login_id        ?? '',
    integration_authorizenet_transaction_key: s.integration_authorizenet_transaction_key ?? '',
    integration_authorizenet_client_key:      s.integration_authorizenet_client_key      ?? '',
    integration_authorizenet_sandbox:         s.integration_authorizenet_sandbox         ?? true,
    // Fraud check
    fraud_check_enabled:                      s.fraud_check_enabled                      ?? false,
    fraud_maxmind_account_id:                 s.fraud_maxmind_account_id                 ?? '',
    fraud_maxmind_license_key:                s.fraud_maxmind_license_key                ?? '',
    fraud_score_threshold:                    s.fraud_score_threshold                    ?? 75,
    fraud_action:                             s.fraud_action                             ?? 'flag',
    // Domain registrars
    integration_registrar_driver:             s.integration_registrar_driver             ?? '',
    integration_namecheap_api_user:           s.integration_namecheap_api_user           ?? '',
    integration_namecheap_api_key:            s.integration_namecheap_api_key            ?? '',
    integration_namecheap_client_ip:          s.integration_namecheap_client_ip          ?? '',
    integration_namecheap_sandbox:            s.integration_namecheap_sandbox            ?? false,
    integration_enom_uid:                     s.integration_enom_uid                     ?? '',
    integration_enom_pw:                      s.integration_enom_pw                      ?? '',
    integration_enom_sandbox:                 s.integration_enom_sandbox                 ?? false,
    integration_opensrs_api_key:              s.integration_opensrs_api_key              ?? '',
    integration_opensrs_reseller_username:    s.integration_opensrs_reseller_username    ?? '',
    integration_opensrs_sandbox:              s.integration_opensrs_sandbox              ?? false,
    integration_hexonet_login:                s.integration_hexonet_login                ?? '',
    integration_hexonet_password:             s.integration_hexonet_password             ?? '',
    integration_hexonet_sandbox:              s.integration_hexonet_sandbox              ?? false,
    // Namecheap VAS
    integration_namecheap_offer_privacy:      s.integration_namecheap_offer_privacy      !== undefined ? !!s.integration_namecheap_offer_privacy      : true,
    integration_namecheap_default_privacy:    s.integration_namecheap_default_privacy    !== undefined ? !!s.integration_namecheap_default_privacy    : false,
    integration_namecheap_default_lock:       s.integration_namecheap_default_lock       !== undefined ? !!s.integration_namecheap_default_lock       : true,
    // eNom VAS
    integration_enom_offer_privacy:           s.integration_enom_offer_privacy           !== undefined ? !!s.integration_enom_offer_privacy           : false,
    integration_enom_default_lock:            s.integration_enom_default_lock            !== undefined ? !!s.integration_enom_default_lock            : true,
    // OpenSRS VAS
    integration_opensrs_offer_privacy:        s.integration_opensrs_offer_privacy        !== undefined ? !!s.integration_opensrs_offer_privacy        : true,
    integration_opensrs_default_privacy:      s.integration_opensrs_default_privacy      !== undefined ? !!s.integration_opensrs_default_privacy      : false,
    integration_opensrs_default_lock:         s.integration_opensrs_default_lock         !== undefined ? !!s.integration_opensrs_default_lock         : true,
    // Hexonet VAS
    integration_hexonet_offer_privacy:        s.integration_hexonet_offer_privacy        !== undefined ? !!s.integration_hexonet_offer_privacy        : true,
    integration_hexonet_default_privacy:      s.integration_hexonet_default_privacy      !== undefined ? !!s.integration_hexonet_default_privacy      : false,
    integration_hexonet_default_lock:         s.integration_hexonet_default_lock         !== undefined ? !!s.integration_hexonet_default_lock         : true,
})

// Show/hide toggles for password fields
const showSecrets = ref({})
function toggleSecret(key) {
    showSecrets.value[key] = !showSecrets.value[key]
}

// Collapsible integration sections — outer sections open by default; per-registrar closed by default
const openSections = ref(['payment', 'registrar', 'fraud', 'oauth'])
function toggleSection(key) {
    const idx = openSections.value.indexOf(key)
    if (idx === -1) openSections.value.push(key)
    else openSections.value.splice(idx, 1)
}

// Masked display helpers — show last 4 chars of a secret, rest as •
function mask(val) {
    if (!val || val.length < 6) return val ? '••••••••' : ''
    return '•'.repeat(val.length - 4) + val.slice(-4)
}

const tabs = [
    { key: 'general',      label: 'General' },
    { key: 'company',      label: 'Company' },
    { key: 'billing',      label: 'Billing' },
    { key: 'email',        label: 'Email' },
    { key: 'integrations', label: 'Integrations' },
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
                <Link :href="route('admin.mail-pipes.index')" class="text-sm text-indigo-600 hover:underline">Mail Pipes</Link>
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

        <!-- Integrations (own form, own route) -->
        <form v-if="tab === 'integrations'" @submit.prevent="intForm.patch(route('admin.settings.integrations'))" class="space-y-4">

          <!-- ── PAYMENT GATEWAYS ──────────────────────────────────────── -->
          <div class="rounded-xl border border-gray-200 overflow-hidden">
            <button type="button" @click="openSections.includes('payment') ? openSections.splice(openSections.indexOf('payment'), 1) : openSections.push('payment')"
              class="w-full flex items-center justify-between px-5 py-3.5 bg-slate-800 text-white text-left">
              <span class="flex items-center gap-2 font-semibold text-sm">
                <span>💳</span> Payment Gateways
              </span>
              <svg :class="['h-4 w-4 transition-transform duration-200', openSections.includes('payment') ? 'rotate-180' : '']" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
              </svg>
            </button>

            <div v-show="openSections.includes('payment')" class="bg-white divide-y divide-gray-100">

              <!-- Stripe -->
              <div class="p-5 space-y-4">
                <div>
                  <h4 class="text-sm font-semibold text-gray-800">Stripe</h4>
                  <p class="text-xs text-gray-400 mt-0.5">Accept card payments via Stripe. Get keys from <span class="font-medium">dashboard.stripe.com → Developers → API keys</span>.</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Publishable Key</label>
                    <input v-model="intForm.integration_stripe_key" type="text" autocomplete="off" placeholder="pk_live_…"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                    <p v-if="intForm.errors.integration_stripe_key" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_stripe_key }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Secret Key</label>
                    <div class="relative">
                      <input v-model="intForm.integration_stripe_secret" :type="showSecrets['stripe_secret'] ? 'text' : 'password'" autocomplete="new-password" placeholder="sk_live_…"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                      <button type="button" @click="toggleSecret('stripe_secret')" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                        <svg v-if="showSecrets['stripe_secret']" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                      </button>
                    </div>
                  </div>
                  <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Secret <span class="text-gray-400 font-normal">(optional — for verifying Stripe events)</span></label>
                    <div class="relative">
                      <input v-model="intForm.integration_stripe_webhook_secret" :type="showSecrets['stripe_webhook'] ? 'text' : 'password'" autocomplete="new-password" placeholder="whsec_…"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                      <button type="button" @click="toggleSecret('stripe_webhook')" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                        <svg v-if="showSecrets['stripe_webhook']" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- PayPal -->
              <div class="p-5 space-y-4">
                <div>
                  <h4 class="text-sm font-semibold text-gray-800">PayPal</h4>
                  <p class="text-xs text-gray-400 mt-0.5">Accept payments via PayPal. Get credentials from <span class="font-medium">developer.paypal.com → My Apps &amp; Credentials</span>.</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
                    <input v-model="intForm.integration_paypal_client_id" type="text" autocomplete="off"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                    <div class="relative">
                      <input v-model="intForm.integration_paypal_client_secret" :type="showSecrets['paypal_secret'] ? 'text' : 'password'" autocomplete="new-password"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                      <button type="button" @click="toggleSecret('paypal_secret')" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                        <svg v-if="showSecrets['paypal_secret']" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                      </button>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mode</label>
                    <select v-model="intForm.integration_paypal_mode"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                      <option value="sandbox">Sandbox (testing)</option>
                      <option value="live">Live</option>
                    </select>
                    <span v-if="intForm.integration_paypal_mode === 'sandbox'" class="inline-flex items-center gap-1 mt-1.5 text-xs font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded px-2 py-0.5">Sandbox mode active</span>
                  </div>
                </div>
              </div>

              <!-- Authorize.Net -->
              <div class="p-5 space-y-4">
                <div>
                  <h4 class="text-sm font-semibold text-gray-800">Authorize.Net</h4>
                  <p class="text-xs text-gray-400 mt-0.5">Accept card payments via Authorize.Net. Get credentials from <span class="font-medium">Account → Settings → API Credentials &amp; Keys</span>.</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">API Login ID</label>
                    <input v-model="intForm.integration_authorizenet_login_id" type="text" autocomplete="off"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Key</label>
                    <div class="relative">
                      <input v-model="intForm.integration_authorizenet_transaction_key" :type="showSecrets['anet_txkey'] ? 'text' : 'password'" autocomplete="new-password"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                      <button type="button" @click="toggleSecret('anet_txkey')" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                        <svg v-if="showSecrets['anet_txkey']" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                      </button>
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client Key <span class="text-gray-400 font-normal">(for Accept.js / hosted form)</span></label>
                    <div class="relative">
                      <input v-model="intForm.integration_authorizenet_client_key" :type="showSecrets['anet_clientkey'] ? 'text' : 'password'" autocomplete="new-password"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                      <button type="button" @click="toggleSecret('anet_clientkey')" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                        <svg v-if="showSecrets['anet_clientkey']" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                      </button>
                    </div>
                  </div>
                  <div class="flex items-center gap-3 pt-4">
                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                      <input v-model="intForm.integration_authorizenet_sandbox" type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                      Use Sandbox (testing mode)
                    </label>
                    <span v-if="intForm.integration_authorizenet_sandbox" class="inline-flex items-center gap-1 text-xs font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded px-2 py-0.5">Sandbox mode active</span>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- ── DOMAIN REGISTRARS ─────────────────────────────────────── -->
          <div class="rounded-xl border border-gray-200 overflow-hidden">
            <button type="button" @click="openSections.includes('registrar') ? openSections.splice(openSections.indexOf('registrar'), 1) : openSections.push('registrar')"
              class="w-full flex items-center justify-between px-5 py-3.5 bg-slate-800 text-white text-left">
              <span class="flex items-center gap-2 font-semibold text-sm">
                <span>🌐</span> Domain Registrars
              </span>
              <svg :class="['h-4 w-4 transition-transform duration-200', openSections.includes('registrar') ? 'rotate-180' : '']" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
              </svg>
            </button>

            <div v-show="openSections.includes('registrar')" class="bg-white divide-y divide-gray-100">

              <!-- Active Registrar selector -->
              <div class="p-5 space-y-3">
                <div>
                  <h4 class="text-sm font-semibold text-gray-800">Active Registrar</h4>
                  <p class="text-xs text-gray-400 mt-0.5">Select which registrar API to use when provisioning and managing domains.</p>
                </div>
                <div class="max-w-xs">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Registrar Driver</label>
                  <select v-model="intForm.integration_registrar_driver"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">— None selected —</option>
                    <option value="namecheap">Namecheap</option>
                    <option value="enom">eNom</option>
                    <option value="opensrs">OpenSRS</option>
                    <option value="hexonet">Hexonet</option>
                  </select>
                  <p v-if="intForm.errors.integration_registrar_driver" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_registrar_driver }}</p>
                </div>
              </div>

              <!-- ── Namecheap ── -->
              <div>
                <button type="button" @click="toggleSection('reg_namecheap')"
                  class="w-full flex items-center justify-between px-5 py-3 bg-gray-50 hover:bg-gray-100 text-left transition-colors">
                  <span class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    Namecheap
                    <span v-if="intForm.integration_registrar_driver === 'namecheap'"
                      class="text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded px-1.5 py-0.5">Active</span>
                  </span>
                  <svg :class="['h-4 w-4 text-gray-400 transition-transform duration-200', openSections.includes('reg_namecheap') ? 'rotate-180' : '']" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                  </svg>
                </button>
                <div v-show="openSections.includes('reg_namecheap')" class="p-5 space-y-5 bg-white">
                  <div class="space-y-3">
                    <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Credentials</h5>
                    <div class="grid grid-cols-2 gap-4">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">API User</label>
                        <input v-model="intForm.integration_namecheap_api_user" type="text" autocomplete="off"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                        <p v-if="intForm.errors.integration_namecheap_api_user" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_namecheap_api_user }}</p>
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                        <div class="relative">
                          <input v-model="intForm.integration_namecheap_api_key" :type="showSecrets['nc_api_key'] ? 'text' : 'password'" autocomplete="new-password"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                          <button type="button" @click="toggleSecret('nc_api_key')" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                            <svg v-if="showSecrets['nc_api_key']" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                            <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                          </button>
                        </div>
                        <p v-if="intForm.errors.integration_namecheap_api_key" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_namecheap_api_key }}</p>
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Client IP</label>
                        <input v-model="intForm.integration_namecheap_client_ip" type="text" autocomplete="off" placeholder="1.2.3.4"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                        <p class="text-xs text-gray-400 mt-0.5">Your server's IP, must be whitelisted in Namecheap</p>
                        <p v-if="intForm.errors.integration_namecheap_client_ip" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_namecheap_client_ip }}</p>
                      </div>
                      <div class="flex items-center gap-3 pt-4">
                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                          <input v-model="intForm.integration_namecheap_sandbox" type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                          Use Sandbox
                        </label>
                        <span v-if="intForm.integration_namecheap_sandbox" class="inline-flex items-center gap-1 text-xs font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded px-2 py-0.5">Sandbox mode active</span>
                      </div>
                    </div>
                  </div>
                  <div class="pt-1 border-t border-gray-100 space-y-3">
                    <div>
                      <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mt-3">Value Added Services</h5>
                      <p class="text-xs text-gray-400 mt-0.5">Configure which add-ons to offer clients at domain checkout.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                      <label class="flex items-start gap-2.5 cursor-pointer select-none">
                        <input v-model="intForm.integration_namecheap_offer_privacy" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span><span class="text-sm font-medium text-gray-700 block">Offer WHOIS Privacy</span><span class="text-xs text-gray-400">WhoisGuard — free for life with most TLDs</span></span>
                      </label>
                      <label class="flex items-start gap-2.5 cursor-pointer select-none">
                        <input v-model="intForm.integration_namecheap_default_privacy" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span><span class="text-sm font-medium text-gray-700 block">Enable Privacy by Default</span><span class="text-xs text-gray-400">Auto-enable WhoisGuard on new registrations</span></span>
                      </label>
                      <label class="flex items-start gap-2.5 cursor-pointer select-none">
                        <input v-model="intForm.integration_namecheap_default_lock" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span><span class="text-sm font-medium text-gray-700 block">Transfer Lock by Default</span><span class="text-xs text-gray-400">Protect against unauthorized transfers</span></span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ── eNom ── -->
              <div>
                <button type="button" @click="toggleSection('reg_enom')"
                  class="w-full flex items-center justify-between px-5 py-3 bg-gray-50 hover:bg-gray-100 text-left transition-colors">
                  <span class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    eNom
                    <span v-if="intForm.integration_registrar_driver === 'enom'"
                      class="text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded px-1.5 py-0.5">Active</span>
                  </span>
                  <svg :class="['h-4 w-4 text-gray-400 transition-transform duration-200', openSections.includes('reg_enom') ? 'rotate-180' : '']" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                  </svg>
                </button>
                <div v-show="openSections.includes('reg_enom')" class="p-5 space-y-5 bg-white">
                  <div class="space-y-3">
                    <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Credentials</h5>
                    <div class="grid grid-cols-2 gap-4">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">User ID</label>
                        <input v-model="intForm.integration_enom_uid" type="text" autocomplete="off"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                        <p v-if="intForm.errors.integration_enom_uid" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_enom_uid }}</p>
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                          <input v-model="intForm.integration_enom_pw" :type="showSecrets['enom_pw'] ? 'text' : 'password'" autocomplete="new-password"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                          <button type="button" @click="toggleSecret('enom_pw')" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                            <svg v-if="showSecrets['enom_pw']" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                            <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                          </button>
                        </div>
                        <p v-if="intForm.errors.integration_enom_pw" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_enom_pw }}</p>
                      </div>
                      <div class="flex items-center gap-3 pt-4">
                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                          <input v-model="intForm.integration_enom_sandbox" type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                          Use Sandbox
                        </label>
                        <span v-if="intForm.integration_enom_sandbox" class="inline-flex items-center gap-1 text-xs font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded px-2 py-0.5">Sandbox mode active</span>
                      </div>
                    </div>
                  </div>
                  <div class="pt-1 border-t border-gray-100 space-y-3">
                    <div>
                      <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mt-3">Value Added Services</h5>
                      <p class="text-xs text-gray-400 mt-0.5">eNom WhoIsGuard privacy is a separately purchased product and cannot be managed via the reseller API.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <label class="flex items-start gap-2.5 cursor-pointer select-none">
                        <input v-model="intForm.integration_enom_offer_privacy" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span><span class="text-sm font-medium text-gray-700 block">Offer WHOIS Privacy</span><span class="text-xs text-gray-400">WhoIsGuard — purchased separately via eNom panel</span></span>
                      </label>
                      <label class="flex items-start gap-2.5 cursor-pointer select-none">
                        <input v-model="intForm.integration_enom_default_lock" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span><span class="text-sm font-medium text-gray-700 block">Transfer Lock by Default</span><span class="text-xs text-gray-400">Protect against unauthorized transfers</span></span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ── OpenSRS ── -->
              <div>
                <button type="button" @click="toggleSection('reg_opensrs')"
                  class="w-full flex items-center justify-between px-5 py-3 bg-gray-50 hover:bg-gray-100 text-left transition-colors">
                  <span class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    OpenSRS
                    <span v-if="intForm.integration_registrar_driver === 'opensrs'"
                      class="text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded px-1.5 py-0.5">Active</span>
                  </span>
                  <svg :class="['h-4 w-4 text-gray-400 transition-transform duration-200', openSections.includes('reg_opensrs') ? 'rotate-180' : '']" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                  </svg>
                </button>
                <div v-show="openSections.includes('reg_opensrs')" class="p-5 space-y-5 bg-white">
                  <div class="space-y-3">
                    <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Credentials</h5>
                    <div class="grid grid-cols-2 gap-4">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reseller Username</label>
                        <input v-model="intForm.integration_opensrs_reseller_username" type="text" autocomplete="off"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                        <p v-if="intForm.errors.integration_opensrs_reseller_username" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_opensrs_reseller_username }}</p>
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">API Key</label>
                        <div class="relative">
                          <input v-model="intForm.integration_opensrs_api_key" :type="showSecrets['opensrs_key'] ? 'text' : 'password'" autocomplete="new-password"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                          <button type="button" @click="toggleSecret('opensrs_key')" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                            <svg v-if="showSecrets['opensrs_key']" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                            <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                          </button>
                        </div>
                        <p v-if="intForm.errors.integration_opensrs_api_key" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_opensrs_api_key }}</p>
                      </div>
                      <div class="flex items-center gap-3 pt-4">
                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                          <input v-model="intForm.integration_opensrs_sandbox" type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                          Use Sandbox
                        </label>
                        <span v-if="intForm.integration_opensrs_sandbox" class="inline-flex items-center gap-1 text-xs font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded px-2 py-0.5">Sandbox mode active</span>
                      </div>
                    </div>
                  </div>
                  <div class="flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
                    <svg class="h-4 w-4 mt-0.5 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                    <span><strong>TLD Pricing Import:</strong> OpenSRS has no bulk pricing API. Import queries each TLD individually and may take 30–60 seconds to complete.</span>
                  </div>
                  <div class="pt-1 border-t border-gray-100 space-y-3">
                    <div>
                      <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mt-3">Value Added Services</h5>
                      <p class="text-xs text-gray-400 mt-0.5">WHOIS privacy and transfer lock are fully managed via the OpenSRS XCP API.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                      <label class="flex items-start gap-2.5 cursor-pointer select-none">
                        <input v-model="intForm.integration_opensrs_offer_privacy" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span><span class="text-sm font-medium text-gray-700 block">Offer WHOIS Privacy</span><span class="text-xs text-gray-400">ID Protect — managed via API</span></span>
                      </label>
                      <label class="flex items-start gap-2.5 cursor-pointer select-none">
                        <input v-model="intForm.integration_opensrs_default_privacy" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span><span class="text-sm font-medium text-gray-700 block">Enable Privacy by Default</span><span class="text-xs text-gray-400">Auto-enable ID Protect on new registrations</span></span>
                      </label>
                      <label class="flex items-start gap-2.5 cursor-pointer select-none">
                        <input v-model="intForm.integration_opensrs_default_lock" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span><span class="text-sm font-medium text-gray-700 block">Transfer Lock by Default</span><span class="text-xs text-gray-400">Protect against unauthorized transfers</span></span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <!-- ── Hexonet ── -->
              <div>
                <button type="button" @click="toggleSection('reg_hexonet')"
                  class="w-full flex items-center justify-between px-5 py-3 bg-gray-50 hover:bg-gray-100 text-left transition-colors">
                  <span class="flex items-center gap-2 text-sm font-semibold text-gray-700">
                    Hexonet
                    <span v-if="intForm.integration_registrar_driver === 'hexonet'"
                      class="text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded px-1.5 py-0.5">Active</span>
                  </span>
                  <svg :class="['h-4 w-4 text-gray-400 transition-transform duration-200', openSections.includes('reg_hexonet') ? 'rotate-180' : '']" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                  </svg>
                </button>
                <div v-show="openSections.includes('reg_hexonet')" class="p-5 space-y-5 bg-white">
                  <div class="space-y-3">
                    <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Credentials</h5>
                    <div class="grid grid-cols-2 gap-4">
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Login</label>
                        <input v-model="intForm.integration_hexonet_login" type="text" autocomplete="off"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                        <p v-if="intForm.errors.integration_hexonet_login" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_hexonet_login }}</p>
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                          <input v-model="intForm.integration_hexonet_password" :type="showSecrets['hexonet_pw'] ? 'text' : 'password'" autocomplete="new-password"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                          <button type="button" @click="toggleSecret('hexonet_pw')" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                            <svg v-if="showSecrets['hexonet_pw']" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                            <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                          </button>
                        </div>
                        <p v-if="intForm.errors.integration_hexonet_password" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_hexonet_password }}</p>
                      </div>
                      <div class="flex items-center gap-3 pt-4">
                        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                          <input v-model="intForm.integration_hexonet_sandbox" type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                          Use Sandbox
                        </label>
                        <span v-if="intForm.integration_hexonet_sandbox" class="inline-flex items-center gap-1 text-xs font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded px-2 py-0.5">Sandbox mode active</span>
                      </div>
                    </div>
                  </div>
                  <div class="pt-1 border-t border-gray-100 space-y-3">
                    <div>
                      <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mt-3">Value Added Services</h5>
                      <p class="text-xs text-gray-400 mt-0.5">WHOIS privacy and transfer lock are fully managed via the Hexonet ISPAPI.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                      <label class="flex items-start gap-2.5 cursor-pointer select-none">
                        <input v-model="intForm.integration_hexonet_offer_privacy" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span><span class="text-sm font-medium text-gray-700 block">Offer WHOIS Privacy</span><span class="text-xs text-gray-400">WhoisGuard — managed via ISPAPI</span></span>
                      </label>
                      <label class="flex items-start gap-2.5 cursor-pointer select-none">
                        <input v-model="intForm.integration_hexonet_default_privacy" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span><span class="text-sm font-medium text-gray-700 block">Enable Privacy by Default</span><span class="text-xs text-gray-400">Auto-enable WhoisGuard on new registrations</span></span>
                      </label>
                      <label class="flex items-start gap-2.5 cursor-pointer select-none">
                        <input v-model="intForm.integration_hexonet_default_lock" type="checkbox" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span><span class="text-sm font-medium text-gray-700 block">Transfer Lock by Default</span><span class="text-xs text-gray-400">Protect against unauthorized transfers</span></span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- ── FRAUD PREVENTION ──────────────────────────────────────── -->
          <div class="rounded-xl border border-gray-200 overflow-hidden">
            <button type="button" @click="openSections.includes('fraud') ? openSections.splice(openSections.indexOf('fraud'), 1) : openSections.push('fraud')"
              class="w-full flex items-center justify-between px-5 py-3.5 bg-slate-800 text-white text-left">
              <span class="flex items-center gap-2 font-semibold text-sm">
                <span>🛡️</span> Fraud Prevention
              </span>
              <svg :class="['h-4 w-4 transition-transform duration-200', openSections.includes('fraud') ? 'rotate-180' : '']" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
              </svg>
            </button>

            <div v-show="openSections.includes('fraud')" class="bg-white p-5 space-y-4">
              <div>
                <h4 class="text-sm font-semibold text-gray-800">MaxMind minFraud</h4>
                <p class="text-xs text-gray-400 mt-0.5">Automatically score new orders using MaxMind minFraud. Get credentials at <span class="font-medium">maxmind.com → Account → Manage License Keys</span>.</p>
              </div>
              <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                <input v-model="intForm.fraud_check_enabled" type="checkbox"
                  class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                Enable fraud scoring on new orders
              </label>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Account ID</label>
                  <input v-model="intForm.fraud_maxmind_account_id" type="text" autocomplete="off" placeholder="123456"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  <p v-if="intForm.errors.fraud_maxmind_account_id" class="text-xs text-red-500 mt-1">{{ intForm.errors.fraud_maxmind_account_id }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">License Key</label>
                  <div class="relative">
                    <input v-model="intForm.fraud_maxmind_license_key" :type="showSecrets['mm_key'] ? 'text' : 'password'" autocomplete="new-password" placeholder="••••••••"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <button type="button" @click="toggleSecret('mm_key')" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                      <svg v-if="showSecrets['mm_key']" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                      <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                    </button>
                  </div>
                  <p v-if="intForm.errors.fraud_maxmind_license_key" class="text-xs text-red-500 mt-1">{{ intForm.errors.fraud_maxmind_license_key }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Score Threshold (1–100)</label>
                  <input v-model="intForm.fraud_score_threshold" type="number" min="1" max="100"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                  <p class="text-xs text-gray-400 mt-0.5">Orders at or above this score trigger the action below</p>
                  <p v-if="intForm.errors.fraud_score_threshold" class="text-xs text-red-500 mt-1">{{ intForm.errors.fraud_score_threshold }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Action When Threshold Exceeded</label>
                  <select v-model="intForm.fraud_action"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="flag">Flag for review — order is placed, score shown in admin</option>
                    <option value="reject">Reject — order is blocked immediately</option>
                  </select>
                  <p v-if="intForm.errors.fraud_action" class="text-xs text-red-500 mt-1">{{ intForm.errors.fraud_action }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- ── OAUTH / SOCIAL LOGIN ──────────────────────────────────── -->
          <div class="rounded-xl border border-gray-200 overflow-hidden">
            <button type="button" @click="openSections.includes('oauth') ? openSections.splice(openSections.indexOf('oauth'), 1) : openSections.push('oauth')"
              class="w-full flex items-center justify-between px-5 py-3.5 bg-slate-800 text-white text-left">
              <span class="flex items-center gap-2 font-semibold text-sm">
                <span>🔑</span> OAuth / Social Login
              </span>
              <svg :class="['h-4 w-4 transition-transform duration-200', openSections.includes('oauth') ? 'rotate-180' : '']" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
              </svg>
            </button>

            <div v-show="openSections.includes('oauth')" class="bg-white divide-y divide-gray-100">

              <!-- Google -->
              <div class="p-5 space-y-4">
                <div class="flex items-start gap-3">
                  <svg class="h-7 w-7 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                  </svg>
                  <div>
                    <h4 class="text-sm font-semibold text-gray-800">Google</h4>
                    <p class="text-xs text-gray-400 mt-0.5">Allows clients (and staff) to sign in / register with their Google account. Get credentials from <span class="font-medium">Google Cloud Console → APIs &amp; Services → Credentials → OAuth 2.0 Client IDs</span>.</p>
                    <p class="text-xs text-gray-400 mt-1">Redirect URI to whitelist: <code class="bg-gray-100 px-1 rounded">{{ appUrl }}/auth/google/callback</code></p>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
                    <input v-model="intForm.integration_google_client_id" type="text" autocomplete="off" placeholder="1234567890-abc….apps.googleusercontent.com"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                    <p v-if="intForm.errors.integration_google_client_id" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_google_client_id }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                    <div class="relative">
                      <input v-model="intForm.integration_google_client_secret" :type="showSecrets['google_secret'] ? 'text' : 'password'" autocomplete="new-password" placeholder="GOCSPX-…"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                      <button type="button" @click="toggleSecret('google_secret')" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                        <svg v-if="showSecrets['google_secret']" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                      </button>
                    </div>
                    <p v-if="intForm.errors.integration_google_client_secret" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_google_client_secret }}</p>
                  </div>
                </div>
              </div>

              <!-- Microsoft -->
              <div class="p-5 space-y-4">
                <div class="flex items-start gap-3">
                  <svg class="h-7 w-7 mt-0.5 shrink-0" viewBox="0 0 23 23" fill="none">
                    <path fill="#f3f3f3" d="M0 0h23v23H0z"/><path fill="#f35325" d="M1 1h10v10H1z"/>
                    <path fill="#81bc06" d="M12 1h10v10H12z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/>
                    <path fill="#ffba08" d="M12 12h10v10H12z"/>
                  </svg>
                  <div>
                    <h4 class="text-sm font-semibold text-gray-800">Microsoft</h4>
                    <p class="text-xs text-gray-400 mt-0.5">Sign in / register with Microsoft / Azure AD accounts. Register an app in <span class="font-medium">Azure Portal → App registrations</span>.</p>
                    <p class="text-xs text-gray-400 mt-1">Redirect URI to whitelist: <code class="bg-gray-100 px-1 rounded">{{ appUrl }}/auth/microsoft/callback</code></p>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
                    <input v-model="intForm.integration_microsoft_client_id" type="text" autocomplete="off"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                    <p v-if="intForm.errors.integration_microsoft_client_id" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_microsoft_client_id }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                    <div class="relative">
                      <input v-model="intForm.integration_microsoft_client_secret" :type="showSecrets['ms_secret'] ? 'text' : 'password'" autocomplete="new-password"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                      <button type="button" @click="toggleSecret('ms_secret')" class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                        <svg v-if="showSecrets['ms_secret']" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z" clip-rule="evenodd"/><path d="M10.748 13.93l2.523 2.524a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                      </button>
                    </div>
                    <p v-if="intForm.errors.integration_microsoft_client_secret" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_microsoft_client_secret }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tenant ID</label>
                    <input v-model="intForm.integration_microsoft_tenant" type="text" placeholder="common"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                    <p class="text-xs text-gray-400 mt-0.5">Use 'common' for multi-tenant</p>
                    <p v-if="intForm.errors.integration_microsoft_tenant" class="text-xs text-red-500 mt-1">{{ intForm.errors.integration_microsoft_tenant }}</p>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- Save button -->
          <div class="flex items-center gap-3 pt-1">
            <button type="submit" :disabled="intForm.processing"
              class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              Save Integration Settings
            </button>
            <span v-if="intForm.recentlySuccessful" class="text-sm text-green-600">Saved.</span>
          </div>
        </form>

        <!-- Email settings (own form, own route) -->
        <form v-if="tab === 'email'" @submit.prevent="mailForm.patch(route('admin.settings.mail'))" class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">

                <!-- Mailer type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mail Driver</label>
                    <select v-model="mailForm.mail_mailer"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="sendmail">Sendmail (server default)</option>
                        <option value="smtp">SMTP</option>
                        <option value="log">Log only (debug)</option>
                    </select>
                </div>

                <!-- From -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Address</label>
                        <input v-model="mailForm.mail_from_address" type="email"
                            placeholder="noreply@yourdomain.com"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        <p v-if="mailForm.errors.mail_from_address" class="text-xs text-red-500 mt-1">{{ mailForm.errors.mail_from_address }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                        <input v-model="mailForm.mail_from_name" type="text"
                            placeholder="Support"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>

                <!-- Sendmail path -->
                <div v-if="mailForm.mail_mailer === 'sendmail'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sendmail Path</label>
                    <input v-model="mailForm.mail_sendmail_path" type="text"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="text-xs text-gray-400 mt-1">Default: <code>/usr/sbin/sendmail -t -i</code></p>
                </div>

                <!-- SMTP fields -->
                <template v-if="mailForm.mail_mailer === 'smtp'">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                            <input v-model="mailForm.mail_host" type="text" placeholder="mail.yourdomain.com"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
                            <input v-model="mailForm.mail_port" type="number" placeholder="587"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <!-- Port presets -->
                            <div class="flex gap-1.5 mt-1.5 flex-wrap">
                                <button v-for="p in [
                                    { port: 587,  label: '587 STARTTLS' },
                                    { port: 465,  label: '465 SSL' },
                                    { port: 25,   label: '25 Plain' },
                                    { port: 2525, label: '2525 Alt' },
                                ]" :key="p.port" type="button"
                                    @click="setPresetPort(p.port)"
                                    :class="[
                                        'text-xs px-2 py-0.5 rounded border transition-colors',
                                        Number(mailForm.mail_port) === p.port
                                            ? 'bg-indigo-600 text-white border-indigo-600'
                                            : 'border-gray-300 text-gray-500 hover:border-indigo-400 hover:text-indigo-600'
                                    ]">
                                    {{ p.label }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                            <select v-model="mailForm.mail_encryption"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="auto">Auto (detect from port)</option>
                                <option value="tls">TLS (STARTTLS) — port 587</option>
                                <option value="ssl">SSL/TLS — port 465</option>
                                <option value="">None / plain</option>
                            </select>
                            <!-- Auto-detect hint -->
                            <p v-if="encryptionHint" :class="['text-xs mt-1 flex items-center gap-1', encryptionHint.color]">
                                <svg class="h-3 w-3 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                                Port {{ mailForm.mail_port }} → {{ encryptionHint.label }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input v-model="mailForm.mail_username" type="text" autocomplete="off"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input v-model="mailForm.mail_password" type="password" autocomplete="new-password"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                </template>
            </div>

            <!-- Test send -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
                <p class="text-sm font-medium text-gray-700">Send Test Email</p>
                <div class="flex gap-2">
                    <input v-model="testTo" type="email" placeholder="you@example.com"
                        class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <button type="button" @click="sendTestMail" :disabled="!testTo || testLoading"
                        class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-900 disabled:opacity-50">
                        {{ testLoading ? 'Sending…' : 'Send Test' }}
                    </button>
                </div>
                <p v-if="testResult" :class="testResult.success ? 'text-green-600' : 'text-red-600'" class="text-sm">
                    {{ testResult.message }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" :disabled="mailForm.processing"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    Save Mail Settings
                </button>
                <span v-if="mailForm.recentlySuccessful" class="text-sm text-green-600">Saved.</span>
            </div>
        </form>

        <form v-else @submit.prevent="form.patch(route('admin.settings.update'))" class="space-y-4">

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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Site Title</label>
                    <input v-model="form.site_title" type="text" placeholder="Strata Service Billing and Support Platform"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="text-xs text-gray-400 mt-1">Shown in browser tab titles and email subjects. Leave blank to use the application default.</p>
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

                <!-- Tagline -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Portal Tagline</label>
                    <input v-model="form.tagline" type="text" placeholder="Professional hosting &amp; services"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    <p class="text-xs text-gray-400 mt-1">Shown below the hero title on the public portal home page.</p>
                </div>

                <!-- Portal Theme -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Portal Color Theme</label>
                    <div class="grid grid-cols-4 gap-3">
                        <label v-for="th in [
                            { value:'blue',      label:'Ocean Blue',   bg:'linear-gradient(135deg,#0c1445,#0891b2)' },
                            { value:'red',       label:'Ruby Red',     bg:'linear-gradient(135deg,#2d0a0a,#dc2626)' },
                            { value:'green',     label:'Forest Green', bg:'linear-gradient(135deg,#042f1a,#059669)' },
                            { value:'lightblue', label:'Sky Blue',     bg:'linear-gradient(135deg,#0a1628,#3b82f6)' },
                        ]" :key="th.value"
                            class="cursor-pointer rounded-xl overflow-hidden border-2 transition-all"
                            :class="form.portal_theme === th.value ? 'border-indigo-500 ring-2 ring-indigo-200' : 'border-gray-200 hover:border-gray-300'">
                            <input type="radio" class="sr-only" :value="th.value" v-model="form.portal_theme" />
                            <div class="h-10 w-full" :style="`background: ${th.bg};`"></div>
                            <div class="bg-white px-2 py-1.5 text-xs font-medium text-gray-600 text-center">{{ th.label }}</div>
                        </label>
                    </div>
                </div>

                <!-- Domain Search TLDs -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Domain Search TLDs</label>
                    <input v-model="form.domain_search_tlds" type="text" placeholder=".com,.net,.org,.io"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
                    <p class="text-xs text-gray-400 mt-1">Comma-separated TLDs shown in the domain search widget. Requires a domain registrar configured in Integrations.</p>
                </div>

                <!-- Two-Factor Authentication -->
                <div class="border-t border-gray-100 pt-4">
                    <p class="text-sm font-semibold text-gray-800 mb-3">Two-Factor Authentication</p>
                    <div class="space-y-3">
                        <label class="flex items-center justify-between gap-4 cursor-pointer">
                            <div>
                                <span class="block text-sm font-medium text-gray-700">Require 2FA for staff &amp; admins</span>
                                <span class="block text-xs text-gray-400 mt-0.5">When enabled, admin and staff accounts must complete a TOTP challenge after login.</span>
                            </div>
                            <button type="button" @click="form.otp_enabled = !form.otp_enabled"
                                :class="['relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none',
                                    form.otp_enabled ? 'bg-indigo-600' : 'bg-gray-200']">
                                <span :class="['inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform duration-200',
                                    form.otp_enabled ? 'translate-x-5' : 'translate-x-0']" />
                            </button>
                        </label>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Session lifetime <span class="font-normal text-gray-400">(minutes)</span></label>
                                <input v-model.number="form.otp_lifetime" type="number" min="0" max="1440"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <p class="text-xs text-gray-400 mt-1">0 = valid for the entire session.</p>
                            </div>
                            <div class="flex flex-col justify-between">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Keep-alive</label>
                                <label class="flex items-center gap-2 cursor-pointer mt-1">
                                    <button type="button" @click="form.otp_keep_alive = !form.otp_keep_alive"
                                        :class="['relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none',
                                            form.otp_keep_alive ? 'bg-indigo-600' : 'bg-gray-200']">
                                        <span :class="['inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition-transform duration-200',
                                            form.otp_keep_alive ? 'translate-x-5' : 'translate-x-0']" />
                                    </button>
                                    <span class="text-sm text-gray-600">Reset timer on each request</span>
                                </label>
                                <p class="text-xs text-gray-400 mt-1">Only applies when lifetime &gt; 0.</p>
                            </div>
                        </div>
                    </div>
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

                <!-- Bank Transfer Instructions -->
                <div class="border-t border-gray-100 pt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Bank Transfer / Manual Payment Instructions
                        <span class="font-normal text-gray-400 ml-1">(leave blank to disable)</span>
                    </label>
                    <textarea v-model="form.bank_transfer_instructions" rows="5"
                        placeholder="Bank: Example Bank&#10;Account Name: Your Company&#10;Account Number: 0000-0000&#10;Routing/Sort: 000000&#10;Reference: Your Invoice Number"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y font-mono">
                    </textarea>
                    <p class="text-xs text-gray-400 mt-1">Shown to clients when they select Bank Transfer on an unpaid invoice.</p>
                </div>

                <!-- Affiliate Defaults -->
                <div class="pt-4 border-t border-gray-100">
                    <p class="text-sm font-semibold text-gray-800 mb-3">Affiliate Program Defaults</p>
                    <p class="text-xs text-gray-500 mb-3">Applied when a client self-registers as an affiliate. Individual commission rates can be overridden per affiliate.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Default Commission Type</label>
                            <select v-model="form.affiliate_default_commission_type"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="percent">Percent (%)</option>
                                <option value="fixed">Fixed ($)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Default Value ({{ form.affiliate_default_commission_type === 'percent' ? '%' : '$' }})
                            </label>
                            <input v-model="form.affiliate_default_commission_value" type="number" step="0.01" min="0"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Default Payout Threshold ($)</label>
                            <input v-model="form.affiliate_default_payout_threshold" type="number" step="0.01" min="0"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
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
