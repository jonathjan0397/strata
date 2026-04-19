<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'

defineProps({
  currentVersion: { type: [String, null], default: null },
  codeVersion: { type: [String, null], default: null },
  alreadyUpdated: { type: Boolean, default: false },
  hasZipExtension: { type: Boolean, default: false },
  checks: { type: Array, default: () => [] },
  hardFail: { type: Boolean, default: false },
  installedAt: { type: [String, null], default: null },
  lastUpgradedAt: { type: [String, null], default: null },
  updateRepo: { type: [String, null], default: null },
})

const step = ref(1)
const authToken = ref('')
const authError = ref(null)
const upgradeError = ref(null)
const upgradeLog = ref([])
const newVersion = ref(null)
const zipInfo = ref(null)
const zipFile = ref(null)
const skipExtract = ref(false)
const downloadLatest = ref(false)
const release = ref(null)
const releaseLoading = ref(false)
const releaseError = ref(null)
const upgradeContext = ref({
  currentVersion: null,
  codeVersion: null,
  alreadyUpdated: false,
  hasZipExtension: false,
  checks: [],
  hardFail: false,
  installedAt: null,
  lastUpgradedAt: null,
  updateRepo: null,
})

const auth = {
  email: ref(''),
  password: ref(''),
}

const checksAllPass = computed(() =>
  upgradeContext.value.checks.every(check => check.pass || check.warn)
)

const authValid = computed(() =>
  auth.email.value.trim() && auth.password.value.length >= 6
)

const canUpgrade = computed(() => {
  if (skipExtract.value) return true
  if (downloadLatest.value && release.value && !release.value.up_to_date) return true
  return !!zipInfo.value
})

const targetVersion = computed(() => {
  if (skipExtract.value) return upgradeContext.value.codeVersion ?? '...'
  if (downloadLatest.value) return release.value?.tag ?? '...'
  return zipInfo.value?.version ?? '...'
})

async function fetchRelease() {
  if (!authToken.value) return

  releaseLoading.value = true
  releaseError.value = null

  try {
    const { data } = await axios.get('/upgrade/release', {
      params: { auth_token: authToken.value },
    })
    release.value = data.release ?? null
  } catch (error) {
    releaseError.value = error.response?.data?.error ?? 'Could not check for the latest release.'
  } finally {
    releaseLoading.value = false
  }
}

async function verifyAuth() {
  authError.value = null

  try {
    const { data } = await axios.post('/upgrade/verify', {
      email: auth.email.value.trim(),
      password: b64(auth.password.value),
    })

    authToken.value = data.auth_token ?? ''
    upgradeContext.value = {
      ...upgradeContext.value,
      ...(data.context ?? {}),
    }
    release.value = null
    fetchRelease()
    step.value = 2
  } catch (error) {
    authError.value = error.response?.data?.error ?? 'Verification failed.'
  }
}

async function onZipSelected(event) {
  const file = event.target.files?.[0]
  if (!file) return

  zipFile.value = file
  zipInfo.value = null
  skipExtract.value = false
  downloadLatest.value = false

  try {
    const formData = new FormData()
    formData.append('zip', file)
    formData.append('auth_token', authToken.value)
    const { data } = await axios.post('/upgrade/peek', formData)

    if (data.success) {
      zipInfo.value = { version: data.version }
      return
    }

    zipFile.value = null
    alert(data.error ?? 'Could not read ZIP.')
  } catch (error) {
    zipFile.value = null
    alert(error.response?.data?.error ?? 'Could not inspect ZIP file.')
  }
}

function useManualMode() {
  skipExtract.value = true
  downloadLatest.value = false
  zipFile.value = null
  zipInfo.value = null
  step.value = 4
}

function useBuiltInDownload() {
  skipExtract.value = false
  downloadLatest.value = true
  zipFile.value = null
  zipInfo.value = null
  step.value = 4
}

function goConfirm() {
  if (canUpgrade.value) {
    step.value = 4
  }
}

async function runUpgrade() {
  step.value = 5
  upgradeError.value = null
  upgradeLog.value = []

  try {
    const formData = new FormData()
    formData.append('auth_token', authToken.value)
    formData.append('skip_extract', skipExtract.value ? '1' : '0')
    formData.append('download_latest', downloadLatest.value ? '1' : '0')

    if (!skipExtract.value && !downloadLatest.value && zipFile.value) {
      formData.append('zip', zipFile.value)
    }

    const { data } = await axios.post('/upgrade/run', formData, {
      timeout: 300000,
    })

    upgradeLog.value = data.log ?? []
    newVersion.value = data.new_version
    step.value = 6
  } catch (error) {
    upgradeError.value = error.response?.data?.error ?? 'Upgrade failed. Check server logs.'
    upgradeLog.value = error.response?.data?.log ?? []
    step.value = 4
  }
}

function b64(value) {
  return btoa(unescape(encodeURIComponent(value)))
}

function fmt(iso) {
  if (!iso) return '-'
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return iso
  }
}

function releaseNotesSnippet(body) {
  if (!body) return 'No release notes were published for this release.'
  return body.length > 280 ? `${body.slice(0, 280)}...` : body
}
</script>

<template>
  <div class="min-h-screen bg-gray-950 flex flex-col items-center justify-center px-4 py-12">
    <div class="w-full max-w-xl">
      <div class="text-center mb-8">
        <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 shadow-lg shadow-indigo-500/30 text-white font-bold text-2xl mb-4">S</div>
        <h1 class="text-2xl font-bold text-white">Strata Service Billing and Support Platform Upgrade Wizard</h1>
        <p class="text-gray-400 text-sm mt-1">Upgrade your billing installation without shell access.</p>
      </div>

      <div class="flex items-center justify-center gap-1.5 mb-8">
        <div
          v-for="i in 6"
          :key="i"
          :class="[
            'h-1.5 rounded-full transition-all duration-300',
            i === step ? 'w-8 bg-indigo-400' : i < step ? 'w-4 bg-indigo-600' : 'w-4 bg-gray-700',
          ]"
        />
      </div>

      <div v-if="step === 1" class="bg-gray-900 rounded-2xl border border-gray-800 p-6 space-y-5">
        <div>
          <h2 class="text-lg font-semibold text-white mb-1">Verify admin credentials</h2>
          <p class="text-sm text-gray-400">Enter your super-admin email and password to unlock upgrade details and authorize the upgrade.</p>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Admin Email</label>
            <input
              v-model="auth.email.value"
              type="email"
              autocomplete="email"
              class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="admin@example.com"
            >
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Password</label>
            <input
              v-model="auth.password.value"
              type="password"
              autocomplete="current-password"
              class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="********"
            >
          </div>
        </div>

        <div v-if="authError" class="rounded-lg bg-red-900/30 border border-red-700/50 px-4 py-3 text-sm text-red-300">
          {{ authError }}
        </div>

        <button
          @click="verifyAuth"
          :disabled="!authValid"
          class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-medium py-2.5 rounded-xl transition-colors"
        >
          Verify
        </button>
      </div>

      <div v-if="step === 2" class="bg-gray-900 rounded-2xl border border-gray-800 p-6 space-y-5">
        <div>
          <h2 class="text-lg font-semibold text-white mb-1">Pre-upgrade checks</h2>
          <p class="text-sm text-gray-400">Review your current installation and latest available release before proceeding.</p>
        </div>

        <div class="rounded-xl bg-gray-800/60 border border-gray-700 p-4 text-sm space-y-2">
          <div class="flex justify-between">
            <span class="text-gray-400">Installed version</span>
            <span class="font-mono font-medium text-white">{{ upgradeContext.currentVersion }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">Code version on disk</span>
            <span class="font-mono font-medium" :class="upgradeContext.alreadyUpdated ? 'text-amber-400' : 'text-gray-300'">{{ upgradeContext.codeVersion }}</span>
          </div>
          <div v-if="upgradeContext.installedAt" class="flex justify-between text-xs pt-1 border-t border-gray-700 mt-1">
            <span class="text-gray-500">Originally installed</span>
            <span class="text-gray-500">{{ fmt(upgradeContext.installedAt) }}</span>
          </div>
          <div v-if="upgradeContext.lastUpgradedAt" class="flex justify-between text-xs">
            <span class="text-gray-500">Last upgraded</span>
            <span class="text-gray-500">{{ fmt(upgradeContext.lastUpgradedAt) }}</span>
          </div>
        </div>

        <div class="rounded-xl bg-gray-800/60 border border-gray-700 p-4 space-y-3">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-medium text-white">Built-in updates</p>
              <p class="text-xs text-gray-500">Repository: {{ release?.repo || upgradeContext.updateRepo || 'not configured' }}</p>
            </div>
            <button
              type="button"
              @click="fetchRelease"
              class="shrink-0 rounded-lg border border-gray-700 px-3 py-1.5 text-xs text-gray-300 hover:text-white hover:border-gray-500 transition-colors"
            >
              Refresh
            </button>
          </div>

          <p v-if="releaseLoading" class="text-sm text-gray-400">Checking GitHub for the latest release...</p>

          <div v-else-if="release" class="space-y-2">
            <div class="flex justify-between text-sm">
              <span class="text-gray-400">Latest release</span>
              <span class="font-mono text-indigo-300">{{ release.tag }}</span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-gray-500">Published</span>
              <span class="text-gray-500">{{ fmt(release.published_at) }}</span>
            </div>
            <div class="flex justify-between text-xs">
              <span class="text-gray-500">Package source</span>
              <span class="text-gray-500">{{ release.source }}</span>
            </div>
            <div class="rounded-lg border px-3 py-2 text-xs" :class="release.up_to_date ? 'border-green-700/40 bg-green-900/20 text-green-300' : 'border-amber-700/40 bg-amber-900/20 text-amber-300'">
              {{ release.up_to_date ? 'This installation is already on the latest published release.' : 'A newer published release is available for built-in upgrade.' }}
            </div>
            <p class="text-xs text-gray-400 whitespace-pre-line">{{ releaseNotesSnippet(release.notes) }}</p>
          </div>

          <div v-else-if="releaseError" class="rounded-lg bg-red-900/30 border border-red-700/50 px-4 py-3 text-sm text-red-300">
            {{ releaseError }}
          </div>
        </div>

        <ul class="space-y-2">
          <li v-for="check in upgradeContext.checks" :key="check.label" class="flex items-start gap-3 text-sm">
            <span
              :class="[
                'mt-0.5 h-4 w-4 shrink-0 rounded-full flex items-center justify-center text-xs font-bold',
                check.pass ? 'bg-green-500/20 text-green-400' : check.warn ? 'bg-amber-500/20 text-amber-400' : 'bg-red-500/20 text-red-400',
              ]"
            >
              {{ check.pass ? 'Y' : check.warn ? '!' : 'X' }}
            </span>
            <div class="min-w-0">
              <span :class="check.pass ? 'text-gray-200' : check.warn ? 'text-amber-300' : 'text-red-300'">{{ check.label }}</span>
              <p v-if="check.detail && !check.pass" class="text-xs text-gray-500 mt-0.5 break-all">{{ check.detail }}</p>
            </div>
          </li>
        </ul>

        <div v-if="upgradeContext.hardFail" class="rounded-lg bg-red-900/30 border border-red-700/50 px-4 py-3 text-sm text-red-300">
          One or more required checks failed. Fix the issues above before continuing.
        </div>

        <div v-if="upgradeContext.alreadyUpdated" class="rounded-lg bg-amber-900/20 border border-amber-700/40 px-4 py-3 text-sm text-amber-300">
          Your code files already show a different version than the lock file. If you already uploaded new files manually, the wizard can skip extraction and only run migrations and cache rebuilds.
        </div>

        <div class="flex gap-3">
          <button @click="step = 1" class="flex-1 border border-gray-700 text-gray-400 hover:text-white hover:border-gray-500 font-medium py-2.5 rounded-xl transition-colors text-sm">Back</button>
          <button
            :disabled="upgradeContext.hardFail || !checksAllPass"
            @click="step = 3"
            class="flex-1 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-medium py-2.5 rounded-xl transition-colors"
          >
            Continue
          </button>
        </div>
      </div>

      <div v-if="step === 3" class="bg-gray-900 rounded-2xl border border-gray-800 p-6 space-y-5">
        <div>
          <h2 class="text-lg font-semibold text-white mb-1">Choose upgrade source</h2>
          <p class="text-sm text-gray-400">Use the built-in updater, upload a release package, or finish a manual file upload.</p>
        </div>

        <div v-if="release && !release.up_to_date" class="rounded-xl bg-gray-800/60 border border-gray-700 p-4 space-y-3">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-medium text-white">Built-in updater</p>
              <p class="text-xs text-gray-500">Latest published release {{ release.tag }}</p>
            </div>
            <span class="rounded-full bg-indigo-500/20 px-2 py-1 text-[11px] font-medium text-indigo-300">Recommended</span>
          </div>
          <p class="text-sm text-gray-400">The server will download the release package directly from GitHub and run the upgrade for you.</p>
          <button
            type="button"
            @click="useBuiltInDownload"
            class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 rounded-xl transition-colors"
          >
            Download {{ release.tag }} and continue
          </button>
        </div>

        <div v-else-if="release && release.up_to_date" class="rounded-lg bg-green-900/20 border border-green-700/40 px-4 py-3 text-sm text-green-300">
          This installation already matches the latest published release. You can still upload a ZIP or use manual mode if you need to rerun the upgrade steps.
        </div>

        <div class="rounded-xl bg-gray-800/60 border border-gray-700 p-4 space-y-3">
          <div>
            <p class="text-sm font-medium text-white">Upload release package</p>
            <p class="text-xs text-gray-500">Use an official Strata Service Billing and Support Platform ZIP package.</p>
          </div>

          <div v-if="upgradeContext.hasZipExtension">
            <label class="flex flex-col items-center justify-center gap-3 border-2 border-dashed border-gray-700 hover:border-indigo-500 rounded-xl p-8 cursor-pointer transition-colors group">
              <svg class="h-10 w-10 text-gray-600 group-hover:text-indigo-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
              </svg>
              <div class="text-center">
                <p v-if="!zipFile" class="text-sm text-gray-400">Click to select or drag and drop</p>
                <p v-else class="text-sm text-green-400 font-medium">{{ zipFile.name }}</p>
                <p class="text-xs text-gray-600 mt-1">ZIP package for Strata Service Billing and Support Platform</p>
              </div>
              <input type="file" accept=".zip" class="hidden" @change="onZipSelected">
            </label>

            <div v-if="zipInfo" class="mt-3 rounded-lg bg-green-900/20 border border-green-700/40 px-4 py-3 text-sm text-green-300 flex items-center gap-2">
              <span class="text-green-400">Y</span>
              Detected release <strong>{{ zipInfo.version }}</strong>. Ready to install.
            </div>
          </div>

          <div v-else class="rounded-lg bg-amber-900/20 border border-amber-700/40 px-4 py-3 text-sm text-amber-300">
            PHP ext-zip is not available on this server. ZIP upload is disabled. Use the already-uploaded files mode instead.
          </div>
        </div>

        <div class="border-t border-gray-800 pt-4">
          <p class="text-xs text-gray-500 mb-2">Already uploaded new files via FTP or your hosting file manager?</p>
          <button
            @click="useManualMode"
            class="w-full border border-gray-700 hover:border-gray-500 text-gray-400 hover:text-white text-sm font-medium py-2.5 rounded-xl transition-colors"
          >
            Files already uploaded - skip extraction and run migrations only
          </button>
        </div>

        <div class="flex gap-3 pt-1">
          <button @click="step = 2" class="flex-1 border border-gray-700 text-gray-400 hover:text-white hover:border-gray-500 font-medium py-2.5 rounded-xl transition-colors text-sm">Back</button>
          <button
            @click="goConfirm"
            :disabled="!canUpgrade"
            class="flex-1 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-medium py-2.5 rounded-xl transition-colors"
          >
            Continue
          </button>
        </div>
      </div>

      <div v-if="step === 4" class="bg-gray-900 rounded-2xl border border-gray-800 p-6 space-y-5">
        <div>
          <h2 class="text-lg font-semibold text-white mb-1">Confirm upgrade</h2>
          <p class="text-sm text-gray-400">Review what will happen, then start the upgrade.</p>
        </div>

        <div class="rounded-xl bg-gray-800/60 border border-gray-700 p-4 text-sm space-y-2.5">
          <div class="flex justify-between items-center">
            <span class="text-gray-400">Upgrading from</span>
            <span class="font-mono text-white">{{ upgradeContext.currentVersion }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-400">Upgrading to</span>
            <span class="font-mono font-semibold text-indigo-300">{{ targetVersion }}</span>
          </div>
          <div class="flex justify-between items-center border-t border-gray-700 pt-2.5 mt-1">
            <span class="text-gray-400">Package source</span>
            <span class="text-gray-300">
              {{ skipExtract ? 'Already uploaded files' : downloadLatest ? `Built-in download from ${release?.repo || upgradeContext.updateRepo}` : 'Uploaded ZIP package' }}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-400">Database migrations</span>
            <span class="text-green-400">Will run</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-400">Cache</span>
            <span class="text-green-400">Will be cleared and rebuilt</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-400">License ping</span>
            <span class="text-green-400">Will run after success</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-400">.env file</span>
            <span class="text-gray-300">Preserved</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-400">Uploaded files and storage</span>
            <span class="text-gray-300">Preserved</span>
          </div>
        </div>

        <div class="rounded-lg bg-amber-900/20 border border-amber-700/40 px-4 py-3 text-sm text-amber-300">
          <strong>Heads up:</strong> The site may be briefly unavailable during the upgrade while caches rebuild and migrations run.
        </div>

        <div v-if="upgradeError" class="rounded-lg bg-red-900/30 border border-red-700/50 px-4 py-3 text-sm text-red-300">
          <p class="font-medium mb-1">Upgrade failed</p>
          <p>{{ upgradeError }}</p>
          <ul v-if="upgradeLog.length" class="mt-2 space-y-0.5 text-xs text-red-400 font-mono">
            <li v-for="(line, index) in upgradeLog" :key="index">{{ line }}</li>
          </ul>
        </div>

        <div class="flex gap-3">
          <button
            @click="step = 3"
            class="flex-1 border border-gray-700 text-gray-400 hover:text-white hover:border-gray-500 font-medium py-2.5 rounded-xl transition-colors text-sm"
          >
            Back
          </button>
          <button
            @click="runUpgrade"
            class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 rounded-xl transition-colors"
          >
            Start Upgrade
          </button>
        </div>
      </div>

      <div v-if="step === 5" class="bg-gray-900 rounded-2xl border border-gray-800 p-8 text-center space-y-5">
        <div class="flex justify-center">
          <svg class="animate-spin h-12 w-12 text-indigo-400" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
          </svg>
        </div>
        <div>
          <p class="text-white font-semibold text-lg">Upgrading...</p>
          <p class="text-gray-400 text-sm mt-1">Downloading files if needed, extracting code, running migrations, rebuilding cache, and syncing the license.</p>
        </div>
      </div>

      <div v-if="step === 6" class="bg-gray-900 rounded-2xl border border-gray-800 p-6 space-y-5">
        <div class="text-center">
          <div class="flex justify-center mb-3">
            <div class="h-14 w-14 rounded-full bg-green-500/20 flex items-center justify-center">
              <svg class="h-8 w-8 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </div>
          </div>
          <h2 class="text-xl font-bold text-white">Upgrade complete</h2>
          <p class="text-gray-400 text-sm mt-1">
            Strata Service Billing and Support Platform has been upgraded to
            <span class="font-mono text-indigo-300 font-semibold">{{ newVersion }}</span>
          </p>
        </div>

        <div v-if="upgradeLog.length" class="rounded-xl bg-gray-800/60 border border-gray-700 p-4">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Upgrade log</p>
          <ul class="space-y-1 text-xs text-gray-300 font-mono">
            <li v-for="(line, index) in upgradeLog" :key="index" class="flex gap-2">
              <span class="text-green-500 shrink-0">Y</span>
              <span>{{ line }}</span>
            </li>
          </ul>
        </div>

        <a
          href="/admin"
          class="block w-full text-center bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 rounded-xl transition-colors"
        >
          Go to Admin Dashboard
        </a>
      </div>
    </div>

    <p class="mt-8 text-xs text-gray-700">Strata Service Billing and Support Platform · Upgrade Wizard</p>
  </div>
</template>
