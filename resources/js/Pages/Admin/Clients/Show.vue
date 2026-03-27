<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

defineProps({ client: Object })

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
    </div>
  </div>
</template>
