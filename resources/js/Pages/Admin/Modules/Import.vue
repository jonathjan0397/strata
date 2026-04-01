<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
    module:   Object,
    products: Array,
})

// ── State ──────────────────────────────────────────────────────────────────────
const step = ref('loading')  // loading | mapping | accounts | importing | done | error

const fetchError   = ref(null)
const importError  = ref(null)

// Data from preview
const rawAccounts  = ref([])
const packages     = ref([])
const packageMap   = ref({})   // planName → productId|null

// Account selection
const selected     = ref({})   // username → bool
const autoCreate   = ref({})   // planName → bool  (create new product for this plan)

// Results
const results      = ref(null)

// ── Fetch accounts from panel ──────────────────────────────────────────────────
onMounted(fetchPreview)

async function fetchPreview() {
    step.value = 'loading'
    fetchError.value = null
    try {
        const res = await axios.post(route('admin.modules.import.preview', props.module.id))
        rawAccounts.value = res.data.accounts
        packages.value    = res.data.packages
        packageMap.value  = { ...res.data.package_map }

        // Default: select all non-already-imported accounts
        rawAccounts.value.forEach(a => {
            selected.value[a.username] = !a.already_imported
        })

        // Default: if a package has no product match, mark for auto-create
        packages.value.forEach(p => {
            autoCreate.value[p.name] = packageMap.value[p.name] == null
        })

        step.value = packages.value.length ? 'mapping' : 'accounts'
    } catch (e) {
        fetchError.value = e.response?.data?.error ?? e.message
        step.value = 'error'
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────────
const unmappedPlans = computed(() =>
    packages.value.filter(p => packageMap.value[p.name] == null)
)

const selectedCount = computed(() =>
    Object.values(selected.value).filter(Boolean).length
)

const newClientCount = computed(() =>
    rawAccounts.value.filter(a => selected.value[a.username] && !a.existing_client_id && !a.already_imported).length
)

function productName(id) {
    return props.products.find(p => p.id === id)?.name ?? '—'
}

function planForAccount(acct) {
    const plan = acct.plan || ''
    if (!plan) return { label: 'No plan', productId: null }
    const pid = packageMap.value[plan]
    if (pid) return { label: productName(pid), productId: pid }
    if (autoCreate.value[plan]) return { label: `Create "${plan}"`, productId: null, creating: true }
    return { label: `${plan} (unmapped)`, productId: null, unmapped: true }
}

function toggleAll(val) {
    rawAccounts.value.forEach(a => {
        if (!a.already_imported) selected.value[a.username] = val
    })
}

// ── Run import ─────────────────────────────────────────────────────────────────
async function runImport() {
    step.value = 'importing'
    importError.value = null

    const accountsToImport = rawAccounts.value.filter(a => selected.value[a.username])
    const autoCreatePlans  = packages.value
        .filter(p => autoCreate.value[p.name] && packageMap.value[p.name] == null)
        .map(p => p.name)

    try {
        const res = await axios.post(route('admin.modules.import.store', props.module.id), {
            accounts:             accountsToImport,
            package_map:          packageMap.value,
            auto_create_products: autoCreatePlans,
        })
        results.value = res.data
        step.value = 'done'
    } catch (e) {
        importError.value = e.response?.data?.error ?? e.message
        step.value = 'error'
    }
}

const inputCls  = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500'
const selectCls = inputCls
</script>

<template>
  <div class="max-w-4xl">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 mb-6 text-sm">
      <Link :href="route('admin.modules.index')" class="text-gray-400 hover:text-gray-600">Servers</Link>
      <span class="text-gray-300">/</span>
      <span class="text-gray-600 font-medium">{{ module.name }}</span>
      <span class="text-gray-300">/</span>
      <span class="text-gray-900 font-semibold">Import Accounts</span>
    </div>

    <!-- ── Step: Loading ────────────────────────────────────────────────────── -->
    <div v-if="step === 'loading'" class="bg-white rounded-xl border border-gray-200 p-10 text-center">
      <div class="animate-spin inline-block w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full mb-4"></div>
      <p class="text-sm text-gray-500">Connecting to <strong>{{ module.hostname }}</strong> and fetching accounts…</p>
    </div>

    <!-- ── Step: Error ──────────────────────────────────────────────────────── -->
    <div v-else-if="step === 'error'" class="bg-white rounded-xl border border-red-200 p-8">
      <p class="text-sm font-semibold text-red-700 mb-1">{{ importError ? 'Import failed' : 'Could not reach server' }}</p>
      <p class="text-sm text-red-600 font-mono">{{ importError ?? fetchError }}</p>
      <div class="mt-4 flex gap-2">
        <button v-if="!importError" @click="fetchPreview"
          class="px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
          Retry
        </button>
        <Link :href="route('admin.modules.index')"
          class="px-4 py-2 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50">
          Back to Servers
        </Link>
      </div>
    </div>

    <!-- ── Step: Package mapping ────────────────────────────────────────────── -->
    <div v-else-if="step === 'mapping'" class="space-y-4">
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-base font-semibold text-gray-900 mb-1">Map Plans to Products</h2>
        <p class="text-sm text-gray-500 mb-4">
          We found {{ packages.length }} plan(s) on <strong>{{ module.name }}</strong>.
          Map each to an existing Strata product, or let us create a placeholder product you can price later.
        </p>

        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100">
              <th class="text-left py-2 text-xs font-medium text-gray-500 w-1/3">Panel Plan</th>
              <th class="text-left py-2 text-xs font-medium text-gray-500">Map to Product</th>
              <th class="text-left py-2 text-xs font-medium text-gray-500 w-40">Auto-create</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="pkg in packages" :key="pkg.name" class="py-2">
              <td class="py-2 pr-4 font-mono text-xs text-gray-700">{{ pkg.name }}</td>
              <td class="py-2 pr-4">
                <select v-model="packageMap[pkg.name]" :class="selectCls"
                  :disabled="autoCreate[pkg.name]">
                  <option :value="null">— None / auto-create —</option>
                  <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
              </td>
              <td class="py-2">
                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                  <input type="checkbox" v-model="autoCreate[pkg.name]"
                    :disabled="packageMap[pkg.name] != null"
                    class="rounded text-indigo-600"
                    @change="autoCreate[pkg.name] && (packageMap[pkg.name] = null)" />
                  Create product
                </label>
              </td>
            </tr>
          </tbody>
        </table>

        <p v-if="unmappedPlans.length" class="mt-3 text-xs text-amber-600">
          {{ unmappedPlans.length }} plan(s) are unmapped — accounts on those plans will be imported without a linked product.
        </p>
      </div>

      <div class="flex gap-2">
        <button @click="step = 'accounts'"
          class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
          Continue →
        </button>
        <Link :href="route('admin.modules.index')"
          class="px-4 py-2 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50">
          Cancel
        </Link>
      </div>
    </div>

    <!-- ── Step: Select accounts ────────────────────────────────────────────── -->
    <div v-else-if="step === 'accounts'" class="space-y-4">
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-start justify-between gap-4 mb-4">
          <div>
            <h2 class="text-base font-semibold text-gray-900">Select Accounts to Import</h2>
            <p class="text-sm text-gray-500 mt-0.5">
              {{ rawAccounts.length }} accounts found. {{ selectedCount }} selected.
              <span v-if="newClientCount > 0" class="text-indigo-600"> {{ newClientCount }} new client{{ newClientCount !== 1 ? 's' : '' }} will be created.</span>
            </p>
          </div>
          <div class="flex gap-2 shrink-0">
            <button @click="toggleAll(true)" class="text-xs text-indigo-600 hover:underline">Select all</button>
            <span class="text-gray-300">|</span>
            <button @click="toggleAll(false)" class="text-xs text-gray-400 hover:underline">Deselect all</button>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="py-2 w-8"></th>
                <th class="text-left py-2 text-xs font-medium text-gray-500">Username</th>
                <th class="text-left py-2 text-xs font-medium text-gray-500">Domain</th>
                <th class="text-left py-2 text-xs font-medium text-gray-500">Email</th>
                <th class="text-left py-2 text-xs font-medium text-gray-500">Plan → Product</th>
                <th class="text-left py-2 text-xs font-medium text-gray-500">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="acct in rawAccounts" :key="acct.username"
                :class="['hover:bg-gray-50', acct.already_imported ? 'opacity-40' : '']">
                <td class="py-2 pr-2">
                  <input type="checkbox" v-model="selected[acct.username]"
                    :disabled="acct.already_imported"
                    class="rounded text-indigo-600" />
                </td>
                <td class="py-2 pr-3 font-mono text-xs text-gray-800">{{ acct.username }}</td>
                <td class="py-2 pr-3 text-gray-600 text-xs">{{ acct.domain || '—' }}</td>
                <td class="py-2 pr-3 text-gray-500 text-xs truncate max-w-[160px]">
                  <span v-if="acct.email" :title="acct.email">{{ acct.email }}</span>
                  <span v-else class="text-gray-300">—</span>
                  <span v-if="acct.existing_client_id" class="ml-1 text-xs text-green-600 font-medium">(existing)</span>
                  <span v-else-if="!acct.already_imported && selected[acct.username]"
                    class="ml-1 text-xs text-indigo-500 font-medium">(new)</span>
                </td>
                <td class="py-2 pr-3 text-xs">
                  <span v-if="!acct.plan" class="text-gray-300">—</span>
                  <template v-else>
                    <span class="text-gray-500">{{ acct.plan }}</span>
                    <span class="text-gray-300 mx-1">→</span>
                    <span :class="planForAccount(acct).creating ? 'text-amber-600' :
                                   planForAccount(acct).unmapped ? 'text-red-400' : 'text-green-600'">
                      {{ planForAccount(acct).label }}
                    </span>
                  </template>
                </td>
                <td class="py-2 text-xs">
                  <span v-if="acct.already_imported"
                    class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">
                    Already imported
                  </span>
                  <span v-else-if="acct.suspended"
                    class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">
                    Suspended
                  </span>
                  <span v-else
                    class="inline-flex items-center px-2 py-0.5 rounded-full bg-green-50 text-green-700">
                    Active
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Summary box -->
      <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 text-sm text-indigo-700">
        <strong>Import summary:</strong>
        {{ selectedCount }} account{{ selectedCount !== 1 ? 's' : '' }} will be imported.
        <template v-if="newClientCount > 0">
          {{ newClientCount }} new client {{ newClientCount !== 1 ? 'accounts' : 'account' }} will be created.
        </template>
        <template v-if="packages.filter(p => autoCreate[p.name] && packageMap[p.name] == null).length > 0">
          {{ packages.filter(p => autoCreate[p.name] && packageMap[p.name] == null).length }} placeholder product{{ packages.filter(p => autoCreate[p.name] && packageMap[p.name] == null).length !== 1 ? 's' : '' }} will be created (hidden, $0 — review and price them after import).
        </template>
      </div>

      <div class="flex gap-2">
        <button v-if="packages.length" @click="step = 'mapping'"
          class="px-4 py-2 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50">
          ← Back
        </button>
        <button @click="runImport" :disabled="selectedCount === 0"
          class="px-5 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-40 transition-colors">
          Import {{ selectedCount }} Account{{ selectedCount !== 1 ? 's' : '' }}
        </button>
        <Link :href="route('admin.modules.index')"
          class="px-4 py-2 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50">
          Cancel
        </Link>
      </div>
    </div>

    <!-- ── Step: Importing ──────────────────────────────────────────────────── -->
    <div v-else-if="step === 'importing'" class="bg-white rounded-xl border border-gray-200 p-10 text-center">
      <div class="animate-spin inline-block w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full mb-4"></div>
      <p class="text-sm text-gray-500">Importing accounts… please wait.</p>
    </div>

    <!-- ── Step: Done ───────────────────────────────────────────────────────── -->
    <div v-else-if="step === 'done'" class="bg-white rounded-xl border border-green-200 p-8">
      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-600 text-lg">✓</div>
        <div>
          <p class="font-semibold text-gray-900">Import complete</p>
          <p class="text-sm text-gray-500">Successfully imported from <strong>{{ module.name }}</strong></p>
        </div>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-gray-50 rounded-lg p-3 text-center">
          <p class="text-2xl font-bold text-indigo-600">{{ results.imported }}</p>
          <p class="text-xs text-gray-500 mt-0.5">Services imported</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3 text-center">
          <p class="text-2xl font-bold text-gray-400">{{ results.skipped }}</p>
          <p class="text-xs text-gray-500 mt-0.5">Already existed</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3 text-center">
          <p class="text-2xl font-bold text-blue-600">{{ results.new_clients }}</p>
          <p class="text-xs text-gray-500 mt-0.5">New clients</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3 text-center">
          <p class="text-2xl font-bold text-amber-500">{{ results.new_products }}</p>
          <p class="text-xs text-gray-500 mt-0.5">Products created</p>
        </div>
      </div>

      <div v-if="results.new_products > 0"
        class="mb-4 text-sm text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-4 py-3">
        <strong>{{ results.new_products }} placeholder product{{ results.new_products !== 1 ? 's were' : ' was' }} created</strong> with a $0 price and hidden from the portal.
        Go to <Link :href="route('admin.products.index')" class="underline">Products</Link> to review and set pricing before making them visible.
      </div>

      <div v-if="results.new_clients > 0"
        class="mb-4 text-sm text-blue-700 bg-blue-50 border border-blue-100 rounded-lg px-4 py-3">
        <strong>{{ results.new_clients }} new client{{ results.new_clients !== 1 ? ' accounts were' : ' account was' }} created.</strong>
        Clients imported without an email address have a placeholder email — go to
        <Link :href="route('admin.clients.index')" class="underline">Clients</Link> to update their contact info.
      </div>

      <div class="flex gap-2">
        <Link :href="route('admin.services.index')"
          class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors">
          View Services
        </Link>
        <Link :href="route('admin.modules.index')"
          class="px-4 py-2 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50">
          Back to Servers
        </Link>
      </div>
    </div>

  </div>
</template>
