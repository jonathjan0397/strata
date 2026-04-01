<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, Link } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const props = defineProps({
  product: { type: Object, default: null },
  modules: { type: Array,  default: () => [] },
})

const form = useForm({
  name:                 props.product?.name                 ?? '',
  category:             props.product?.category             ?? '',
  short_description:    props.product?.short_description    ?? '',
  description:          props.product?.description          ?? '',
  type:                 props.product?.type                 ?? 'shared',
  price:                props.product?.price                ?? '0.00',
  setup_fee:            props.product?.setup_fee            ?? '0.00',
  billing_cycle:        props.product?.billing_cycle        ?? 'monthly',
  stock:                props.product?.stock                ?? '',
  module:               props.product?.module               ?? '',
  module_config:        props.product?.module_config        ?? {},
  autosetup:            props.product?.autosetup            ?? 'manual',
  trial_days:           props.product?.trial_days           ?? '',
  hidden:               props.product?.hidden               ?? false,
  taxable:              props.product?.taxable              ?? true,
  sort_order:           props.product?.sort_order           ?? 0,
  configurable_options: props.product?.configurable_options ?? [],
})

// ── Provisioning ──────────────────────────────────────────────────────────────
const PANEL_TYPES = [
  { value: 'cpanel',      label: 'cPanel (WHM)' },
  { value: 'directadmin', label: 'DirectAdmin' },
  { value: 'plesk',       label: 'Plesk' },
  { value: 'hestia',      label: 'HestiaCP' },
  { value: 'cwp',         label: 'CWP (Control Web Panel)' },
]

// Servers of the currently selected panel type
const serversOfType = computed(() =>
  props.modules.filter(m => m.type === form.module)
)

// Which specific server is pinned (null = auto-select)
const pinnedModuleId = ref(props.product?.module_config?.module_id ?? null)

// Packages loaded from the pinned server (or first server of the type)
const availablePackages = ref([])
const packagesLoading   = ref(false)
const packagesError     = ref(null)

async function loadPackages(moduleId) {
  if (!moduleId) {
    availablePackages.value = []
    return
  }
  packagesLoading.value = true
  packagesError.value   = null
  try {
    const res = await axios.get(route('admin.modules.packages', moduleId))
    availablePackages.value = res.data.packages ?? []
    if (res.data.error) packagesError.value = res.data.error
  } catch {
    availablePackages.value = []
    packagesError.value = 'Could not load packages from server.'
  } finally {
    packagesLoading.value = false
  }
}

// When panel type changes — reset server pin and reload packages from first available server
function onPanelTypeChange() {
  pinnedModuleId.value = null
  form.module_config = { ...form.module_config, module_id: null }
  const first = serversOfType.value[0]
  loadPackages(first?.id ?? null)
}

// When a specific server is selected
function onServerChange() {
  form.module_config = { ...form.module_config, module_id: pinnedModuleId.value }
  const targetId = pinnedModuleId.value ?? serversOfType.value[0]?.id ?? null
  loadPackages(targetId)
}

// Seed packages on page load if editing a product with a module already set
if (form.module) {
  const targetId = pinnedModuleId.value ?? serversOfType.value[0]?.id ?? null
  loadPackages(targetId)
}

// ── Configurable options builder ──────────────────────────────────────────────
function addOptionGroup() {
  form.configurable_options.push({ name: '', choices: [''] })
}

function removeOptionGroup(i) {
  form.configurable_options.splice(i, 1)
}

function addChoice(group) {
  group.choices.push('')
}

function removeChoice(group, ci) {
  if (group.choices.length > 1) group.choices.splice(ci, 1)
}

// ── Submit ────────────────────────────────────────────────────────────────────
function submit() {
  // Clean empty option groups / empty choices
  form.configurable_options = form.configurable_options
    .filter(g => g.name.trim())
    .map(g => ({ ...g, choices: g.choices.filter(c => c.trim()) }))
    .filter(g => g.choices.length)

  if (props.product) {
    form.patch(route('admin.products.update', props.product.id))
  } else {
    form.post(route('admin.products.store'))
  }
}

const BILLING_CYCLES = ['monthly','quarterly','semi_annual','annual','biennial','triennial','one_time']
const PRODUCT_TYPES  = ['shared','reseller','vps','dedicated','domain','ssl','other']
const CATEGORIES     = ['Shared Hosting','VPS Hosting','Dedicated Servers','Reseller Hosting','Domains','SSL Certificates','Email Hosting','Other']
</script>

<template>
  <div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.products.index')" class="text-sm text-slate-400 hover:text-slate-600">← Products</Link>
      <span class="text-slate-200">/</span>
      <h1 class="text-xl font-bold text-slate-800">{{ product ? 'Edit Product' : 'New Product' }}</h1>
    </div>

    <form @submit.prevent="submit" class="space-y-5">

      <!-- Basic Info -->
      <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-6 shadow-sm space-y-4">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wider">Basic Information</h2>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Product Name <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text" required
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <p v-if="form.errors.name" class="text-red-500 text-xs mt-0.5">{{ form.errors.name }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
            <input v-model="form.category" type="text" list="category-list" placeholder="e.g. Shared Hosting"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <datalist id="category-list">
              <option v-for="c in CATEGORIES" :key="c" :value="c" />
            </datalist>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
            <select v-model="form.type"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option v-for="t in PRODUCT_TYPES" :key="t" :value="t" class="capitalize">{{ t.replace(/_/g, ' ') }}</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Short Description</label>
          <input v-model="form.short_description" type="text" maxlength="255" placeholder="One-line summary for product listings…"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Full Description</label>
          <textarea v-model="form.description" rows="3" placeholder="Detailed product description…"
            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y" />
        </div>
      </div>

      <!-- Pricing -->
      <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-6 shadow-sm space-y-4">
        <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wider">Pricing & Billing</h2>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Price ($) <span class="text-red-500">*</span></label>
            <input v-model="form.price" type="number" step="0.01" min="0" required
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Setup Fee ($)</label>
            <input v-model="form.setup_fee" type="number" step="0.01" min="0"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Billing Cycle</label>
            <select v-model="form.billing_cycle"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option v-for="c in BILLING_CYCLES" :key="c" :value="c">{{ c.replace(/_/g, ' ') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Stock (blank = unlimited)</label>
            <input v-model="form.stock" type="number" min="0" placeholder="∞"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <div class="flex flex-wrap gap-6 pt-1">
          <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
            <input v-model="form.taxable" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
            Taxable
          </label>
          <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
            <input v-model="form.hidden" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
            Hidden from order form
          </label>
        </div>
      </div>

      <!-- Configurable Options -->
      <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wider">Configurable Options</h2>
            <p class="text-xs text-slate-400 mt-0.5">Spec groups displayed on the product listing (e.g. Disk, RAM, Bandwidth)</p>
          </div>
          <button type="button" @click="addOptionGroup"
            class="text-xs text-blue-600 border border-blue-200 px-2.5 py-1 rounded-lg hover:bg-blue-50 transition-colors">
            + Add Option
          </button>
        </div>

        <div v-if="!form.configurable_options.length" class="text-sm text-slate-400 text-center py-4 border border-dashed border-slate-200 rounded-lg">
          No configurable options. Click "+ Add Option" to define spec groups.
        </div>

        <div v-for="(group, gi) in form.configurable_options" :key="gi"
          class="border border-slate-200 rounded-lg p-4 space-y-3">
          <div class="flex items-center gap-2">
            <input v-model="group.name" type="text" placeholder="Option name (e.g. Disk Space)"
              class="flex-1 border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <button type="button" @click="removeOptionGroup(gi)"
              class="text-red-400 hover:text-red-600 text-sm px-2">✕</button>
          </div>

          <div class="space-y-1.5 pl-3">
            <div v-for="(choice, ci) in group.choices" :key="ci" class="flex gap-2 items-center">
              <input v-model="group.choices[ci]" type="text" :placeholder="`Choice ${ci + 1} (e.g. 10 GB)`"
                class="flex-1 border border-slate-200 rounded-lg px-2.5 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <button type="button" @click="removeChoice(group, ci)" :disabled="group.choices.length === 1"
                class="text-slate-300 hover:text-red-400 disabled:opacity-30 transition-colors text-xs">✕</button>
            </div>
            <button type="button" @click="addChoice(group)"
              class="text-xs text-slate-400 hover:text-blue-600 transition-colors">+ add choice</button>
          </div>
        </div>
      </div>

      <!-- Module / Provisioning -->
      <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-6 shadow-sm space-y-4">
        <div>
          <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wider">Provisioning</h2>
          <p class="text-xs text-slate-400 mt-0.5">Controls how hosting accounts are automatically created when a customer orders this product.</p>
        </div>

        <!-- Row 1: Panel type + Auto-setup trigger -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Panel Type</label>
            <select v-model="form.module" @change="onPanelTypeChange"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">None — manual provisioning only</option>
              <option v-for="pt in PANEL_TYPES" :key="pt.value" :value="pt.value">{{ pt.label }}</option>
              <option value="domain">Domain Registrar</option>
              <option value="ssl">SSL</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Provision Trigger</label>
            <select v-model="form.autosetup"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="on_payment">On Payment — create account once invoice is paid</option>
              <option value="on_order">On Order — create account immediately at checkout</option>
              <option value="manual">Manual — admin must approve each order</option>
              <option value="never">Never — no auto-provisioning</option>
            </select>
            <p class="text-xs text-slate-400 mt-1">
              <template v-if="form.autosetup === 'on_payment'">Account is created the moment payment clears — recommended for paid plans.</template>
              <template v-else-if="form.autosetup === 'on_order'">Account is created at checkout, before payment. Use for free/trial products.</template>
              <template v-else-if="form.autosetup === 'manual'">Order lands in the queue; an admin clicks Provision to activate it.</template>
              <template v-else>Orders are created but never auto-provisioned.</template>
            </p>
          </div>
        </div>

        <!-- Server + Package rows (only shown when a panel type is selected) -->
        <template v-if="form.module && !['domain','ssl'].includes(form.module)">

          <!-- Server assignment -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Server Assignment</label>
            <select v-model="pinnedModuleId" @change="onServerChange"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option :value="null">Auto-select — use any available {{ form.module }} server at order time</option>
              <template v-if="serversOfType.length">
                <option v-for="m in serversOfType" :key="m.id" :value="m.id">
                  {{ m.name }} — {{ m.hostname }}
                  ({{ m.current_accounts }}{{ m.max_accounts ? ' / ' + m.max_accounts : '' }} accounts)
                </option>
              </template>
            </select>
            <p v-if="!serversOfType.length"
              class="text-xs text-amber-600 mt-1">
              No active {{ form.module }} servers configured.
              <Link :href="route('admin.modules.create')" class="underline">Add one in Servers &amp; Modules</Link> before orders can be auto-provisioned.
            </p>
            <p v-else class="text-xs text-slate-400 mt-1">
              Pin to a specific server to guarantee which machine handles new orders, or leave on auto-select for capacity-based load balancing.
            </p>
          </div>

          <!-- Package / Plan -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Package / Plan
              <span class="font-normal text-slate-400 text-xs ml-1">— assigned on the panel when the account is created</span>
            </label>

            <!-- Loading -->
            <div v-if="packagesLoading"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-400 bg-slate-50">
              Loading packages…
            </div>

            <!-- Dropdown from live packages -->
            <select v-else-if="availablePackages.length"
              v-model="form.module_config.plan"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">— No plan (server default) —</option>
              <option v-for="p in availablePackages" :key="p.name" :value="p.name">{{ p.name }}</option>
            </select>

            <!-- Text fallback if server unreachable or no packages -->
            <template v-else>
              <input v-model="form.module_config.plan" type="text"
                placeholder="e.g. Basic, Pro, Enterprise (exact name from the control panel)"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <p v-if="packagesError" class="text-xs text-amber-600 mt-1">
                {{ packagesError }} — enter the plan name manually.
              </p>
              <p v-else-if="serversOfType.length" class="text-xs text-slate-400 mt-1">
                Select a specific server above to load its packages automatically.
              </p>
            </template>
          </div>

          <!-- Package resource limits (used for auto-create) -->
          <div v-if="form.module_config.plan" class="grid grid-cols-2 gap-4 pt-3 border-t border-slate-100">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Disk Quota (MB)
                <span class="font-normal text-slate-400 text-xs ml-1">blank = server default</span>
              </label>
              <input v-model.number="form.module_config.disk_mb" type="number" min="0" placeholder="e.g. 10240"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">
                Bandwidth (MB/mo)
                <span class="font-normal text-slate-400 text-xs ml-1">blank = server default</span>
              </label>
              <input v-model.number="form.module_config.bandwidth_mb" type="number" min="0" placeholder="e.g. 51200"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div class="col-span-2">
              <label class="flex items-start gap-2 cursor-pointer">
                <input v-model="form.module_config.auto_create_package" type="checkbox"
                  class="h-4 w-4 mt-0.5 rounded border-slate-300 text-blue-600" />
                <span class="text-sm text-slate-700">
                  Auto-create package on panel if it doesn't exist
                  <span class="block text-xs text-slate-400 font-normal">When the first account is provisioned, Strata will create the package on the panel using the disk/bandwidth values above if it isn't already there.</span>
                </span>
              </label>
            </div>
          </div>
        </template>

        <!-- Trial + Sort -->
        <div class="grid grid-cols-2 gap-4 pt-1 border-t border-slate-100">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Trial Period (days)
              <span class="text-slate-400 font-normal text-xs">(blank = no trial)</span>
            </label>
            <input v-model="form.trial_days" type="number" min="1" max="365" placeholder="e.g. 14"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <p v-if="form.errors.trial_days" class="text-red-500 text-xs mt-0.5">{{ form.errors.trial_days }}</p>
            <p class="text-xs text-slate-400 mt-1">Account activates immediately; invoice generated when trial ends.</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Sort Order</label>
            <input v-model="form.sort_order" type="number"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex justify-end gap-3">
        <Link :href="route('admin.products.index')" class="text-sm text-slate-500 px-4 py-2 hover:text-slate-700">Cancel</Link>
        <button type="submit" :disabled="form.processing"
          class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium px-6 py-2 rounded-lg shadow-sm transition-colors">
          {{ form.processing ? 'Saving…' : (product ? 'Save Changes' : 'Create Product') }}
        </button>
      </div>
    </form>
  </div>
</template>
