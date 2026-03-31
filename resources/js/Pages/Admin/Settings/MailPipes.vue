<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    pipes:       Array,
    departments: Array,
    staff:       Array,
    appUrl:      String,
})

// ── Form factory ─────────────────────────────────────────────────────────────
const mkForm = (src = {}) => useForm({
    name:                        src.name                        ?? '',
    email_address:               src.email_address               ?? '',
    department_id:               src.department_id               ?? '',
    auto_assign_to:              src.auto_assign_to              ?? '',
    default_priority:            src.default_priority            ?? 'medium',
    create_client_if_not_exists: src.create_client_if_not_exists ?? true,
    strip_signature:             src.strip_signature             ?? true,
    auto_reply_enabled:          src.auto_reply_enabled          ?? false,
    auto_reply_subject:          src.auto_reply_subject          ?? '',
    auto_reply_body:             src.auto_reply_body             ?? '',
    reject_unknown_senders:      src.reject_unknown_senders      ?? false,
    is_active:                   src.is_active                   ?? true,
})

// ── Create ────────────────────────────────────────────────────────────────────
const showCreate = ref(false)
const createForm = ref(mkForm())

function submitCreate() {
    createForm.value.post(route('admin.mail-pipes.store'), {
        onSuccess: () => { createForm.value = mkForm(); showCreate.value = false },
    })
}

// ── Edit ──────────────────────────────────────────────────────────────────────
const editId   = ref(null)
const editForm = ref(null)

function startEdit(pipe) {
    editId.value   = pipe.id
    editForm.value = mkForm(pipe)
}

function submitEdit(pipe) {
    editForm.value.patch(route('admin.mail-pipes.update', pipe.id), {
        onSuccess: () => { editId.value = null; editForm.value = null },
    })
}

// ── Other actions ─────────────────────────────────────────────────────────────
function deletePipe(pipe) {
    if (confirm(`Delete pipe "${pipe.name}"? This cannot be undone.`)) {
        router.delete(route('admin.mail-pipes.destroy', pipe.id))
    }
}

function regenerateToken(pipe) {
    if (confirm('Regenerate the token? The old pipe command / URL will stop working immediately.')) {
        router.post(route('admin.mail-pipes.token', pipe.id))
    }
}

// ── Clipboard ─────────────────────────────────────────────────────────────────
const copied = ref(null)
function copyText(text, key) {
    navigator.clipboard.writeText(text).then(() => {
        copied.value = key
        setTimeout(() => { copied.value = null }, 2000)
    })
}

const pipeUrl     = (p) => `${props.appUrl}/pipe/${p.pipe_token}`
const forwardRule = (p) => `| php /path/to/artisan mail:pipe ${p.pipe_token}`

const priorityColors = {
    low:    'bg-gray-100 text-gray-600',
    medium: 'bg-blue-100 text-blue-700',
    high:   'bg-orange-100 text-orange-700',
    urgent: 'bg-red-100 text-red-700',
}

const inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500'
const selectCls = inputCls
</script>

<template>
  <div class="max-w-3xl">

    <!-- Page header -->
    <div class="flex items-start justify-between mb-6">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <Link :href="route('admin.settings.index')" class="text-sm text-gray-400 hover:text-gray-600">← Settings</Link>
          <span class="text-gray-300">/</span>
          <h1 class="text-xl font-bold text-gray-900">Mail Pipes</h1>
        </div>
        <p class="text-sm text-gray-500">Route inbound emails to tickets. Each pipe maps an address to a department with its own rules.</p>
      </div>
      <button @click="showCreate = !showCreate"
        class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shrink-0">
        + New Pipe
      </button>
    </div>

    <!-- ── Create form ─────────────────────────────────────────────────────── -->
    <div v-if="showCreate" class="bg-white rounded-xl border border-indigo-200 p-5 mb-6">
      <h2 class="text-sm font-semibold text-gray-800 mb-4">Create Mail Pipe</h2>
      <form @submit.prevent="submitCreate" class="space-y-4">

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Pipe Name *</label>
            <input v-model="createForm.name" type="text" required placeholder="e.g. Support, Billing, Sales" :class="inputCls" />
            <p v-if="createForm.errors.name" class="text-xs text-red-500 mt-1">{{ createForm.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-gray-400 font-normal">(display only)</span></label>
            <input v-model="createForm.email_address" type="email" placeholder="support@yourdomain.com" :class="inputCls" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
            <select v-model="createForm.department_id" :class="selectCls">
              <option value="">— None —</option>
              <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Auto-assign To</label>
            <select v-model="createForm.auto_assign_to" :class="selectCls">
              <option value="">— Unassigned —</option>
              <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Default Priority</label>
            <select v-model="createForm.default_priority" :class="selectCls">
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
        </div>

        <!-- Toggles -->
        <div class="grid grid-cols-2 gap-3">
          <label v-for="(label, key, hint) in {
            create_client_if_not_exists: 'Auto-create client accounts for unknown senders',
            strip_signature: 'Strip email signatures from ticket body',
            reject_unknown_senders: 'Reject emails from unrecognised senders (overrides auto-create)',
            is_active: 'Pipe is active',
          }" :key="key" class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" v-model="createForm[key]" class="mt-0.5 rounded text-indigo-600" />
            {{ label }}
          </label>
        </div>

        <!-- Auto-reply -->
        <div class="border-t border-gray-100 pt-4 space-y-3">
          <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
            <input type="checkbox" v-model="createForm.auto_reply_enabled" class="rounded text-indigo-600" />
            Send auto-reply on new tickets
          </label>
          <template v-if="createForm.auto_reply_enabled">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Auto-reply Subject <span class="text-gray-400">(leave blank for default)</span></label>
              <input v-model="createForm.auto_reply_subject" type="text" placeholder="We received your message [Ticket #ID]" :class="inputCls" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Auto-reply Body <span class="text-gray-400">(leave blank for default)</span></label>
              <textarea v-model="createForm.auto_reply_body" rows="4" :class="inputCls" placeholder="Thank you for contacting us. Your ticket #ID has been received…" />
            </div>
          </template>
        </div>

        <div class="flex gap-2 pt-1">
          <button type="submit" :disabled="createForm.processing"
            class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
            {{ createForm.processing ? 'Creating…' : 'Create Pipe' }}
          </button>
          <button type="button" @click="showCreate = false"
            class="px-4 py-2 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
            Cancel
          </button>
        </div>
      </form>
    </div>

    <!-- ── Empty state ─────────────────────────────────────────────────────── -->
    <div v-if="pipes.length === 0 && !showCreate"
      class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400 text-sm">
      No mail pipes configured yet. Click <strong>+ New Pipe</strong> to get started.
    </div>

    <!-- ── Pipe cards ──────────────────────────────────────────────────────── -->
    <div v-for="pipe in pipes" :key="pipe.id" class="bg-white rounded-xl border border-gray-200 mb-4">

      <!-- Edit mode -->
      <div v-if="editId === pipe.id && editForm" class="p-5">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">Editing: {{ pipe.name }}</h3>
        <form @submit.prevent="submitEdit(pipe)" class="space-y-4">

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Pipe Name *</label>
              <input v-model="editForm.name" type="text" required :class="inputCls" />
              <p v-if="editForm.errors.name" class="text-xs text-red-500 mt-1">{{ editForm.errors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
              <input v-model="editForm.email_address" type="email" :class="inputCls" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
              <select v-model="editForm.department_id" :class="selectCls">
                <option value="">— None —</option>
                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Auto-assign To</label>
              <select v-model="editForm.auto_assign_to" :class="selectCls">
                <option value="">— Unassigned —</option>
                <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Default Priority</label>
              <select v-model="editForm.default_priority" :class="selectCls">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <label v-for="(label, key) in {
              create_client_if_not_exists: 'Auto-create client accounts for unknown senders',
              strip_signature: 'Strip email signatures from ticket body',
              reject_unknown_senders: 'Reject emails from unrecognised senders',
              is_active: 'Pipe is active',
            }" :key="key" class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
              <input type="checkbox" v-model="editForm[key]" class="mt-0.5 rounded text-indigo-600" />
              {{ label }}
            </label>
          </div>

          <div class="border-t border-gray-100 pt-4 space-y-3">
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
              <input type="checkbox" v-model="editForm.auto_reply_enabled" class="rounded text-indigo-600" />
              Send auto-reply on new tickets
            </label>
            <template v-if="editForm.auto_reply_enabled">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Auto-reply Subject</label>
                <input v-model="editForm.auto_reply_subject" type="text" :class="inputCls" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Auto-reply Body</label>
                <textarea v-model="editForm.auto_reply_body" rows="4" :class="inputCls" />
              </div>
            </template>
          </div>

          <div class="flex gap-2 pt-1">
            <button type="submit" :disabled="editForm.processing"
              class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
              {{ editForm.processing ? 'Saving…' : 'Save Changes' }}
            </button>
            <button type="button" @click="editId = null"
              class="px-4 py-2 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
              Cancel
            </button>
          </div>
        </form>
      </div>

      <!-- View mode -->
      <div v-else class="p-5">
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-semibold text-gray-900">{{ pipe.name }}</span>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize"
                :class="priorityColors[pipe.default_priority]">
                {{ pipe.default_priority }}
              </span>
              <span v-if="!pipe.is_active"
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                Inactive
              </span>
            </div>

            <div class="mt-1.5 text-xs text-gray-500 space-y-0.5">
              <div v-if="pipe.email_address">📬 {{ pipe.email_address }}</div>
              <div>📂 {{ pipe.department?.name ?? 'No department assigned' }}</div>
              <div v-if="pipe.assignee">👤 Auto-assigns to {{ pipe.assignee.name }}</div>
            </div>

            <div class="flex gap-3 mt-2 flex-wrap text-xs">
              <span :class="pipe.create_client_if_not_exists ? 'text-green-600' : 'text-gray-400'">
                {{ pipe.create_client_if_not_exists ? '✓ Auto-create clients' : '✗ No auto-create' }}
              </span>
              <span :class="pipe.strip_signature ? 'text-green-600' : 'text-gray-400'">
                {{ pipe.strip_signature ? '✓ Strip signatures' : '✗ Keep signatures' }}
              </span>
              <span :class="pipe.auto_reply_enabled ? 'text-green-600' : 'text-gray-400'">
                {{ pipe.auto_reply_enabled ? '✓ Auto-reply' : '✗ No auto-reply' }}
              </span>
              <span :class="pipe.reject_unknown_senders ? 'text-orange-600' : 'text-gray-400'">
                {{ pipe.reject_unknown_senders ? '⚠ Reject unknown senders' : '✓ Accept all senders' }}
              </span>
            </div>
          </div>

          <div class="flex gap-2 shrink-0">
            <button @click="startEdit(pipe)"
              class="px-3 py-1.5 text-xs font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
              Edit
            </button>
            <button @click="deletePipe(pipe)"
              class="px-3 py-1.5 text-xs font-medium text-red-500 border border-red-100 rounded-lg hover:bg-red-50 transition-colors">
              Delete
            </button>
          </div>
        </div>

        <!-- Integration snippets -->
        <div class="mt-4 pt-4 border-t border-gray-100 space-y-3">
          <div>
            <p class="text-xs font-medium text-gray-500 mb-1">HTTP Endpoint <span class="font-normal text-gray-400">(SendGrid / Mailgun / Postmark inbound webhook)</span></p>
            <div class="flex items-center gap-2">
              <code class="flex-1 text-xs bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 font-mono text-gray-700 truncate">{{ pipeUrl(pipe) }}</code>
              <button @click="copyText(pipeUrl(pipe), 'url-' + pipe.id)"
                class="shrink-0 px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-500">
                {{ copied === 'url-' + pipe.id ? '✓ Copied' : 'Copy' }}
              </button>
            </div>
          </div>

          <div>
            <p class="text-xs font-medium text-gray-500 mb-1">.forward / procmail rule</p>
            <div class="flex items-center gap-2">
              <code class="flex-1 text-xs bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 font-mono text-gray-700 truncate">{{ forwardRule(pipe) }}</code>
              <button @click="copyText(forwardRule(pipe), 'cmd-' + pipe.id)"
                class="shrink-0 px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-500">
                {{ copied === 'cmd-' + pipe.id ? '✓ Copied' : 'Copy' }}
              </button>
            </div>
          </div>

          <div class="flex items-center justify-between text-xs">
            <span class="text-gray-400">Token: <span class="font-mono">{{ pipe.pipe_token.slice(0, 12) }}…</span></span>
            <button @click="regenerateToken(pipe)" class="text-orange-500 hover:underline">Regenerate token</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Setup guide -->
    <div class="mt-2 rounded-xl border border-blue-100 bg-blue-50 p-5 space-y-3 text-blue-800">
      <p class="text-sm font-semibold">Setup Guide</p>
      <div class="text-xs text-blue-700 space-y-3">
        <div>
          <strong>Option 1 — .forward file</strong> (cPanel / Exim):<br>
          In cPanel → Email Accounts, set a forwarder for the address to pipe to the artisan command. The .forward file contents should be the rule shown above (replace <code class="bg-white/70 px-1 rounded">/path/to/artisan</code> with your installation path).
        </div>
        <div>
          <strong>Option 2 — HTTP webhook</strong> (SendGrid Inbound Parse, Mailgun Routes, Postmark Inbound):<br>
          Point the inbound parse webhook to the <strong>HTTP Endpoint</strong> URL above. The raw RFC 2822 message must be sent as the POST body.
        </div>
        <div>
          <strong>Option 3 — procmail</strong>:<br>
          Add to <code class="bg-white/70 px-1 rounded">~/.procmailrc</code>:
          <pre class="mt-1 bg-white/60 rounded px-2 py-1.5 font-mono text-xs whitespace-pre">:0
| php /path/to/artisan mail:pipe YOUR_TOKEN</pre>
        </div>
        <div>
          <strong>Reply threading:</strong> Clients can reply to ticket notification emails — the reply is automatically appended to the existing ticket as long as the subject contains <code class="bg-white/70 px-1 rounded">[Ticket #ID]</code>.
        </div>
      </div>
    </div>

  </div>
</template>
