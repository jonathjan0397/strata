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
})

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
        <form v-if="tab === 'integrations'" @submit.prevent="intForm.patch(route('admin.settings.integrations'))" class="space-y-5">

          <!-- Google OAuth -->
          <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <div class="flex items-start gap-3">
              <svg class="h-7 w-7 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
              </svg>
              <div>
                <h3 class="text-sm font-semibold text-gray-800">Google OAuth</h3>
                <p class="text-xs text-gray-400 mt-0.5">Allows clients (and staff) to sign in / register with their Google account. Get credentials from <span class="font-medium">Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client IDs</span>.</p>
                <p class="text-xs text-gray-400 mt-1">Redirect URI to whitelist: <code class="bg-gray-100 px-1 rounded">{{ appUrl }}/auth/google/callback</code></p>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
                <input v-model="intForm.integration_google_client_id" type="text" autocomplete="off" placeholder="1234567890-abc….apps.googleusercontent.com"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                <input v-model="intForm.integration_google_client_secret" type="password" autocomplete="new-password" placeholder="GOCSPX-…"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
            </div>
          </div>

          <!-- Microsoft OAuth -->
          <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <div class="flex items-start gap-3">
              <svg class="h-7 w-7 mt-0.5 shrink-0" viewBox="0 0 23 23" fill="none">
                <path fill="#f3f3f3" d="M0 0h23v23H0z"/><path fill="#f35325" d="M1 1h10v10H1z"/>
                <path fill="#81bc06" d="M12 1h10v10H12z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/>
                <path fill="#ffba08" d="M12 12h10v10H12z"/>
              </svg>
              <div>
                <h3 class="text-sm font-semibold text-gray-800">Microsoft OAuth</h3>
                <p class="text-xs text-gray-400 mt-0.5">Sign in / register with Microsoft / Azure AD accounts. Register an app in <span class="font-medium">Azure Portal → App registrations</span>.</p>
                <p class="text-xs text-gray-400 mt-1">Redirect URI to whitelist: <code class="bg-gray-100 px-1 rounded">{{ appUrl }}/auth/microsoft/callback</code></p>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Client ID (Application ID)</label>
                <input v-model="intForm.integration_microsoft_client_id" type="text" autocomplete="off"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                <input v-model="intForm.integration_microsoft_client_secret" type="password" autocomplete="new-password"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tenant ID <span class="text-gray-400 font-normal">(leave "common" for any MS account)</span></label>
                <input v-model="intForm.integration_microsoft_tenant" type="text" placeholder="common"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
            </div>
          </div>

          <!-- Stripe -->
          <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <div>
              <h3 class="text-sm font-semibold text-gray-800">Stripe</h3>
              <p class="text-xs text-gray-400 mt-0.5">Accept card payments via Stripe. Get keys from <span class="font-medium">dashboard.stripe.com → Developers → API keys</span>.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Publishable Key</label>
                <input v-model="intForm.integration_stripe_key" type="text" autocomplete="off" placeholder="pk_live_…"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Secret Key</label>
                <input v-model="intForm.integration_stripe_secret" type="password" autocomplete="new-password" placeholder="sk_live_…"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Webhook Secret <span class="text-gray-400 font-normal">(optional — for verifying Stripe events)</span></label>
                <input v-model="intForm.integration_stripe_webhook_secret" type="password" autocomplete="new-password" placeholder="whsec_…"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
            </div>
          </div>

          <!-- PayPal -->
          <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <div>
              <h3 class="text-sm font-semibold text-gray-800">PayPal</h3>
              <p class="text-xs text-gray-400 mt-0.5">Accept payments via PayPal. Get credentials from <span class="font-medium">developer.paypal.com → My Apps & Credentials</span>.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
                <input v-model="intForm.integration_paypal_client_id" type="text" autocomplete="off"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                <input v-model="intForm.integration_paypal_client_secret" type="password" autocomplete="new-password"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mode</label>
                <select v-model="intForm.integration_paypal_mode"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="sandbox">Sandbox (testing)</option>
                  <option value="live">Live</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Authorize.Net -->
          <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <div>
              <h3 class="text-sm font-semibold text-gray-800">Authorize.Net</h3>
              <p class="text-xs text-gray-400 mt-0.5">Accept card payments via Authorize.Net. Get credentials from <span class="font-medium">Account → Settings → API Credentials & Keys</span>.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">API Login ID</label>
                <input v-model="intForm.integration_authorizenet_login_id" type="text" autocomplete="off"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Key</label>
                <input v-model="intForm.integration_authorizenet_transaction_key" type="password" autocomplete="new-password"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Client Key <span class="text-gray-400 font-normal">(for Accept.js / hosted form)</span></label>
                <input v-model="intForm.integration_authorizenet_client_key" type="password" autocomplete="new-password"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
              </div>
              <div class="flex items-center gap-3 pt-5">
                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                  <input v-model="intForm.integration_authorizenet_sandbox" type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                  Use Sandbox (testing mode)
                </label>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-3">
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
