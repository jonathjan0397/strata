<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    prices:    Array,
    canImport: Boolean,
})

// ── Selection ────────────────────────────────────────────────────────────────
const selected = ref([])

const allSelected = computed(() =>
    props.prices.length > 0 && selected.value.length === props.prices.length
)
const someSelected = computed(() =>
    selected.value.length > 0 && selected.value.length < props.prices.length
)

function toggleAll() {
    if (allSelected.value) selected.value = []
    else selected.value = props.prices.map(p => p.id)
}

function toggleOne(id) {
    const idx = selected.value.indexOf(id)
    if (idx === -1) selected.value.push(id)
    else selected.value.splice(idx, 1)
}

// ── Bulk action ───────────────────────────────────────────────────────────────
const bulkAction     = ref('markup')
const bulkMarkupType = ref('percent')
const bulkMarkupVal  = ref('')
const bulkProcessing = ref(false)

function applyBulk() {
    if (!selected.value.length) return
    if (bulkAction.value === 'delete' && !confirm(`Remove ${selected.value.length} TLD(s)?`)) return

    bulkProcessing.value = true
    router.post(route('admin.tld-pricing.bulk-update'), {
        ids:          selected.value,
        action:       bulkAction.value,
        markup_type:  bulkMarkupType.value,
        markup_value: bulkMarkupVal.value,
    }, {
        onSuccess: () => { selected.value = []; bulkMarkupVal.value = '' },
        onFinish:  () => { bulkProcessing.value = false },
    })
}

// ── Add form ──────────────────────────────────────────────────────────────────
const showAddForm = ref(false)

const addForm = useForm({
    tld:           '',
    register_cost: '',
    renew_cost:    '',
    transfer_cost: '',
    markup_type:   'percent',
    markup_value:  0,
    currency:      'USD',
    is_active:     true,
})

function saveAdd() {
    addForm.post(route('admin.tld-pricing.store'), {
        onSuccess: () => { addForm.reset(); showAddForm.value = false },
    })
}

// ── Inline edit ───────────────────────────────────────────────────────────────
const editing = ref(null)
const editForm = useForm({
    register_cost: '',
    renew_cost:    '',
    transfer_cost: '',
    markup_type:   'percent',
    markup_value:  0,
    currency:      'USD',
    is_active:     true,
})

function startEdit(p) {
    editing.value          = p.id
    editForm.register_cost = p.register_cost ?? ''
    editForm.renew_cost    = p.renew_cost    ?? ''
    editForm.transfer_cost = p.transfer_cost ?? ''
    editForm.markup_type   = p.markup_type
    editForm.markup_value  = p.markup_value
    editForm.currency      = p.currency
    editForm.is_active     = p.is_active
}

function saveEdit(id) {
    editForm.patch(route('admin.tld-pricing.update', id), {
        onSuccess: () => { editing.value = null },
    })
}

function cancelEdit() { editing.value = null }

function remove(id) {
    if (confirm('Remove this TLD?')) router.delete(route('admin.tld-pricing.destroy', id))
}

// ── Import ────────────────────────────────────────────────────────────────────
const importing = ref(false)

function importPrices() {
    importing.value = true
    router.post(route('admin.tld-pricing.import'), {}, {
        onFinish: () => { importing.value = false },
    })
}

function fmt(v) {
    if (v == null) return '—'
    return '$' + Number(v).toFixed(2)
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 gap-4">
      <div>
        <h1 class="text-xl font-bold text-gray-900">TLD Pricing</h1>
        <p class="mt-0.5 text-sm text-gray-500">Set per-TLD registration costs and markup. Final prices are shown to customers.</p>
      </div>
      <div class="flex gap-2 shrink-0">
        <button v-if="canImport" @click="importPrices" :disabled="importing"
          class="px-3 py-1.5 text-xs font-medium rounded-lg border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 disabled:opacity-50 transition-colors">
          {{ importing ? 'Importing…' : 'Import from Registrar' }}
        </button>
        <button @click="showAddForm = !showAddForm"
          class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-colors">
          + Add TLD
        </button>
      </div>
    </div>

    <!-- Add form -->
    <div v-if="showAddForm" class="mb-5 bg-white border border-indigo-100 rounded-xl p-5">
      <h2 class="text-sm font-semibold text-gray-800 mb-4">Add TLD</h2>
      <form @submit.prevent="saveAdd" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 items-end">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">TLD</label>
          <input v-model="addForm.tld" placeholder=".com" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
          <p v-if="addForm.errors.tld" class="mt-0.5 text-xs text-red-500">{{ addForm.errors.tld }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Reg. Cost</label>
          <input v-model="addForm.register_cost" type="number" step="0.0001" min="0" placeholder="0.00" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Renew Cost</label>
          <input v-model="addForm.renew_cost" type="number" step="0.0001" min="0" placeholder="0.00" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Transfer Cost</label>
          <input v-model="addForm.transfer_cost" type="number" step="0.0001" min="0" placeholder="0.00" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Markup</label>
          <select v-model="addForm.markup_type" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400">
            <option value="percent">%</option>
            <option value="fixed">$</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Value</label>
          <input v-model="addForm.markup_value" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Currency</label>
          <input v-model="addForm.currency" maxlength="3" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-400" />
        </div>
        <div class="flex gap-2">
          <button type="submit" :disabled="addForm.processing"
            class="flex-1 px-3 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors">
            Save
          </button>
          <button type="button" @click="showAddForm = false"
            class="px-3 py-1.5 rounded-lg border border-gray-200 text-xs text-gray-600 hover:bg-gray-50 transition-colors">
            Cancel
          </button>
        </div>
      </form>
    </div>

    <!-- Bulk action bar -->
    <transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1">
      <div v-if="selected.length" class="mb-3 flex flex-wrap items-center gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3">
        <span class="text-xs font-semibold text-indigo-700 shrink-0">{{ selected.length }} selected</span>

        <div class="flex items-center gap-2 flex-wrap flex-1">
          <select v-model="bulkAction"
            class="rounded-lg border border-indigo-200 bg-white px-2 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-400">
            <option value="markup">Apply Markup</option>
            <option value="activate">Activate</option>
            <option value="deactivate">Deactivate</option>
            <option value="delete">Delete</option>
          </select>

          <template v-if="bulkAction === 'markup'">
            <select v-model="bulkMarkupType"
              class="rounded-lg border border-indigo-200 bg-white px-2 py-1.5 text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-indigo-400">
              <option value="percent">% Percent</option>
              <option value="fixed">$ Fixed</option>
            </select>
            <input v-model="bulkMarkupVal" type="number" step="0.01" min="0"
              :placeholder="bulkMarkupType === 'percent' ? 'e.g. 20' : 'e.g. 5.00'"
              class="w-28 rounded-lg border border-indigo-200 bg-white px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400" />
          </template>

          <button @click="applyBulk" :disabled="bulkProcessing || (bulkAction === 'markup' && bulkMarkupVal === '')"
            :class="[
              'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors disabled:opacity-50',
              bulkAction === 'delete'
                ? 'bg-red-600 hover:bg-red-700 text-white'
                : 'bg-indigo-600 hover:bg-indigo-700 text-white'
            ]">
            {{ bulkProcessing ? 'Applying…' : 'Apply' }}
          </button>
        </div>

        <button @click="selected = []" class="ml-auto text-xs text-indigo-500 hover:text-indigo-700 shrink-0">Clear selection</button>
      </div>
    </transition>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 w-8">
              <input type="checkbox"
                :checked="allSelected"
                :indeterminate="someSelected"
                @change="toggleAll"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
            </th>
            <th class="px-4 py-3 text-left font-medium text-gray-500 text-xs">TLD</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500 text-xs">Reg. Cost</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500 text-xs">Renew Cost</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500 text-xs">Transfer Cost</th>
            <th class="px-4 py-3 text-center font-medium text-gray-500 text-xs">Markup</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500 text-xs">Reg. Price</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500 text-xs">Renew Price</th>
            <th class="px-4 py-3 text-center font-medium text-gray-500 text-xs">Active</th>
            <th class="px-4 py-3 text-center font-medium text-gray-500 text-xs">Synced</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <template v-if="prices.length">
            <tr v-for="p in prices" :key="p.id"
              :class="['hover:bg-gray-50 transition-colors', selected.includes(p.id) ? 'bg-indigo-50 hover:bg-indigo-50' : '']">

              <!-- view row -->
              <template v-if="editing !== p.id">
                <td class="px-4 py-3 w-8">
                  <input type="checkbox" :checked="selected.includes(p.id)" @change="toggleOne(p.id)"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
                </td>
                <td class="px-4 py-3 font-mono font-semibold text-gray-900">{{ p.tld }}</td>
                <td class="px-4 py-3 text-right text-gray-600">{{ fmt(p.register_cost) }}</td>
                <td class="px-4 py-3 text-right text-gray-600">{{ fmt(p.renew_cost) }}</td>
                <td class="px-4 py-3 text-right text-gray-600">{{ fmt(p.transfer_cost) }}</td>
                <td class="px-4 py-3 text-center text-gray-600">
                  <span v-if="p.markup_type === 'percent'">{{ p.markup_value }}%</span>
                  <span v-else>${{ p.markup_value }}</span>
                </td>
                <td class="px-4 py-3 text-right font-semibold text-indigo-700">{{ fmt(p.register_price) }}</td>
                <td class="px-4 py-3 text-right text-gray-600">{{ fmt(p.renew_price) }}</td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-block w-2 h-2 rounded-full" :class="p.is_active ? 'bg-green-500' : 'bg-gray-300'"></span>
                </td>
                <td class="px-4 py-3 text-center text-xs text-gray-400">
                  {{ p.last_synced_at ? new Date(p.last_synced_at).toLocaleDateString() : '—' }}
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                  <button @click="startEdit(p)" class="text-xs text-indigo-600 hover:underline mr-3">Edit</button>
                  <button @click="remove(p.id)" class="text-xs text-red-500 hover:underline">Remove</button>
                </td>
              </template>

              <!-- edit row -->
              <template v-else>
                <td class="px-4 py-3 w-8"></td>
                <td class="px-4 py-3 font-mono font-semibold text-gray-900">{{ p.tld }}</td>
                <td class="px-2 py-2">
                  <input v-model="editForm.register_cost" type="number" step="0.0001" min="0" placeholder="cost"
                    class="w-24 rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                </td>
                <td class="px-2 py-2">
                  <input v-model="editForm.renew_cost" type="number" step="0.0001" min="0" placeholder="cost"
                    class="w-24 rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                </td>
                <td class="px-2 py-2">
                  <input v-model="editForm.transfer_cost" type="number" step="0.0001" min="0" placeholder="cost"
                    class="w-24 rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                </td>
                <td class="px-2 py-2">
                  <div class="flex gap-1 justify-center">
                    <select v-model="editForm.markup_type"
                      class="rounded border border-indigo-300 bg-indigo-50 px-1 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
                      <option value="percent">%</option>
                      <option value="fixed">$</option>
                    </select>
                    <input v-model="editForm.markup_value" type="number" step="0.01" min="0"
                      class="w-16 rounded border border-indigo-300 bg-indigo-50 px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                  </div>
                </td>
                <td class="px-4 py-2 text-right text-xs text-gray-400" colspan="2">Preview after save</td>
                <td class="px-2 py-2 text-center">
                  <input type="checkbox" v-model="editForm.is_active" class="h-4 w-4 rounded border-gray-300 text-indigo-600" />
                </td>
                <td></td>
                <td class="px-2 py-2 text-right whitespace-nowrap">
                  <button @click="saveEdit(p.id)" :disabled="editForm.processing"
                    class="text-xs text-white bg-indigo-600 hover:bg-indigo-700 px-2.5 py-1 rounded mr-1 disabled:opacity-50 transition-colors">Save</button>
                  <button @click="cancelEdit" class="text-xs text-gray-500 hover:underline">Cancel</button>
                </td>
              </template>
            </tr>
          </template>
          <tr v-else>
            <td colspan="11" class="px-4 py-10 text-center text-sm text-gray-400">
              No TLD pricing configured yet. Add a TLD above or import from your registrar.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Selection summary footer -->
    <div v-if="prices.length" class="mt-2 flex items-center justify-between text-xs text-gray-400 px-1">
      <span>{{ prices.length }} TLD{{ prices.length !== 1 ? 's' : '' }} total</span>
      <button v-if="!allSelected" @click="toggleAll" class="hover:text-indigo-600 transition-colors">Select all {{ prices.length }}</button>
      <button v-else @click="selected = []" class="hover:text-indigo-600 transition-colors">Clear selection</button>
    </div>
  </div>
</template>
