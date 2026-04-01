<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  module:   { type: Object, required: true },
  packages: { type: Array,  default: () => [] },
  error:    { type: String, default: null },
})

// ── Per-row state ─────────────────────────────────────────────────────────────
// checked[name] = true if selected for import
const checked   = ref(Object.fromEntries(props.packages.filter(p => !p.product).map(p => [p.name, false])))
// diskMb / bwMb overrides for "create new" packages
const diskMb    = ref(Object.fromEntries(props.packages.map(p => [p.name, p.disk_mb || 1024])))
const bwMb      = ref(Object.fromEntries(props.packages.map(p => [p.name, p.bandwidth_mb || 10240])))

// ── "New package" creation panel ──────────────────────────────────────────────
const showNew    = ref(false)
const newName    = ref('')
const newDisk    = ref(1024)
const newBw      = ref(10240)
const creating   = ref(false)
const createErr  = ref(null)
const createOk   = ref(false)

async function createPackage() {
  creating.value = true
  createErr.value = null
  createOk.value = false
  try {
    await axios.post(route('admin.modules.packages.create', props.module.id), {
      name:         newName.value,
      disk_mb:      newDisk.value,
      bandwidth_mb: newBw.value,
    })
    createOk.value = true
    // Refresh the page to pick up the new package
    setTimeout(() => router.reload(), 1200)
  } catch (e) {
    createErr.value = e.response?.data?.error ?? 'Unknown error.'
  } finally {
    creating.value = false
  }
}

// ── Import ────────────────────────────────────────────────────────────────────
const importing  = ref(false)

function importSelected() {
  const imports = props.packages
    .filter(p => !p.product && checked.value[p.name])
    .map(p => ({
      name:         p.name,
      disk_mb:      diskMb.value[p.name]  ?? p.disk_mb  ?? 0,
      bandwidth_mb: bwMb.value[p.name]    ?? p.bandwidth_mb ?? 0,
      create_on_panel: false,
    }))

  if (!imports.length) return

  importing.value = true
  router.post(route('admin.modules.packages.sync.store', props.module.id), { imports }, {
    onFinish: () => { importing.value = false },
  })
}

const selectedCount = computed(() =>
  props.packages.filter(p => !p.product && checked.value[p.name]).length
)

function toggleAll(val) {
  props.packages.filter(p => !p.product).forEach(p => { checked.value[p.name] = val })
}

function fmt(mb) {
  if (!mb) return '—'
  if (mb >= 1024 * 1024) return (mb / (1024 * 1024)).toFixed(1) + ' TB'
  if (mb >= 1024)        return (mb / 1024).toFixed(1) + ' GB'
  return mb + ' MB'
}
</script>

<template>
  <div class="max-w-4xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.modules.index')" class="text-sm text-gray-500 hover:text-gray-700">← Servers</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">Sync Packages — {{ module.name }}</h1>
    </div>

    <!-- Error fetching packages -->
    <div v-if="error" class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
      Could not fetch packages from server: {{ error }}
    </div>

    <!-- Flash -->
    <div v-if="$page.props.flash?.success" class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3">
      {{ $page.props.flash.success }}
    </div>

    <!-- Create new package on panel ──────────────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="font-semibold text-gray-800 text-sm">Create New Package on Panel</h2>
          <p class="text-xs text-gray-400 mt-0.5">Define a brand-new hosting plan on <strong>{{ module.name }}</strong> and it will appear in the list below.</p>
        </div>
        <button @click="showNew = !showNew" class="text-xs text-indigo-600 border border-indigo-200 px-3 py-1.5 rounded-lg hover:bg-indigo-50">
          {{ showNew ? 'Cancel' : '+ New Package' }}
        </button>
      </div>

      <div v-if="showNew" class="mt-4 grid grid-cols-3 gap-3 items-end">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Package Name</label>
          <input v-model="newName" type="text" placeholder="e.g. Starter" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Disk Quota (MB)</label>
          <input v-model="newDisk" type="number" min="1" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Bandwidth (MB/mo)</label>
          <input v-model="newBw" type="number" min="1" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        </div>

        <div class="col-span-3 flex items-center gap-3">
          <button @click="createPackage" :disabled="creating || !newName.trim()"
            class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg">
            {{ creating ? 'Creating…' : 'Create on Panel' }}
          </button>
          <span v-if="createErr" class="text-xs text-red-600">{{ createErr }}</span>
          <span v-if="createOk" class="text-xs text-green-600">Created! Refreshing…</span>
        </div>
      </div>
    </div>

    <!-- Package list ──────────────────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 bg-gray-50">
        <div class="flex items-center gap-3">
          <input type="checkbox" @change="e => toggleAll(e.target.checked)" class="h-4 w-4 rounded border-gray-300 text-indigo-600" title="Select all importable" />
          <span class="text-sm font-medium text-gray-700">
            {{ packages.length }} package{{ packages.length !== 1 ? 's' : '' }} on panel
          </span>
        </div>
        <button v-if="selectedCount > 0" @click="importSelected" :disabled="importing"
          class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-xs font-medium px-4 py-1.5 rounded-lg">
          {{ importing ? 'Importing…' : `Import ${selectedCount} as Product${selectedCount !== 1 ? 's' : ''}` }}
        </button>
      </div>

      <div v-if="!packages.length && !error" class="px-5 py-10 text-center text-gray-400 text-sm">
        No packages found on this server.
      </div>

      <table v-else class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50 text-xs font-medium text-gray-500">
          <tr>
            <th class="w-8 px-4 py-2"></th>
            <th class="px-4 py-2 text-left">Package Name</th>
            <th class="px-4 py-2 text-right">Disk</th>
            <th class="px-4 py-2 text-right">Bandwidth</th>
            <th class="px-4 py-2 text-left">Strata Product</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="pkg in packages" :key="pkg.name"
            :class="pkg.product ? 'opacity-60' : (checked[pkg.name] ? 'bg-indigo-50' : 'hover:bg-gray-50')">

            <!-- Checkbox (disabled for already-imported) -->
            <td class="px-4 py-2.5 text-center">
              <input v-if="!pkg.product" type="checkbox" v-model="checked[pkg.name]"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
              <span v-else class="text-green-500 text-base" title="Already imported">✓</span>
            </td>

            <td class="px-4 py-2.5 font-medium text-gray-800">{{ pkg.name }}</td>

            <!-- Disk — editable when selected for import -->
            <td class="px-4 py-2.5 text-right text-gray-500">
              <template v-if="!pkg.product && checked[pkg.name]">
                <input v-model.number="diskMb[pkg.name]" type="number" min="0"
                  class="w-24 border border-gray-200 rounded px-2 py-0.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                  title="Disk MB" />
                <span class="text-xs text-gray-400 ml-1">MB</span>
              </template>
              <template v-else>{{ fmt(pkg.disk_mb) }}</template>
            </td>

            <!-- Bandwidth — editable when selected -->
            <td class="px-4 py-2.5 text-right text-gray-500">
              <template v-if="!pkg.product && checked[pkg.name]">
                <input v-model.number="bwMb[pkg.name]" type="number" min="0"
                  class="w-24 border border-gray-200 rounded px-2 py-0.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-indigo-400"
                  title="Bandwidth MB" />
                <span class="text-xs text-gray-400 ml-1">MB</span>
              </template>
              <template v-else>{{ fmt(pkg.bandwidth_mb) }}</template>
            </td>

            <!-- Matching product -->
            <td class="px-4 py-2.5">
              <template v-if="pkg.product">
                <Link :href="route('admin.products.edit', pkg.product.id)"
                  class="text-indigo-600 hover:underline text-xs">
                  {{ pkg.product.name }}
                </Link>
                <span v-if="pkg.product.hidden" class="ml-1 text-xs text-amber-600">(hidden — set a price to publish)</span>
              </template>
              <span v-else class="text-xs text-gray-400">Not imported yet</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="text-xs text-gray-400 mt-3">
      Imported products are created as <strong>hidden</strong> with a $0 price. Edit each product to set pricing and make it visible.
    </p>
  </div>
</template>
