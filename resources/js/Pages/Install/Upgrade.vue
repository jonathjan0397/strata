<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  currentVersion:   { type: String, default: 'unknown' },
  codeVersion:      { type: String, default: 'unknown' },
  alreadyUpdated:   { type: Boolean, default: false },
  hasZipExtension:  { type: Boolean, default: false },
  checks:           { type: Array, default: () => [] },
  hardFail:         { type: Boolean, default: false },
  installedAt:      { type: String, default: null },
  lastUpgradedAt:   { type: String, default: null },
})

// ── Wizard state ───────────────────────────────────────────────────────────────
// Steps: 1=welcome/checks  2=auth  3=upload  4=confirm  5=upgrading  6=done
const step       = ref(1)
const authError  = ref(null)
const upgradeError = ref(null)
const upgradeLog   = ref([])
const newVersion   = ref(null)
const zipInfo      = ref(null)  // { version } after peek
const zipFile      = ref(null)
const skipExtract  = ref(false)

const auth = {
  email:    ref(''),
  password: ref(''),
}

// ── Computed ───────────────────────────────────────────────────────────────────
const checksAllPass = computed(() =>
  props.checks.every(c => c.pass || c.warn)
)

const authValid = computed(() =>
  auth.email.value.trim() && auth.password.value.length >= 6
)

const canUpgrade = computed(() => {
  if (!skipExtract.value && !zipInfo.value) return false
  return true
})

// Version shown at confirm step
const targetVersion = computed(() =>
  skipExtract.value ? props.codeVersion : (zipInfo.value?.version ?? '…')
)

// ── Step actions ───────────────────────────────────────────────────────────────

async function verifyAuth() {
  authError.value = null
  try {
    await axios.post('/upgrade/verify', {
      email:    auth.email.value.trim(),
      password: b64(auth.password.value),
    })
    step.value = 3
  } catch (e) {
    authError.value = e.response?.data?.error ?? 'Verification failed.'
  }
}

async function onZipSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  zipFile.value = file
  zipInfo.value = null

  try {
    const fd = new FormData()
    fd.append('zip', file)
    const { data } = await axios.post('/upgrade/peek', fd)
    if (data.success) {
      zipInfo.value = { version: data.version }
    } else {
      zipFile.value = null
      alert(data.error ?? 'Could not read ZIP.')
    }
  } catch (e) {
    zipFile.value = null
    alert(e.response?.data?.error ?? 'Could not inspect ZIP file.')
  }
}

function useManualMode() {
  skipExtract.value = true
  zipFile.value     = null
  zipInfo.value     = null
  step.value        = 4
}

function goConfirm() {
  if (skipExtract.value || zipInfo.value) step.value = 4
}

async function runUpgrade() {
  step.value     = 5
  upgradeError.value = null
  upgradeLog.value   = []

  try {
    const fd = new FormData()
    fd.append('email',    auth.email.value.trim())
    fd.append('password', b64(auth.password.value))

    if (!skipExtract.value && zipFile.value) {
      fd.append('zip', zipFile.value)
    }

    const { data } = await axios.post('/upgrade/run', fd, {
      timeout: 300_000, // 5 minutes — migrations + extract can be slow
    })

    upgradeLog.value = data.log ?? []
    newVersion.value = data.new_version
    step.value = 6
  } catch (e) {
    upgradeError.value = e.response?.data?.error ?? 'Upgrade failed. Check server logs.'
    upgradeLog.value   = e.response?.data?.log    ?? []
    step.value = 4  // Back to confirm so they can retry
  }
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function b64(str) {
  return btoa(unescape(encodeURIComponent(str)))
}

function fmt(iso) {
  if (!iso) return '—'
  try { return new Date(iso).toLocaleString() } catch { return iso }
}
</script>

<template>
  <div class="min-h-screen bg-gray-950 flex flex-col items-center justify-center px-4 py-12">

    <!-- Card -->
    <div class="w-full max-w-lg">

      <!-- Header -->
      <div class="text-center mb-8">
        <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 shadow-lg shadow-indigo-500/30 text-white font-bold text-2xl mb-4">S</div>
        <h1 class="text-2xl font-bold text-white">Strata Upgrade Wizard</h1>
        <p class="text-gray-400 text-sm mt-1">Upgrade your Strata installation without CLI access</p>
      </div>

      <!-- Step progress pills -->
      <div class="flex items-center justify-center gap-1.5 mb-8">
        <div v-for="i in 6" :key="i"
          :class="['h-1.5 rounded-full transition-all duration-300',
            i === step  ? 'w-8 bg-indigo-400' :
            i < step    ? 'w-4 bg-indigo-600' :
                          'w-4 bg-gray-700']">
        </div>
      </div>

      <!-- ── Step 1: Welcome + pre-flight checks ───────────────────────────── -->
      <div v-if="step === 1" class="bg-gray-900 rounded-2xl border border-gray-800 p-6 space-y-5">
        <div>
          <h2 class="text-lg font-semibold text-white mb-1">Pre-upgrade checks</h2>
          <p class="text-sm text-gray-400">Review your current installation before proceeding.</p>
        </div>

        <!-- Version comparison -->
        <div class="rounded-xl bg-gray-800/60 border border-gray-700 p-4 text-sm space-y-2">
          <div class="flex justify-between">
            <span class="text-gray-400">Installed version</span>
            <span class="font-mono font-medium text-white">{{ currentVersion }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-400">Code version (on disk)</span>
            <span class="font-mono font-medium" :class="alreadyUpdated ? 'text-amber-400' : 'text-gray-300'">{{ codeVersion }}</span>
          </div>
          <div v-if="installedAt" class="flex justify-between text-xs pt-1 border-t border-gray-700 mt-1">
            <span class="text-gray-500">Originally installed</span>
            <span class="text-gray-500">{{ fmt(installedAt) }}</span>
          </div>
          <div v-if="lastUpgradedAt" class="flex justify-between text-xs">
            <span class="text-gray-500">Last upgraded</span>
            <span class="text-gray-500">{{ fmt(lastUpgradedAt) }}</span>
          </div>
        </div>

        <!-- Server checks -->
        <ul class="space-y-2">
          <li v-for="c in checks" :key="c.label"
              class="flex items-start gap-3 text-sm">
            <span :class="[
              'mt-0.5 h-4 w-4 shrink-0 rounded-full flex items-center justify-center text-xs font-bold',
              c.pass  ? 'bg-green-500/20 text-green-400' :
              c.warn  ? 'bg-amber-500/20 text-amber-400' :
                        'bg-red-500/20 text-red-400'
            ]">{{ c.pass ? '✓' : c.warn ? '!' : '✗' }}</span>
            <div class="min-w-0">
              <span :class="c.pass ? 'text-gray-200' : c.warn ? 'text-amber-300' : 'text-red-300'">{{ c.label }}</span>
              <p v-if="c.detail && !c.pass" class="text-xs text-gray-500 mt-0.5 break-all">{{ c.detail }}</p>
            </div>
          </li>
        </ul>

        <div v-if="hardFail" class="rounded-lg bg-red-900/30 border border-red-700/50 px-4 py-3 text-sm text-red-300">
          One or more required checks failed. Fix the issues above before continuing.
        </div>

        <div v-if="alreadyUpdated" class="rounded-lg bg-amber-900/20 border border-amber-700/40 px-4 py-3 text-sm text-amber-300">
          Your code files already show a different version than what's recorded in the lock file. You may have already uploaded new files via FTP — the wizard can run migrations and update the lock file without re-extracting files.
        </div>

        <button
          :disabled="hardFail"
          @click="step = 2"
          class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-medium py-2.5 rounded-xl transition-colors"
        >
          Continue
        </button>
      </div>

      <!-- ── Step 2: Admin verification ───────────────────────────────────── -->
      <div v-if="step === 2" class="bg-gray-900 rounded-2xl border border-gray-800 p-6 space-y-5">
        <div>
          <h2 class="text-lg font-semibold text-white mb-1">Verify admin credentials</h2>
          <p class="text-sm text-gray-400">Enter your super-admin email and password to authorize the upgrade.</p>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Admin Email</label>
            <input v-model="auth.email.value" type="email" autocomplete="email"
              class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="admin@example.com" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Password</label>
            <input v-model="auth.password.value" type="password" autocomplete="current-password"
              class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              placeholder="••••••••" />
          </div>
        </div>

        <div v-if="authError" class="rounded-lg bg-red-900/30 border border-red-700/50 px-4 py-3 text-sm text-red-300">
          {{ authError }}
        </div>

        <div class="flex gap-3">
          <button @click="step = 1" class="flex-1 border border-gray-700 text-gray-400 hover:text-white hover:border-gray-500 font-medium py-2.5 rounded-xl transition-colors text-sm">Back</button>
          <button @click="verifyAuth" :disabled="!authValid"
            class="flex-1 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-medium py-2.5 rounded-xl transition-colors">
            Verify
          </button>
        </div>
      </div>

      <!-- ── Step 3: Upload ZIP ─────────────────────────────────────────────── -->
      <div v-if="step === 3" class="bg-gray-900 rounded-2xl border border-gray-800 p-6 space-y-5">
        <div>
          <h2 class="text-lg font-semibold text-white mb-1">Upload release package</h2>
          <p class="text-sm text-gray-400">Upload the <code class="bg-gray-800 px-1.5 py-0.5 rounded text-indigo-300">Strata-*.zip</code> release file downloaded from GitHub.</p>
        </div>

        <div v-if="hasZipExtension">
          <!-- Drop zone -->
          <label class="flex flex-col items-center justify-center gap-3 border-2 border-dashed border-gray-700 hover:border-indigo-500 rounded-xl p-8 cursor-pointer transition-colors group">
            <svg class="h-10 w-10 text-gray-600 group-hover:text-indigo-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            <div class="text-center">
              <p v-if="!zipFile" class="text-sm text-gray-400">Click to select or drag &amp; drop</p>
              <p v-else class="text-sm text-green-400 font-medium">{{ zipFile.name }}</p>
              <p class="text-xs text-gray-600 mt-1">Strata-*.zip release package</p>
            </div>
            <input type="file" accept=".zip" class="hidden" @change="onZipSelected" />
          </label>

          <!-- ZIP version detected -->
          <div v-if="zipInfo" class="mt-3 rounded-lg bg-green-900/20 border border-green-700/40 px-4 py-3 text-sm text-green-300 flex items-center gap-2">
            <span class="text-green-400">✓</span>
            Detected Strata <strong>{{ zipInfo.version }}</strong> — ready to install.
          </div>
        </div>

        <div v-else class="rounded-lg bg-amber-900/20 border border-amber-700/40 px-4 py-3 text-sm text-amber-300">
          PHP ext-zip is not available on this server. You cannot upload a ZIP through this wizard. Upload the new files via FTP/cPanel File Manager instead, then use "Files already uploaded" below.
        </div>

        <!-- Manual / already-uploaded path -->
        <div class="border-t border-gray-800 pt-4">
          <p class="text-xs text-gray-500 mb-2">Already uploaded new files via FTP?</p>
          <button @click="useManualMode"
            class="w-full border border-gray-700 hover:border-gray-500 text-gray-400 hover:text-white text-sm font-medium py-2.5 rounded-xl transition-colors">
            Files already uploaded — skip extraction, run migrations only
          </button>
        </div>

        <div class="flex gap-3 pt-1">
          <button @click="step = 2" class="flex-1 border border-gray-700 text-gray-400 hover:text-white hover:border-gray-500 font-medium py-2.5 rounded-xl transition-colors text-sm">Back</button>
          <button @click="goConfirm" :disabled="!canUpgrade"
            class="flex-1 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white font-medium py-2.5 rounded-xl transition-colors">
            Continue
          </button>
        </div>
      </div>

      <!-- ── Step 4: Confirm ────────────────────────────────────────────────── -->
      <div v-if="step === 4" class="bg-gray-900 rounded-2xl border border-gray-800 p-6 space-y-5">
        <div>
          <h2 class="text-lg font-semibold text-white mb-1">Confirm upgrade</h2>
          <p class="text-sm text-gray-400">Review what will happen, then start the upgrade.</p>
        </div>

        <div class="rounded-xl bg-gray-800/60 border border-gray-700 p-4 text-sm space-y-2.5">
          <div class="flex justify-between items-center">
            <span class="text-gray-400">Upgrading from</span>
            <span class="font-mono text-white">{{ currentVersion }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-400">Upgrading to</span>
            <span class="font-mono font-semibold text-indigo-300">{{ targetVersion }}</span>
          </div>
          <div class="flex justify-between items-center border-t border-gray-700 pt-2.5 mt-1">
            <span class="text-gray-400">File extraction</span>
            <span :class="skipExtract ? 'text-gray-500' : 'text-green-400'">{{ skipExtract ? 'Skipped' : 'From uploaded ZIP' }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-400">Database migrations</span>
            <span class="text-green-400">Will run</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-400">Cache</span>
            <span class="text-green-400">Will be cleared + rebuilt</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-400">.env file</span>
            <span class="text-gray-300">Preserved (not touched)</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-gray-400">Uploaded files / storage</span>
            <span class="text-gray-300">Preserved (not touched)</span>
          </div>
        </div>

        <div class="rounded-lg bg-amber-900/20 border border-amber-700/40 px-4 py-3 text-sm text-amber-300">
          <strong>Heads up:</strong> The site may be briefly unavailable during the upgrade while caches rebuild and migrations run. This typically takes under 30 seconds.
        </div>

        <div v-if="upgradeError" class="rounded-lg bg-red-900/30 border border-red-700/50 px-4 py-3 text-sm text-red-300">
          <p class="font-medium mb-1">Upgrade failed</p>
          <p>{{ upgradeError }}</p>
          <ul v-if="upgradeLog.length" class="mt-2 space-y-0.5 text-xs text-red-400 font-mono">
            <li v-for="(line, i) in upgradeLog" :key="i">{{ line }}</li>
          </ul>
        </div>

        <div class="flex gap-3">
          <button @click="skipExtract = false; step = 3" class="flex-1 border border-gray-700 text-gray-400 hover:text-white hover:border-gray-500 font-medium py-2.5 rounded-xl transition-colors text-sm">Back</button>
          <button @click="runUpgrade"
            class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 rounded-xl transition-colors">
            Start Upgrade
          </button>
        </div>
      </div>

      <!-- ── Step 5: Upgrading (in progress) ──────────────────────────────── -->
      <div v-if="step === 5" class="bg-gray-900 rounded-2xl border border-gray-800 p-8 text-center space-y-5">
        <div class="flex justify-center">
          <svg class="animate-spin h-12 w-12 text-indigo-400" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
          </svg>
        </div>
        <div>
          <p class="text-white font-semibold text-lg">Upgrading…</p>
          <p class="text-gray-400 text-sm mt-1">Extracting files, running migrations, rebuilding cache.<br>This may take up to a minute — do not close this page.</p>
        </div>
      </div>

      <!-- ── Step 6: Done ───────────────────────────────────────────────────── -->
      <div v-if="step === 6" class="bg-gray-900 rounded-2xl border border-gray-800 p-6 space-y-5">
        <div class="text-center">
          <div class="flex justify-center mb-3">
            <div class="h-14 w-14 rounded-full bg-green-500/20 flex items-center justify-center">
              <svg class="h-8 w-8 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </div>
          </div>
          <h2 class="text-xl font-bold text-white">Upgrade complete!</h2>
          <p class="text-gray-400 text-sm mt-1">
            Strata has been upgraded to <span class="font-mono text-indigo-300 font-semibold">{{ newVersion }}</span>
          </p>
        </div>

        <!-- Log output -->
        <div v-if="upgradeLog.length" class="rounded-xl bg-gray-800/60 border border-gray-700 p-4">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Upgrade log</p>
          <ul class="space-y-1 text-xs text-gray-300 font-mono">
            <li v-for="(line, i) in upgradeLog" :key="i" class="flex gap-2">
              <span class="text-green-500 shrink-0">✓</span>
              <span>{{ line }}</span>
            </li>
          </ul>
        </div>

        <a href="/admin/dashboard"
          class="block w-full text-center bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 rounded-xl transition-colors">
          Go to Admin Dashboard →
        </a>
      </div>

    </div>

    <!-- Footer -->
    <p class="mt-8 text-xs text-gray-700">Strata Service Billing and Support Platform · Upgrade Wizard</p>
  </div>
</template>
