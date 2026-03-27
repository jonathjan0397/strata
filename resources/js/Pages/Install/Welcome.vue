<script setup>
import { ref, reactive, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  installType: { type: String, default: 'dev' }, // 'zip' = shared hosting, 'dev' = VPS/clone
})

// ── Wizard state ──────────────────────────────────────────────────────────────
// Steps: 1=welcome  2=requirements  3=database  4=environment  5=site  6=admin  7=installing  8=done
const step = ref(1)

const reqs          = ref(null)
const dbStatus      = ref(null)
const installError  = ref(null)
const installResult = ref(null)  // { queue, storage_mode, app_url }

const db = reactive({
  host:     'localhost',
  port:     3306,
  name:     'strata',
  username: '',
  password: '',
})

// Environment step — queue mode.
// Default: sync for ZIP/shared installs, database for VPS/dev.
const env = reactive({
  queue: props.installType === 'zip' ? 'sync' : 'database',
})

const site = reactive({
  name: 'Strata',
  url:  window.location.origin,
})

const admin = reactive({
  name:     '',
  email:    '',
  password: '',
  confirm:  '',
})

// ── Progress bar labels ───────────────────────────────────────────────────────
const stepLabels = ['Welcome', 'Requirements', 'Database', 'Environment', 'Site', 'Admin', 'Complete']

// ── Computed helpers ──────────────────────────────────────────────────────────
const reqsPassed = computed(() => reqs.value?.all_pass)

const dbValid = computed(() =>
  db.host && db.port && db.name && db.username
)

const adminValid = computed(() =>
  admin.name && admin.email && admin.password.length >= 8 &&
  admin.password === admin.confirm
)

// Cron command shown on the done screen.
const cronCommand = computed(() => {
  const url = installResult.value?.app_url ?? site.url
  const host = new URL(url).hostname
  return `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`
})

const workerCommand = computed(() => {
  return `php artisan queue:work --sleep=3 --tries=3`
})

// ── Step actions ──────────────────────────────────────────────────────────────
async function loadRequirements() {
  reqs.value = null
  const { data } = await axios.get('/install/requirements')
  reqs.value = data
  step.value = 2
}

async function testDb() {
  dbStatus.value = 'testing'
  try {
    const { data } = await axios.post('/install/test-database', {
      db_host:     db.host,
      db_port:     db.port,
      db_name:     db.name,
      db_username: db.username,
      db_password: db.password,
    })
    dbStatus.value = data.success
      ? { ok: true, version: data.version }
      : { ok: false, error: data.error }
  } catch (e) {
    dbStatus.value = { ok: false, error: e.response?.data?.error ?? 'Connection failed.' }
  }
}

function goToEnv() {
  if (dbStatus.value?.ok) step.value = 4
}

async function runInstall() {
  step.value = 7
  installError.value = null
  try {
    const { data } = await axios.post('/install/run', {
      db_host:          db.host,
      db_port:          db.port,
      db_name:          db.name,
      db_username:      db.username,
      db_password:      db.password,
      app_name:         site.name,
      app_url:          site.url,
      admin_name:       admin.name,
      admin_email:      admin.email,
      admin_password:   admin.password,
      queue_connection: env.queue,
    })
    installResult.value = data
    step.value = 8
  } catch (e) {
    installError.value = e.response?.data?.error ?? 'Installation failed. Check server logs.'
    step.value = 6
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function isWarn(check) {
  return check.warn && !check.pass
}
</script>

<template>
  <div class="min-h-screen bg-gray-950 flex flex-col items-center justify-center px-4 py-12">

    <!-- Header -->
    <div class="mb-10 text-center">
      <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-2xl mx-auto mb-4">S</div>
      <h1 class="text-3xl font-bold text-white tracking-tight">Strata</h1>
      <p class="text-gray-400 mt-1 text-sm">Billing &amp; Automation Platform — Setup Wizard</p>
    </div>

    <!-- Progress bar (steps 1–7, step 8 = done) -->
    <div class="w-full max-w-lg mb-8">
      <div class="flex justify-between text-xs text-gray-500 mb-1.5">
        <span v-for="(label, i) in stepLabels" :key="i"
          :class="step > i + 1 ? 'text-indigo-400' : step === i + 1 ? 'text-white font-medium' : ''">
          {{ label }}
        </span>
      </div>
      <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
        <div class="h-full bg-indigo-600 rounded-full transition-all duration-500"
          :style="{ width: ((Math.min(step, 8) - 1) / 7 * 100) + '%' }" />
      </div>
    </div>

    <!-- Card -->
    <div class="w-full max-w-lg bg-gray-900 rounded-2xl border border-gray-800 shadow-2xl p-8">

      <!-- Step 1: Welcome -->
      <div v-if="step === 1">
        <h2 class="text-xl font-semibold text-white mb-3">Welcome to Strata</h2>
        <p class="text-sm text-gray-400 mb-5 leading-relaxed">
          This wizard will guide you through configuring your database, creating your admin account,
          and completing the installation. No command-line access is required.
        </p>

        <!-- Install type badge -->
        <div class="mb-4 flex items-center gap-2 text-xs">
          <span v-if="installType === 'zip'"
            class="inline-flex items-center gap-1.5 rounded-full bg-blue-900/40 border border-blue-700 text-blue-300 px-3 py-1">
            <span class="h-1.5 w-1.5 rounded-full bg-blue-400 inline-block"></span>
            Shared Hosting Install — pre-built package detected
          </span>
          <span v-else
            class="inline-flex items-center gap-1.5 rounded-full bg-purple-900/40 border border-purple-700 text-purple-300 px-3 py-1">
            <span class="h-1.5 w-1.5 rounded-full bg-purple-400 inline-block"></span>
            VPS / Developer Install
          </span>
        </div>

        <div class="bg-gray-800 rounded-lg p-4 text-sm text-gray-300 mb-6 space-y-1.5">
          <p class="font-medium text-white mb-2">Before you begin, have ready:</p>
          <p>· MySQL/MariaDB database name, host, username &amp; password</p>
          <p>· The URL this site will be accessed from</p>
          <p>· Your admin email address and a secure password</p>
        </div>
        <button @click="loadRequirements"
          class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg py-2.5 text-sm transition-colors">
          Begin →
        </button>
      </div>

      <!-- Step 2: Requirements -->
      <div v-else-if="step === 2">
        <h2 class="text-xl font-semibold text-white mb-5">Server Requirements</h2>
        <div v-if="!reqs" class="text-center text-gray-400 py-8">Checking…</div>
        <div v-else class="space-y-2 mb-6">
          <div v-for="(check, key) in reqs.checks" :key="key"
            class="flex items-center justify-between rounded-lg px-3 py-2 text-sm"
            :class="check.pass
              ? 'bg-green-900/20 border border-green-800/50'
              : isWarn(check)
                ? 'bg-yellow-900/20 border border-yellow-800/50'
                : 'bg-red-900/20 border border-red-800/50'"
          >
            <span :class="check.pass ? 'text-green-300' : isWarn(check) ? 'text-yellow-300' : 'text-red-300'">
              {{ check.label }}
            </span>
            <span class="text-xs font-mono"
              :class="check.pass ? 'text-green-500' : isWarn(check) ? 'text-yellow-500' : 'text-red-500'">
              {{ check.pass ? '✓' : isWarn(check) ? '⚠' : '✗' }}{{ check.detail ? ' ' + check.detail : '' }}
            </span>
          </div>
        </div>
        <div v-if="reqs && !reqsPassed" class="bg-red-900/30 border border-red-800 rounded-lg p-3 text-sm text-red-300 mb-4">
          Fix the failed requirements above, then reload this page to re-check.
        </div>
        <div v-if="reqs && reqsPassed && reqs.checks && Object.values(reqs.checks).some(c => c.warn && !c.pass)"
          class="bg-yellow-900/30 border border-yellow-800 rounded-lg p-3 text-sm text-yellow-300 mb-4">
          ⚠ Some optional checks have warnings. The installer can continue, but some features may behave differently.
        </div>
        <div class="flex gap-3">
          <button @click="step = 1" class="flex-1 border border-gray-700 text-gray-300 hover:bg-gray-800 rounded-lg py-2.5 text-sm transition-colors">← Back</button>
          <button @click="step = 3" :disabled="!reqsPassed"
            class="flex-1 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white font-medium rounded-lg py-2.5 text-sm transition-colors">
            Continue →
          </button>
        </div>
      </div>

      <!-- Step 3: Database -->
      <div v-else-if="step === 3">
        <h2 class="text-xl font-semibold text-white mb-5">Database Connection</h2>
        <div class="space-y-3 mb-4">
          <div class="grid grid-cols-3 gap-3">
            <div class="col-span-2">
              <label class="block text-xs font-medium text-gray-400 mb-1">Host</label>
              <input v-model="db.host" type="text" placeholder="localhost"
                class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-400 mb-1">Port</label>
              <input v-model="db.port" type="number" placeholder="3306"
                class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Database Name</label>
            <input v-model="db.name" type="text" placeholder="strata"
              class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Username</label>
            <input v-model="db.username" type="text"
              class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Password</label>
            <input v-model="db.password" type="password"
              class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
        </div>

        <!-- Test result -->
        <div v-if="dbStatus && dbStatus !== 'testing'" class="mb-4 rounded-lg px-3 py-2.5 text-sm"
          :class="dbStatus.ok ? 'bg-green-900/30 border border-green-800 text-green-300' : 'bg-red-900/30 border border-red-800 text-red-300'"
        >
          <span v-if="dbStatus.ok">✓ Connected — MySQL {{ dbStatus.version }}</span>
          <span v-else>✗ {{ dbStatus.error }}</span>
        </div>

        <div class="flex gap-3">
          <button @click="step = 2" class="flex-1 border border-gray-700 text-gray-300 hover:bg-gray-800 rounded-lg py-2.5 text-sm transition-colors">← Back</button>
          <button @click="testDb" :disabled="!dbValid || dbStatus === 'testing'"
            class="flex-1 border border-indigo-700 text-indigo-300 hover:bg-indigo-900/40 disabled:opacity-40 rounded-lg py-2.5 text-sm transition-colors">
            {{ dbStatus === 'testing' ? 'Testing…' : 'Test Connection' }}
          </button>
          <button @click="goToEnv" :disabled="!dbStatus?.ok"
            class="flex-1 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white font-medium rounded-lg py-2.5 text-sm transition-colors">
            Continue →
          </button>
        </div>
      </div>

      <!-- Step 4: Environment / Queue Mode -->
      <div v-else-if="step === 4">
        <h2 class="text-xl font-semibold text-white mb-2">Environment Type</h2>
        <p class="text-sm text-gray-400 mb-5">Choose the queue mode that matches your server environment.</p>

        <div class="space-y-3 mb-6">
          <!-- Sync -->
          <label class="block cursor-pointer">
            <input type="radio" v-model="env.queue" value="sync" class="sr-only" />
            <div class="rounded-xl border-2 p-4 transition-colors"
              :class="env.queue === 'sync' ? 'border-indigo-500 bg-indigo-900/20' : 'border-gray-700 hover:border-gray-600'">
              <div class="flex items-start justify-between">
                <div>
                  <p class="text-sm font-semibold text-white">Sync <span class="ml-1 text-xs font-normal text-gray-400">— Shared Hosting</span></p>
                  <p class="mt-1 text-xs text-gray-400 leading-relaxed">
                    Jobs (emails, provisioning) run immediately in-process. No cron worker needed.
                    Recommended for cPanel / Plesk / DirectAdmin shared accounts.
                  </p>
                </div>
                <div class="ml-4 mt-0.5 h-4 w-4 flex-shrink-0 rounded-full border-2 transition-colors"
                  :class="env.queue === 'sync' ? 'border-indigo-500 bg-indigo-500' : 'border-gray-600'"></div>
              </div>
            </div>
          </label>

          <!-- Database -->
          <label class="block cursor-pointer">
            <input type="radio" v-model="env.queue" value="database" class="sr-only" />
            <div class="rounded-xl border-2 p-4 transition-colors"
              :class="env.queue === 'database' ? 'border-indigo-500 bg-indigo-900/20' : 'border-gray-700 hover:border-gray-600'">
              <div class="flex items-start justify-between">
                <div>
                  <p class="text-sm font-semibold text-white">Database Queue <span class="ml-1 text-xs font-normal text-gray-400">— VPS / Dedicated</span></p>
                  <p class="mt-1 text-xs text-gray-400 leading-relaxed">
                    Jobs are queued in the database and processed by a background worker.
                    Requires a queue worker (Supervisor or cron) and Laravel Horizon optionally.
                  </p>
                </div>
                <div class="ml-4 mt-0.5 h-4 w-4 flex-shrink-0 rounded-full border-2 transition-colors"
                  :class="env.queue === 'database' ? 'border-indigo-500 bg-indigo-500' : 'border-gray-600'"></div>
              </div>
            </div>
          </label>
        </div>

        <div class="flex gap-3">
          <button @click="step = 3" class="flex-1 border border-gray-700 text-gray-300 hover:bg-gray-800 rounded-lg py-2.5 text-sm transition-colors">← Back</button>
          <button @click="step = 5"
            class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg py-2.5 text-sm transition-colors">
            Continue →
          </button>
        </div>
      </div>

      <!-- Step 5: Site Configuration -->
      <div v-else-if="step === 5">
        <h2 class="text-xl font-semibold text-white mb-5">Site Configuration</h2>
        <div class="space-y-3 mb-6">
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Site Name</label>
            <input v-model="site.name" type="text" placeholder="Strata"
              class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Site URL</label>
            <input v-model="site.url" type="url" placeholder="https://billing.yourdomain.com"
              class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <p class="mt-1 text-xs text-gray-500">Must match the URL you will access this site from. No trailing slash.</p>
          </div>
        </div>
        <div class="flex gap-3">
          <button @click="step = 4" class="flex-1 border border-gray-700 text-gray-300 hover:bg-gray-800 rounded-lg py-2.5 text-sm transition-colors">← Back</button>
          <button @click="step = 6" :disabled="!site.name || !site.url"
            class="flex-1 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white font-medium rounded-lg py-2.5 text-sm transition-colors">
            Continue →
          </button>
        </div>
      </div>

      <!-- Step 6: Admin Account -->
      <div v-else-if="step === 6">
        <h2 class="text-xl font-semibold text-white mb-5">Admin Account</h2>

        <div v-if="installError" class="mb-4 bg-red-900/30 border border-red-800 rounded-lg px-3 py-2.5 text-sm text-red-300">
          {{ installError }}
        </div>

        <div class="space-y-3 mb-6">
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Full Name</label>
            <input v-model="admin.name" type="text"
              class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Email Address</label>
            <input v-model="admin.email" type="email"
              class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Password <span class="text-gray-500">(min 8 characters)</span></label>
            <input v-model="admin.password" type="password"
              class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-400 mb-1">Confirm Password</label>
            <input v-model="admin.confirm" type="password"
              class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :class="admin.confirm && admin.confirm !== admin.password ? 'border-red-600' : ''" />
            <p v-if="admin.confirm && admin.confirm !== admin.password" class="mt-1 text-xs text-red-400">Passwords do not match.</p>
          </div>
        </div>
        <div class="flex gap-3">
          <button @click="step = 5" class="flex-1 border border-gray-700 text-gray-300 hover:bg-gray-800 rounded-lg py-2.5 text-sm transition-colors">← Back</button>
          <button @click="runInstall" :disabled="!adminValid"
            class="flex-1 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white font-medium rounded-lg py-2.5 text-sm transition-colors">
            Install Strata →
          </button>
        </div>
      </div>

      <!-- Step 7: Installing -->
      <div v-else-if="step === 7" class="text-center py-8">
        <div class="flex justify-center mb-5">
          <svg class="h-12 w-12 text-indigo-400 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>
        <h2 class="text-xl font-semibold text-white mb-2">Installing…</h2>
        <p class="text-sm text-gray-400">Creating tables, seeding roles, and setting up your account.<br>This may take 15–30 seconds.</p>
      </div>

      <!-- Step 8: Complete -->
      <div v-else-if="step === 8" class="py-2">
        <div class="flex justify-center mb-5">
          <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-900/40 border border-green-700">
            <svg class="h-8 w-8 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
        </div>
        <h2 class="text-xl font-semibold text-white mb-2 text-center">Installation Complete!</h2>
        <p class="text-sm text-gray-400 mb-5 text-center">
          Strata has been installed successfully.<br>
          The setup wizard has been locked and cannot be run again.
        </p>

        <!-- Post-install setup instructions -->
        <div class="space-y-3 mb-6">

          <!-- Cron job (always required) -->
          <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
            <p class="text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Required — Cron Job</p>
            <p class="text-xs text-gray-400 mb-2">
              Add this to your crontab (or cPanel Cron Jobs) to enable scheduled billing, domain renewal, and overdue checks:
            </p>
            <code class="block bg-gray-900 text-green-400 text-xs rounded-lg px-3 py-2 font-mono break-all">
              * * * * * php {{ installResult?.app_url?.replace('https://', '').replace('http://', '') ?? '' }}/path/to/artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1
            </code>
            <p class="mt-2 text-xs text-gray-500">
              Replace <code class="text-gray-400">/path/to/artisan</code> with the full server path to the <code class="text-gray-400">artisan</code> file.
            </p>
          </div>

          <!-- Queue worker — only shown for database queue -->
          <div v-if="installResult?.queue === 'database'" class="bg-gray-800 rounded-xl border border-gray-700 p-4">
            <p class="text-xs font-semibold text-gray-300 uppercase tracking-wider mb-2">Required — Queue Worker</p>
            <p class="text-xs text-gray-400 mb-2">
              You selected the Database queue. Start a persistent worker via Supervisor, or add a cron-based worker:
            </p>
            <code class="block bg-gray-900 text-green-400 text-xs rounded-lg px-3 py-2 font-mono">
              php artisan queue:work --sleep=3 --tries=3
            </code>
          </div>

          <!-- Sync mode note -->
          <div v-if="installResult?.queue === 'sync'" class="bg-blue-900/20 rounded-xl border border-blue-800/50 p-4">
            <p class="text-xs font-semibold text-blue-300 uppercase tracking-wider mb-1">Queue: Sync Mode</p>
            <p class="text-xs text-blue-300/80">
              Jobs run inline — no queue worker is needed. Email sending and provisioning happen synchronously on each request.
            </p>
          </div>

          <!-- Storage controller note -->
          <div v-if="installResult?.storage_mode === 'controller'" class="bg-yellow-900/20 rounded-xl border border-yellow-800/50 p-4">
            <p class="text-xs font-semibold text-yellow-300 uppercase tracking-wider mb-1">Storage: Controller Mode</p>
            <p class="text-xs text-yellow-300/80">
              Symlinks are disabled on this host. Uploaded files (logos, attachments) will be served through the application. This is fully supported.
            </p>
          </div>

        </div>

        <a :href="(installResult?.app_url ?? site.url) + '/login'"
          class="block w-full text-center bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg px-6 py-2.5 text-sm transition-colors">
          Go to Login →
        </a>
      </div>

    </div>

    <!-- Footer -->
    <p class="mt-6 text-xs text-gray-600">Strata — Licensed under FSL-1.1-Apache-2.0</p>
  </div>
</template>
