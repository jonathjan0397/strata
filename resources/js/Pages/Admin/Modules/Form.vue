<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ module: { type: Object, default: null } })

const PANEL_TYPES = [
  { value: 'cpanel',      label: 'cPanel (WHM)',    port: 2087, note: 'WHM JSON API' },
  { value: 'plesk',       label: 'Plesk',           port: 8443, note: 'REST API v2' },
  { value: 'directadmin', label: 'DirectAdmin',     port: 2222, note: 'HTTP API' },
  { value: 'hestia',      label: 'HestiaCP',        port: 8083, note: 'REST API' },
  { value: 'cwp',         label: 'CWP (Control Web Panel)', port: 2304, note: 'REST API' },
  { value: 'strata_panel', label: 'Strata Hosting Panel', port: 443, note: 'REST API v1 / Sanctum token' },
  { value: 'vestacp',     label: 'VestaCP',         port: 8083, note: 'API (manual provisioning)' },
  { value: 'cyberpanel',  label: 'CyberPanel',      port: 8090, note: 'API (manual provisioning)' },
  { value: 'generic',     label: 'Generic / Other', port: 2087, note: 'Manual provisioning only' },
]

const DEFAULT_PORTS = Object.fromEntries(PANEL_TYPES.map(t => [t.value, t.port]))

const form = useForm({
  name:           props.module?.name           ?? '',
  type:           props.module?.type           ?? 'cpanel',
  hostname:       props.module?.hostname       ?? '',
  port:           props.module?.port           ?? 2087,
  local_hostname: props.module?.local_hostname ?? null,
  local_port:     props.module?.local_port     ?? null,
  username:       props.module?.username       ?? '',
  api_token:      '',
  ssl:            props.module?.ssl            ?? true,
  active:         props.module?.active         ?? true,
  max_accounts:   props.module?.max_accounts   ?? '',
})

const showLocal = ref(!!(props.module?.local_hostname))

function onTypeChange() {
  // Auto-fill the default port when type changes (only if not editing an existing server)
  if (!props.module) {
    form.port = DEFAULT_PORTS[form.type] ?? 2087
  }
}

function submit() {
  if (props.module) {
    form.patch(route('admin.modules.update', props.module.id))
  } else {
    form.post(route('admin.modules.store'))
  }
}
</script>

<template>
  <div class="max-w-lg">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.modules.index')" class="text-sm text-gray-500 hover:text-gray-700">← Servers</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ module ? 'Edit Server' : 'Add Server' }}</h1>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <form @submit.prevent="submit" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input v-model="form.name" type="text" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
            <select v-model="form.type" @change="onTypeChange" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option v-for="t in PANEL_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Hostname</label>
            <input v-model="form.hostname" type="text" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="server.example.com" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
            <input v-model="form.port" type="number" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- Local / internal access toggle -->
          <div class="col-span-2">
            <button type="button" @click="showLocal = !showLocal"
              class="flex items-center gap-1.5 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
              <svg :class="showLocal ? 'rotate-90' : ''" class="h-3.5 w-3.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
              </svg>
              {{ showLocal ? 'Hide' : 'Set' }} local / internal access address
            </button>
            <p class="mt-0.5 text-xs text-gray-400">
              Use when Strata and this panel are on the same server. API calls will use the local address; SSL certificate verification is skipped automatically for local connections.
            </p>
          </div>

          <template v-if="showLocal">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Local Hostname</label>
              <div class="flex gap-2">
                <input v-model="form.local_hostname" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="127.0.0.1" />
                <button type="button" @click="form.local_hostname = '127.0.0.1'"
                  class="shrink-0 text-xs text-gray-500 border border-gray-300 rounded-lg px-2.5 py-2 hover:bg-gray-50 transition-colors">
                  localhost
                </button>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Local Port <span class="font-normal text-gray-400">(blank = same as above)</span></label>
              <input v-model="form.local_port" type="number" min="1" max="65535" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" :placeholder="form.port" />
            </div>
          </template>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
            <input v-model="form.username" type="text" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">API Token {{ module ? '(leave blank to keep)' : '' }}</label>
            <input v-model="form.api_token" type="password" :required="!module" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            <p v-if="form.type === 'strata_panel'" class="text-xs text-gray-400 mt-1">
              Use a Strata Hosting Panel admin API token with `accounts:*` and `catalog:read` abilities.
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Max Accounts (blank = unlimited)</label>
            <input v-model="form.max_accounts" type="number" min="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
          </div>
          <div class="flex items-end gap-6 pb-1">
            <label class="flex items-center gap-2 text-sm"><input v-model="form.ssl" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" /> SSL</label>
            <label class="flex items-center gap-2 text-sm"><input v-model="form.active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600" /> Active</label>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <Link :href="route('admin.modules.index')" class="text-sm text-gray-500 px-4 py-2">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
            {{ module ? 'Save Changes' : 'Add Server' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
