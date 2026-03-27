<script setup>
import { ref, computed } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const page   = usePage()
const user   = computed(() => page.props.auth.user)
const status = computed(() => page.props.flash?.status)

// 2FA state
const twoFactorEnabled   = computed(() => user.value?.two_factor_enabled && user.value?.two_factor_confirmed_at)
const twoFactorPending   = computed(() => user.value?.two_factor_secret && !user.value?.two_factor_confirmed_at)

const qrSvg    = ref(null)
const plainKey = ref(null)
const loading  = ref(false)

const confirmForm = useForm({ code: '' })

async function enableTwoFactor() {
  loading.value = true
  await router.post(route('two-factor.enable'), {}, { preserveScroll: true })

  // Fetch QR after page reloads with new secret
  await fetchQr()
  loading.value = false
}

async function fetchQr() {
  const { data } = await axios.get(route('two-factor.qr-code'))
  qrSvg.value    = data.svg
  plainKey.value = data.secret
}

function confirmTwoFactor() {
  confirmForm.post(route('two-factor.confirm'), {
    preserveScroll: true,
    onSuccess: () => {
      qrSvg.value    = null
      plainKey.value = null
      confirmForm.reset('code')
    },
  })
}

function disableTwoFactor() {
  router.delete(route('two-factor.disable'), { preserveScroll: true })
}

// If we already have a pending secret (page reload), show QR
if (twoFactorPending.value) {
  fetchQr()
}
</script>

<template>
  <div class="max-w-2xl">
    <h2 class="text-lg font-semibold text-gray-900 mb-1">Two-Factor Authentication</h2>
    <p class="text-sm text-gray-500 mb-6">
      Add an extra layer of protection using a time-based one-time password (TOTP) from any authenticator app.
    </p>

    <!-- Status banners -->
    <div v-if="status === '2fa-enabled'" class="mb-5 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
      Two-factor authentication is now enabled.
    </div>
    <div v-if="status === '2fa-disabled'" class="mb-5 rounded-lg bg-yellow-50 border border-yellow-200 px-4 py-3 text-sm text-yellow-800">
      Two-factor authentication has been disabled.
    </div>

    <!-- Enabled state -->
    <div v-if="twoFactorEnabled" class="rounded-xl border border-green-200 bg-green-50 px-5 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center">
          <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <span class="text-sm font-medium text-green-800">Two-factor authentication is enabled</span>
      </div>
      <button
        class="text-sm text-red-600 hover:text-red-700 font-medium"
        @click="disableTwoFactor"
      >
        Disable
      </button>
    </div>

    <!-- Setup flow: secret generated but not yet confirmed -->
    <div v-else-if="twoFactorPending">
      <div class="rounded-xl border border-gray-200 bg-white p-6 space-y-5">
        <div>
          <h3 class="text-sm font-semibold text-gray-900 mb-1">1. Scan this QR code</h3>
          <p class="text-xs text-gray-500 mb-4">Use any authenticator app (Google Authenticator, Authy, 1Password, etc.).</p>
          <div v-if="qrSvg" class="flex justify-center">
            <div class="p-3 bg-white rounded-lg border border-gray-200 inline-block" v-html="qrSvg" />
          </div>
          <div v-else class="flex justify-center items-center h-48 text-gray-400 text-sm">Loading QR…</div>
        </div>

        <div v-if="plainKey">
          <p class="text-xs text-gray-500 mb-1">Or enter this key manually:</p>
          <code class="block text-xs font-mono bg-gray-100 rounded px-3 py-2 break-all text-gray-700">{{ plainKey }}</code>
        </div>

        <div>
          <h3 class="text-sm font-semibold text-gray-900 mb-3">2. Enter the 6-digit code to confirm</h3>
          <form @submit.prevent="confirmTwoFactor" class="flex gap-3">
            <input
              v-model="confirmForm.code"
              type="text"
              inputmode="numeric"
              maxlength="6"
              placeholder="000000"
              class="flex-1 bg-gray-50 border border-gray-300 text-gray-900 rounded-lg px-4 py-2 text-sm font-mono tracking-widest text-center focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
            <button
              type="submit"
              :disabled="confirmForm.processing"
              class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-medium rounded-lg px-5 py-2 text-sm transition-colors"
            >
              Confirm
            </button>
          </form>
          <p v-if="confirmForm.errors.code" class="mt-1.5 text-xs text-red-600">{{ confirmForm.errors.code }}</p>
        </div>
      </div>
    </div>

    <!-- Not enabled -->
    <div v-else>
      <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
            <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
            </svg>
          </div>
          <span class="text-sm text-gray-600">Two-factor authentication is <span class="font-medium text-gray-900">not enabled</span></span>
        </div>
        <button
          :disabled="loading"
          class="text-sm bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-medium rounded-lg px-4 py-1.5 transition-colors"
          @click="enableTwoFactor"
        >
          Enable
        </button>
      </div>
    </div>
  </div>
</template>
