<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, useForm, usePage, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  client: Object,
  stats:  Object,
  groups: Array,
  staff:  Array,
})

const page        = usePage()
const flash       = computed(() => page.props.flash)
const activeTab   = ref('overview')

// ── Profile editing ───────────────────────────────────────────────────────────
const editingProfile = ref(false)
const profileForm = useForm({
  name:          props.client.name          ?? '',
  email:         props.client.email         ?? '',
  company:       props.client.company       ?? '',
  phone:         props.client.phone         ?? '',
  website:       props.client.website       ?? '',
  lead_source:   props.client.lead_source   ?? '',
  client_status: props.client.client_status ?? 'active',
  country:       props.client.country       ?? '',
  state:         props.client.state         ?? '',
  tax_exempt:    props.client.tax_exempt     ?? false,
})

function saveProfile() {
  profileForm.patch(route('admin.clients.update', props.client.id), {
    onSuccess: () => { editingProfile.value = false },
  })
}

// ── Credit top-up ─────────────────────────────────────────────────────────────
const showCreditForm = ref(false)
const creditForm = useForm({ amount: '', description: 'Account credit' })

function submitCredit() {
  creditForm.post(route('admin.clients.credit', props.client.id), {
    onSuccess: () => { showCreditForm.value = false; creditForm.reset() },
  })
}

// ── Email ─────────────────────────────────────────────────────────────────────
const showEmailModal = ref(false)
const emailForm = useForm({ subject: '', body: '' })

function sendEmail() {
  emailForm.post(route('admin.clients.email', props.client.id), {
    onSuccess: () => { showEmailModal.value = false; emailForm.reset() },
  })
}

// ── Notes ─────────────────────────────────────────────────────────────────────
const noteForm = useForm({ body: '', type: 'note' })

function submitNote() {
  noteForm.post(route('admin.clients.notes.store', props.client.id), {
    onSuccess: () => noteForm.reset('body'),
  })
}

function deleteNote(noteId) {
  if (confirm('Delete this note?')) {
    router.delete(route('admin.clients.notes.destroy', [props.client.id, noteId]))
  }
}

// ── Tasks ─────────────────────────────────────────────────────────────────────
const taskForm = useForm({
  title:       '',
  description: '',
  priority:    'normal',
  due_at:      '',
  assigned_to: '',
})

function submitTask() {
  taskForm.post(route('admin.clients.tasks.store', props.client.id), {
    onSuccess: () => taskForm.reset(),
  })
}

function toggleTask(taskId) {
  router.patch(route('admin.clients.tasks.complete', [props.client.id, taskId]))
}

function deleteTask(taskId) {
  if (confirm('Delete this task?')) {
    router.delete(route('admin.clients.tasks.destroy', [props.client.id, taskId]))
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const statusColors = {
  prospect: 'bg-blue-100 text-blue-700 ring-1 ring-blue-200',
  active:   'bg-green-100 text-green-700 ring-1 ring-green-200',
  inactive: 'bg-gray-100 text-gray-600 ring-1 ring-gray-200',
  at_risk:  'bg-amber-100 text-amber-700 ring-1 ring-amber-200',
  churned:  'bg-red-100 text-red-600 ring-1 ring-red-200',
}

const statusLabels = { prospect: 'Prospect', active: 'Active', inactive: 'Inactive', at_risk: 'At Risk', churned: 'Churned' }
const priorityColors = { low: 'bg-slate-100 text-slate-500', normal: 'bg-blue-100 text-blue-600', high: 'bg-red-100 text-red-600' }
const noteIcons = { note: '📝', call: '📞', email: '✉️', meeting: '👥' }
const noteTypeLabels = { note: 'Note', call: 'Call Log', email: 'Email', meeting: 'Meeting' }

const pendingTasks   = computed(() => props.client.tasks?.filter(t => !t.completed_at) ?? [])
const completedTasks = computed(() => props.client.tasks?.filter(t =>  t.completed_at) ?? [])

function fmt(date) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString()
}
function fmtMoney(v) {
  return '$' + parseFloat(v ?? 0).toFixed(2)
}
</script>

<template>
  <div class="max-w-6xl">

    <!-- Flash -->
    <div v-if="flash?.success" class="mb-4 rounded-xl bg-green-50/80 border border-green-200/60 px-4 py-3 text-sm text-green-800">
      {{ flash.success }}
    </div>
    <div v-if="flash?.error" class="mb-4 rounded-xl bg-red-50/80 border border-red-200/60 px-4 py-3 text-sm text-red-800">
      {{ flash.error }}
    </div>

    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
      <div class="flex items-center gap-4">
        <Link :href="route('admin.clients.index')" class="text-sm text-slate-400 hover:text-slate-600">← Clients</Link>
        <div class="h-12 w-12 rounded-full bg-blue-600 flex items-center justify-center text-white text-lg font-bold uppercase shadow-md shadow-blue-400/30">
          {{ client.name?.charAt(0) }}
        </div>
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold text-slate-800">{{ client.name }}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
              :class="statusColors[client.client_status] ?? 'bg-gray-100 text-gray-600'">
              {{ statusLabels[client.client_status] ?? client.client_status }}
            </span>
          </div>
          <p class="text-sm text-slate-500 mt-0.5">
            <span v-if="client.company">{{ client.company }} · </span>{{ client.email }}
          </p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <button @click="editingProfile = !editingProfile"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
          <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
          Edit Profile
        </button>
        <button @click="showEmailModal = !showEmailModal"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
          ✉ Email
        </button>
        <Link :href="route('admin.invoices.create') + '?user_id=' + client.id"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-blue-600 border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors">
          + Invoice
        </Link>
        <button @click="router.post(route('admin.clients.suspend', client.id))"
          class="px-3 py-1.5 text-sm font-medium text-red-500 border border-red-200 rounded-lg hover:bg-red-50 transition-colors">
          Suspend All
        </button>
      </div>
    </div>

    <!-- ── Stats ─────────────────────────────────────────────────────────── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-4 shadow-sm">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Services</p>
        <p class="text-2xl font-bold text-slate-800 mt-1">{{ stats.active_services }}</p>
      </div>
      <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-4 shadow-sm">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Unpaid</p>
        <p class="text-2xl font-bold text-amber-600 mt-1">{{ fmtMoney(stats.unpaid_total) }}</p>
      </div>
      <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-4 shadow-sm">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Paid</p>
        <p class="text-2xl font-bold text-green-600 mt-1">{{ fmtMoney(stats.total_paid) }}</p>
      </div>
      <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-4 shadow-sm">
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Open Tickets</p>
        <p class="text-2xl font-bold text-slate-800 mt-1">{{ stats.open_tickets }}</p>
      </div>
    </div>

    <!-- ── Body ──────────────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Left: Profile sidebar -->
      <div class="space-y-4">

        <!-- Profile form -->
        <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-5 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-600 uppercase tracking-wider mb-3">Contact Info</h2>

          <template v-if="!editingProfile">
            <dl class="space-y-2 text-sm">
              <div><dt class="text-xs text-slate-400">Email</dt><dd class="text-slate-700">{{ client.email }}</dd></div>
              <div v-if="client.phone"><dt class="text-xs text-slate-400">Phone</dt><dd class="text-slate-700">{{ client.phone }}</dd></div>
              <div v-if="client.company"><dt class="text-xs text-slate-400">Company</dt><dd class="text-slate-700">{{ client.company }}</dd></div>
              <div v-if="client.website"><dt class="text-xs text-slate-400">Website</dt><dd><a :href="client.website" target="_blank" class="text-blue-600 hover:underline text-xs">{{ client.website }}</a></dd></div>
              <div v-if="client.country"><dt class="text-xs text-slate-400">Location</dt><dd class="text-slate-700">{{ client.state ? client.state + ', ' : '' }}{{ client.country }}</dd></div>
              <div v-if="client.lead_source"><dt class="text-xs text-slate-400">Lead Source</dt><dd class="text-slate-700 capitalize">{{ client.lead_source }}</dd></div>
              <div><dt class="text-xs text-slate-400">Tax Exempt</dt><dd class="text-slate-700">{{ client.tax_exempt ? 'Yes' : 'No' }}</dd></div>
              <div><dt class="text-xs text-slate-400">Joined</dt><dd class="text-slate-700">{{ fmt(client.created_at) }}</dd></div>
            </dl>
          </template>

          <template v-else>
            <form @submit.prevent="saveProfile" class="space-y-2">
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Full Name</label>
                <input v-model="profileForm.name" type="text" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Email</label>
                <input v-model="profileForm.email" type="email" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Company</label>
                <input v-model="profileForm.company" type="text" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Phone</label>
                <input v-model="profileForm.phone" type="text" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Website</label>
                <input v-model="profileForm.website" type="url" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Lead Source</label>
                <select v-model="profileForm.lead_source" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="">— none —</option>
                  <option value="google">Google</option>
                  <option value="referral">Referral</option>
                  <option value="direct">Direct</option>
                  <option value="social">Social Media</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-500 mb-0.5">Status</label>
                <select v-model="profileForm.client_status" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="prospect">Prospect</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="at_risk">At Risk</option>
                  <option value="churned">Churned</option>
                </select>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="block text-xs font-medium text-slate-500 mb-0.5">Country</label>
                  <input v-model="profileForm.country" maxlength="2" placeholder="US" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-slate-500 mb-0.5">State</label>
                  <input v-model="profileForm.state" maxlength="10" placeholder="CA" class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
              </div>
              <label class="flex items-center gap-2 text-sm text-slate-600 pt-1">
                <input v-model="profileForm.tax_exempt" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                Tax Exempt
              </label>
              <div class="flex gap-2 pt-1">
                <button type="submit" :disabled="profileForm.processing"
                  class="flex-1 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-xs font-medium rounded-lg py-1.5 transition-colors">
                  {{ profileForm.processing ? 'Saving…' : 'Save' }}
                </button>
                <button type="button" @click="editingProfile = false"
                  class="flex-1 text-slate-500 border border-slate-200 text-xs rounded-lg py-1.5 hover:bg-slate-50">
                  Cancel
                </button>
              </div>
            </form>
          </template>
        </div>

        <!-- Credit & Group -->
        <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-5 shadow-sm space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-sm text-slate-500">Credit Balance</span>
            <div class="flex items-center gap-2">
              <strong class="text-slate-800">${{ parseFloat(client.credit_balance ?? 0).toFixed(2) }}</strong>
              <button @click="showCreditForm = !showCreditForm" class="text-xs text-blue-600 hover:underline">+ Add</button>
            </div>
          </div>

          <div v-if="showCreditForm" class="space-y-2 pt-2 border-t border-slate-100">
            <input v-model="creditForm.amount" type="number" step="0.01" min="0.01" placeholder="Amount ($)"
              class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <input v-model="creditForm.description" type="text" placeholder="Reason"
              class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <button @click="submitCredit" :disabled="creditForm.processing || !creditForm.amount"
              class="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-xs font-medium rounded-lg py-1.5 transition-colors">
              {{ creditForm.processing ? 'Adding…' : 'Add Credit' }}
            </button>
          </div>

          <div class="pt-2 border-t border-slate-100">
            <label class="block text-xs font-medium text-slate-400 mb-1">Client Group</label>
            <select
              :value="client.client_group_id"
              @change="router.post(route('admin.client-groups.assign', client.id), { client_group_id: $event.target.value || null })"
              class="w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">— No group —</option>
              <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
          </div>
        </div>

        <!-- Email compose -->
        <div v-if="showEmailModal" class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-200/60 p-5 shadow-sm space-y-3">
          <h3 class="text-sm font-semibold text-slate-700">Send Email to {{ client.name }}</h3>
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Subject</label>
            <input v-model="emailForm.subject" type="text" placeholder="Subject…"
              class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Message</label>
            <textarea v-model="emailForm.body" rows="4" placeholder="Type your message…"
              class="w-full border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
            <p v-if="emailForm.errors.email" class="mt-1 text-xs text-red-600">{{ emailForm.errors.email }}</p>
          </div>
          <div class="flex gap-2">
            <button @click="sendEmail" :disabled="emailForm.processing"
              class="flex-1 bg-blue-600 hover:bg-blue-500 disabled:opacity-60 text-white text-xs font-medium rounded-lg py-1.5 transition-colors">
              {{ emailForm.processing ? 'Sending…' : 'Send' }}
            </button>
            <button @click="showEmailModal = false" class="text-slate-500 border border-slate-200 text-xs rounded-lg px-3 py-1.5 hover:bg-slate-50">Cancel</button>
          </div>
        </div>
      </div>

      <!-- Right: Tabs -->
      <div class="lg:col-span-2">

        <!-- Tab bar -->
        <div class="flex gap-1 mb-4 bg-white/50 backdrop-blur-sm border border-blue-100/60 rounded-xl p-1 shadow-sm">
          <button v-for="tab in ['overview', 'services', 'invoices', 'support', 'notes', 'tasks']" :key="tab"
            @click="activeTab = tab"
            class="flex-1 py-1.5 px-2 text-xs font-semibold rounded-lg capitalize transition-colors"
            :class="activeTab === tab
              ? 'bg-blue-600 text-white shadow-sm'
              : 'text-slate-500 hover:text-slate-700 hover:bg-slate-100/60'">
            {{ tab }}
            <span v-if="tab === 'tasks' && pendingTasks.length"
              class="ml-1 inline-flex h-4 w-4 items-center justify-center rounded-full bg-amber-400 text-white text-xs leading-none">
              {{ pendingTasks.length }}
            </span>
          </button>
        </div>

        <!-- ── Overview tab ─────────────────────────────────────────────── -->
        <div v-show="activeTab === 'overview'" class="space-y-4">
          <!-- Recent Services -->
          <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
              <h3 class="text-sm font-semibold text-slate-700">Recent Services</h3>
              <button @click="activeTab = 'services'" class="text-xs text-blue-600 hover:underline">View all →</button>
            </div>
            <ul class="divide-y divide-slate-50 text-sm">
              <li v-for="s in client.services?.slice(0, 4)" :key="s.id" class="py-2 flex justify-between items-center">
                <span class="text-slate-700">{{ s.domain ?? s.product?.name }}</span>
                <StatusBadge :status="s.status" />
              </li>
              <li v-if="!client.services?.length" class="py-3 text-slate-400 text-center">No services.</li>
            </ul>
          </div>
          <!-- Recent Invoices -->
          <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
              <h3 class="text-sm font-semibold text-slate-700">Recent Invoices</h3>
              <button @click="activeTab = 'invoices'" class="text-xs text-blue-600 hover:underline">View all →</button>
            </div>
            <table class="min-w-full text-sm">
              <tbody class="divide-y divide-slate-50">
                <tr v-for="inv in client.invoices?.slice(0, 5)" :key="inv.id">
                  <td class="py-1.5">
                    <Link :href="route('admin.invoices.show', inv.id)" class="text-blue-600 hover:underline">#{{ inv.id }}</Link>
                  </td>
                  <td class="py-1.5 text-slate-400 text-xs">{{ inv.due_date }}</td>
                  <td class="py-1.5 text-right font-medium text-slate-700">${{ parseFloat(inv.total).toFixed(2) }}</td>
                  <td class="py-1.5 text-right"><StatusBadge :status="inv.status" /></td>
                </tr>
                <tr v-if="!client.invoices?.length">
                  <td colspan="4" class="py-3 text-center text-slate-400">No invoices.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ── Services tab ─────────────────────────────────────────────── -->
        <div v-show="activeTab === 'services'"
          class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 overflow-hidden shadow-sm">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50/80">
              <tr>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">Service</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">Cycle</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-400 uppercase">Amount</th>
                <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-400 uppercase">Next Due</th>
                <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-400 uppercase">Status</th>
                <th class="px-4 py-2.5"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="s in client.services" :key="s.id" class="hover:bg-blue-50/20">
                <td class="px-4 py-2.5 font-medium text-slate-700">{{ s.domain ?? s.product?.name }}</td>
                <td class="px-4 py-2.5 text-slate-500 capitalize text-xs">{{ s.billing_cycle?.replace(/_/g, ' ') }}</td>
                <td class="px-4 py-2.5 text-right font-medium text-slate-700">${{ parseFloat(s.amount).toFixed(2) }}</td>
                <td class="px-4 py-2.5 text-center text-xs text-slate-500">{{ s.next_due_date ?? '—' }}</td>
                <td class="px-4 py-2.5 text-center"><StatusBadge :status="s.status" /></td>
                <td class="px-4 py-2.5 text-right">
                  <Link :href="route('admin.services.show', s.id)" class="text-xs text-blue-600 hover:underline">View</Link>
                </td>
              </tr>
              <tr v-if="!client.services?.length">
                <td colspan="6" class="px-4 py-6 text-center text-slate-400">No services.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ── Invoices tab ─────────────────────────────────────────────── -->
        <div v-show="activeTab === 'invoices'">
          <div class="flex justify-end mb-3">
            <Link :href="route('admin.invoices.create') + '?user_id=' + client.id"
              class="text-sm text-blue-600 border border-blue-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-colors">
              + New Invoice
            </Link>
          </div>
          <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 overflow-hidden shadow-sm">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50/80">
                <tr>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">Invoice</th>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">Date</th>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">Due</th>
                  <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-400 uppercase">Total</th>
                  <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-400 uppercase">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="inv in client.invoices" :key="inv.id" class="hover:bg-blue-50/20">
                  <td class="px-4 py-2.5">
                    <Link :href="route('admin.invoices.show', inv.id)" class="text-blue-600 hover:underline font-medium">#{{ inv.id }}</Link>
                  </td>
                  <td class="px-4 py-2.5 text-slate-500 text-xs">{{ inv.date }}</td>
                  <td class="px-4 py-2.5 text-slate-500 text-xs">{{ inv.due_date }}</td>
                  <td class="px-4 py-2.5 text-right font-medium text-slate-700">${{ parseFloat(inv.total).toFixed(2) }}</td>
                  <td class="px-4 py-2.5 text-right"><StatusBadge :status="inv.status" /></td>
                </tr>
                <tr v-if="!client.invoices?.length">
                  <td colspan="5" class="px-4 py-6 text-center text-slate-400">No invoices.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ── Support tab ──────────────────────────────────────────────── -->
        <div v-show="activeTab === 'support'"
          class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 overflow-hidden shadow-sm">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50/80">
              <tr>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">Ticket</th>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase">Subject</th>
                <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-400 uppercase">Priority</th>
                <th class="px-4 py-2.5 text-center text-xs font-semibold text-slate-400 uppercase">Status</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-400 uppercase">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-for="t in client.tickets" :key="t.id" class="hover:bg-blue-50/20">
                <td class="px-4 py-2.5">
                  <Link :href="route('admin.support.show', t.id)" class="text-blue-600 hover:underline font-medium">#{{ t.id }}</Link>
                </td>
                <td class="px-4 py-2.5 text-slate-700">{{ t.subject }}</td>
                <td class="px-4 py-2.5 text-center"><StatusBadge :status="t.priority" /></td>
                <td class="px-4 py-2.5 text-center"><StatusBadge :status="t.status" /></td>
                <td class="px-4 py-2.5 text-right text-xs text-slate-400">{{ fmt(t.created_at) }}</td>
              </tr>
              <tr v-if="!client.tickets?.length">
                <td colspan="5" class="px-4 py-6 text-center text-slate-400">No support tickets.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ── Notes tab ────────────────────────────────────────────────── -->
        <div v-show="activeTab === 'notes'" class="space-y-4">
          <!-- Add note -->
          <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Add Interaction Note</h3>
            <div class="flex gap-2 mb-2">
              <button v-for="t in ['note','call','email','meeting']" :key="t"
                type="button"
                @click="noteForm.type = t"
                class="flex items-center gap-1 px-3 py-1 text-xs rounded-full border transition-colors"
                :class="noteForm.type === t
                  ? 'bg-blue-600 text-white border-blue-600'
                  : 'border-slate-200 text-slate-500 hover:border-blue-300'">
                {{ noteIcons[t] }} {{ noteTypeLabels[t] }}
              </button>
            </div>
            <div class="flex gap-2">
              <textarea
                v-model="noteForm.body"
                rows="2"
                :placeholder="{ note: 'Add an internal note…', call: 'Call summary…', email: 'Email summary…', meeting: 'Meeting notes…' }[noteForm.type]"
                class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
              />
              <button
                @click="submitNote"
                :disabled="noteForm.processing || !noteForm.body.trim()"
                class="self-end rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-50 transition-colors"
              >
                Add
              </button>
            </div>
          </div>

          <!-- Notes list -->
          <ul class="space-y-3">
            <li v-for="note in client.notes" :key="note.id"
              class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 px-4 py-3 shadow-sm">
              <div class="flex justify-between items-start gap-2">
                <div class="flex items-start gap-2">
                  <span class="text-lg leading-none mt-0.5">{{ noteIcons[note.type] ?? '📝' }}</span>
                  <div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ noteTypeLabels[note.type] ?? 'Note' }}</span>
                    <p class="text-sm text-slate-700 whitespace-pre-wrap mt-0.5">{{ note.body }}</p>
                  </div>
                </div>
                <button @click="deleteNote(note.id)" class="text-xs text-red-400 hover:text-red-600 shrink-0 mt-0.5">✕</button>
              </div>
              <p class="mt-1.5 text-xs text-slate-400 ml-7">{{ note.author?.name ?? 'Staff' }} · {{ fmt(note.created_at) }}</p>
            </li>
            <li v-if="!client.notes?.length" class="text-sm text-slate-400 text-center py-6">No notes yet.</li>
          </ul>
        </div>

        <!-- ── Tasks tab ────────────────────────────────────────────────── -->
        <div v-show="activeTab === 'tasks'" class="space-y-4">
          <!-- Add task form -->
          <div class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Add Follow-up Task</h3>
            <div class="space-y-2">
              <input v-model="taskForm.title" type="text" placeholder="Task title…"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <div class="grid grid-cols-3 gap-2">
                <select v-model="taskForm.priority"
                  class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="low">Low Priority</option>
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                </select>
                <input v-model="taskForm.due_at" type="date"
                  class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                <select v-model="taskForm.assigned_to"
                  class="border border-slate-200 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="">Unassigned</option>
                  <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <textarea v-model="taskForm.description" rows="2" placeholder="Optional description…"
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none" />
              <button @click="submitTask" :disabled="taskForm.processing || !taskForm.title.trim()"
                class="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-medium rounded-lg py-2 transition-colors">
                {{ taskForm.processing ? 'Adding…' : 'Add Task' }}
              </button>
            </div>
          </div>

          <!-- Pending tasks -->
          <ul class="space-y-2">
            <li v-for="task in pendingTasks" :key="task.id"
              class="bg-white/70 backdrop-blur-sm rounded-xl border border-blue-100/60 px-4 py-3 shadow-sm flex items-start gap-3">
              <button @click="toggleTask(task.id)"
                class="mt-0.5 h-5 w-5 shrink-0 rounded border-2 border-slate-300 hover:border-blue-500 transition-colors flex items-center justify-center" />
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="text-sm font-medium text-slate-800">{{ task.title }}</span>
                  <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium" :class="priorityColors[task.priority]">
                    {{ task.priority }}
                  </span>
                  <span v-if="task.due_at" class="text-xs text-slate-400">Due {{ fmt(task.due_at) }}</span>
                  <span v-if="task.assignee" class="text-xs text-slate-400">→ {{ task.assignee.name }}</span>
                </div>
                <p v-if="task.description" class="text-xs text-slate-500 mt-0.5">{{ task.description }}</p>
              </div>
              <button @click="deleteTask(task.id)" class="text-xs text-red-400 hover:text-red-600 shrink-0">✕</button>
            </li>
            <li v-if="!pendingTasks.length" class="text-sm text-slate-400 text-center py-4">No pending tasks.</li>
          </ul>

          <!-- Completed tasks -->
          <details v-if="completedTasks.length" class="mt-2">
            <summary class="text-xs text-slate-400 cursor-pointer select-none hover:text-slate-600">
              {{ completedTasks.length }} completed task(s)
            </summary>
            <ul class="space-y-2 mt-2">
              <li v-for="task in completedTasks" :key="task.id"
                class="bg-slate-50/70 rounded-xl border border-slate-100 px-4 py-3 flex items-center gap-3 opacity-60">
                <button @click="toggleTask(task.id)"
                  class="h-5 w-5 shrink-0 rounded bg-green-500 border-2 border-green-500 flex items-center justify-center text-white text-xs">✓</button>
                <span class="flex-1 text-sm text-slate-500 line-through">{{ task.title }}</span>
                <button @click="deleteTask(task.id)" class="text-xs text-red-300 hover:text-red-500">✕</button>
              </li>
            </ul>
          </details>
        </div>

      </div>
    </div>
  </div>
</template>
