<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, useForm, usePage, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ client: Object, groups: Array })

const flash = computed(() => usePage().props.flash)

// Credit top-up form
const showCreditForm = ref(false)
const creditForm = useForm({ amount: '', description: 'Account credit top-up' })

function submitCredit(clientId) {
  creditForm.post(route('admin.clients.credit', clientId), {
    onSuccess: () => {
      showCreditForm.value = false
      creditForm.reset()
    },
  })
}

// Internal notes
const noteForm = useForm({ body: '' })

function submitNote(clientId) {
  noteForm.post(route('admin.clients.notes.store', clientId), {
    onSuccess: () => noteForm.reset(),
  })
}

function deleteNote(clientId, noteId) {
  if (confirm('Delete this note?')) {
    router.delete(route('admin.clients.notes.destroy', [clientId, noteId]))
  }
}
</script>

<template>
  <div class="max-w-5xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.clients.index')" class="text-sm text-gray-500 hover:text-gray-700">← Clients</Link>
      <span class="text-gray-300">/</span>
      <h1 class="text-xl font-bold text-gray-900">{{ client.name }}</h1>
    </div>

    <div v-if="flash?.success" class="mb-4 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
      {{ flash.success }}
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Client info -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-2 text-sm">
        <h2 class="font-semibold text-gray-900 mb-3">Account Details</h2>
        <div><span class="text-gray-500">Email:</span> {{ client.email }}</div>
        <div class="flex items-center justify-between">
          <span><span class="text-gray-500">Credit Balance:</span> <strong>${{ client.credit_balance }}</strong></span>
          <button @click="showCreditForm = !showCreditForm"
            class="text-xs text-indigo-600 hover:underline">{{ showCreditForm ? 'Cancel' : '+ Add' }}</button>
        </div>
        <div><span class="text-gray-500">Verified:</span> {{ client.email_verified_at ? 'Yes' : 'No' }}</div>
        <div><span class="text-gray-500">Joined:</span> {{ new Date(client.created_at).toLocaleDateString() }}</div>
        <div class="pt-2 border-t border-gray-100">
          <label class="block text-xs font-medium text-gray-500 mb-1">Client Group</label>
          <select
            :value="client.client_group_id"
            @change="router.post(route('admin.client-groups.assign', client.id), { client_group_id: $event.target.value || null })"
            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"
          >
            <option value="">— No group —</option>
            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
        </div>

        <!-- Credit top-up mini form -->
        <div v-if="showCreditForm" class="pt-3 border-t border-gray-100 space-y-2">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Amount ($)</label>
            <input v-model="creditForm.amount" type="number" step="0.01" min="0.01" placeholder="0.00"
              class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <p v-if="creditForm.errors.amount" class="text-red-500 text-xs mt-0.5">{{ creditForm.errors.amount }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Note</label>
            <input v-model="creditForm.description" type="text"
              class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500" />
          </div>
          <button @click="submitCredit(client.id)" :disabled="creditForm.processing || !creditForm.amount"
            class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-60 text-white text-xs font-medium rounded-lg py-1.5 transition-colors">
            {{ creditForm.processing ? 'Adding…' : 'Add Credit' }}
          </button>
        </div>
      </div>

      <!-- Services -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-3">Services</h2>
        <ul class="divide-y divide-gray-100 text-sm">
          <li v-for="s in client.services" :key="s.id" class="py-2 flex justify-between">
            <span>{{ s.domain ?? s.product?.name }}</span>
            <StatusBadge :status="s.status" />
          </li>
          <li v-if="!client.services?.length" class="py-4 text-gray-400 text-center">No services.</li>
        </ul>
      </div>

      <!-- Invoices -->
      <div class="lg:col-span-3 bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-3">Recent Invoices</h2>
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-100">
              <th class="pb-2">ID</th>
              <th class="pb-2">Date</th>
              <th class="pb-2">Due</th>
              <th class="pb-2 text-right">Total</th>
              <th class="pb-2 text-right">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="inv in client.invoices" :key="inv.id">
              <td class="py-2">
                <Link :href="route('admin.invoices.show', inv.id)" class="text-indigo-600 hover:underline">#{{ inv.id }}</Link>
              </td>
              <td class="py-2 text-gray-500">{{ inv.date }}</td>
              <td class="py-2 text-gray-500">{{ inv.due_date }}</td>
              <td class="py-2 text-right font-medium">${{ inv.total }}</td>
              <td class="py-2 text-right"><StatusBadge :status="inv.status" /></td>
            </tr>
            <tr v-if="!client.invoices?.length">
              <td colspan="5" class="py-4 text-center text-gray-400">No invoices.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Internal Notes (staff only) -->
      <div class="lg:col-span-3 bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-4">Internal Notes <span class="text-xs font-normal text-gray-400 ml-1">(not visible to client)</span></h2>

        <!-- Add note form -->
        <div class="mb-4 flex gap-2">
          <textarea
            v-model="noteForm.body"
            rows="2"
            placeholder="Add an internal note…"
            class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 resize-none"
          />
          <button
            @click="submitNote(client.id)"
            :disabled="noteForm.processing || !noteForm.body.trim()"
            class="self-end rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          >
            {{ noteForm.processing ? 'Saving…' : 'Add' }}
          </button>
        </div>

        <!-- Notes list -->
        <ul class="space-y-3">
          <li
            v-for="note in client.notes"
            :key="note.id"
            class="rounded-lg bg-amber-50 border border-amber-100 px-4 py-3 text-sm"
          >
            <div class="flex justify-between items-start gap-2">
              <p class="text-gray-800 whitespace-pre-wrap">{{ note.body }}</p>
              <button @click="deleteNote(client.id, note.id)" class="text-xs text-red-400 hover:text-red-600 shrink-0">Delete</button>
            </div>
            <p class="mt-1 text-xs text-gray-400">{{ note.author?.name ?? 'Staff' }} · {{ note.created_at }}</p>
          </li>
          <li v-if="!client.notes?.length" class="text-sm text-gray-400 text-center py-4">No internal notes.</li>
        </ul>
      </div>
    </div>
  </div>
</template>
