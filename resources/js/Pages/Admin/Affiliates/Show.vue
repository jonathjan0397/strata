<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm, router } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ affiliate: Object, pendingPayouts: Array })

const settingsForm = useForm({
  code:             props.affiliate.code,
  commission_type:  props.affiliate.commission_type,
  commission_value: props.affiliate.commission_value,
  payout_threshold: props.affiliate.payout_threshold,
  notes:            props.affiliate.notes ?? '',
})

function saveSettings() {
  settingsForm.patch(route('admin.affiliates.update', props.affiliate.id))
}

function approve() {
  router.post(route('admin.affiliates.approve', props.affiliate.id))
}

function deactivate() {
  if (confirm('Deactivate this affiliate?')) {
    router.post(route('admin.affiliates.deactivate', props.affiliate.id))
  }
}

function remove() {
  if (confirm('Permanently remove this affiliate? Their referral history and payouts will also be deleted.')) {
    router.delete(route('admin.affiliates.destroy', props.affiliate.id))
  }
}

function approveReferral(id) {
  router.post(route('admin.affiliates.referrals.approve', id))
}

function approvePayout(id) {
  if (confirm('Mark this payout as paid?')) {
    router.post(route('admin.affiliates.payouts.approve', id))
  }
}

function fmt(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString()
}
</script>

<template>
  <div class="max-w-5xl">
    <div class="flex items-center gap-3 mb-6">
      <Link :href="route('admin.affiliates.index')" class="text-sm text-gray-500 hover:text-gray-700">← Affiliates</Link>
      <h1 class="text-xl font-bold text-gray-900">{{ affiliate.user?.name }}</h1>
      <span :class="{
        'bg-green-100 text-green-700': affiliate.status === 'active',
        'bg-yellow-100 text-yellow-700': affiliate.status === 'pending',
        'bg-gray-100 text-gray-500': affiliate.status === 'inactive',
      }" class="text-xs font-medium px-2 py-0.5 rounded-full capitalize">{{ affiliate.status }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Stats -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3 text-sm">
        <h2 class="font-semibold text-gray-900">Overview</h2>
        <div class="grid grid-cols-2 gap-y-2">
          <span class="text-gray-500">Referral Code</span> <span class="font-mono font-medium">{{ affiliate.code }}</span>
          <span class="text-gray-500">Balance</span>       <span class="font-semibold text-green-700">${{ affiliate.balance }}</span>
          <span class="text-gray-500">Total Earned</span>  <span>${{ affiliate.total_earned }}</span>
          <span class="text-gray-500">Commission</span>
          <span class="capitalize">{{ affiliate.commission_value }}{{ affiliate.commission_type === 'percent' ? '%' : ' (fixed)' }}</span>
          <span class="text-gray-500">Min Payout</span>    <span>${{ affiliate.payout_threshold }}</span>
        </div>
      </div>

      <!-- Actions -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm">
        <h2 class="font-semibold text-gray-900 mb-3">Actions</h2>
        <div class="flex flex-col gap-2">
          <button v-if="affiliate.status === 'pending'" @click="approve"
            class="w-full px-3 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700">
            Approve Affiliate
          </button>
          <button v-if="affiliate.status !== 'inactive'" @click="deactivate"
            class="w-full px-3 py-2 rounded-lg bg-gray-200 text-gray-700 text-sm font-medium hover:bg-gray-300">
            Deactivate
          </button>
          <button v-if="affiliate.status === 'inactive'" @click="approve"
            class="w-full px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
            Reactivate
          </button>
          <button @click="remove"
            class="w-full px-3 py-2 rounded-lg bg-red-100 text-red-700 text-sm font-medium hover:bg-red-200">
            Remove Affiliate
          </button>
        </div>
      </div>

      <!-- Commission Settings -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 text-sm">
        <h2 class="font-semibold text-gray-900 mb-3">Settings</h2>
        <form @submit.prevent="saveSettings" class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Referral Code</label>
            <input v-model="settingsForm.code" type="text" maxlength="20"
              class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm font-mono uppercase" />
            <p v-if="settingsForm.errors.code" class="text-xs text-red-600 mt-1">{{ settingsForm.errors.code }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Commission Type</label>
            <select v-model="settingsForm.commission_type" class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
              <option value="percent">Percent (%)</option>
              <option value="fixed">Fixed ($)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">
              Value ({{ settingsForm.commission_type === 'percent' ? '%' : '$' }})
            </label>
            <input v-model="settingsForm.commission_value" type="number" step="0.01" min="0"
              class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Payout Threshold ($)</label>
            <input v-model="settingsForm.payout_threshold" type="number" step="0.01" min="0"
              class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
            <textarea v-model="settingsForm.notes" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm resize-none" />
          </div>
          <button type="submit" :disabled="settingsForm.processing"
            class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium px-3 py-2 rounded-lg">
            Save
          </button>
        </form>
      </div>

      <!-- Referrals -->
      <div class="lg:col-span-3 bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-3 text-sm">Referrals</h2>
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-100">
              <th class="pb-2">Client</th>
              <th class="pb-2">Order</th>
              <th class="pb-2 text-right">Order Amount</th>
              <th class="pb-2 text-right">Commission</th>
              <th class="pb-2 text-center">Status</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="r in affiliate.referrals" :key="r.id">
              <td class="py-2">{{ r.referred_user?.name ?? '—' }}</td>
              <td class="py-2 text-gray-500 font-mono">{{ r.order?.order_number ?? '—' }}</td>
              <td class="py-2 text-right">${{ r.amount }}</td>
              <td class="py-2 text-right font-medium text-green-700">${{ r.commission }}</td>
              <td class="py-2 text-center">
                <span :class="{
                  'bg-yellow-100 text-yellow-700': r.status === 'pending',
                  'bg-green-100 text-green-700': r.status === 'approved',
                  'bg-blue-100 text-blue-700': r.status === 'paid',
                  'bg-red-100 text-red-700': r.status === 'rejected',
                }" class="text-xs font-medium px-2 py-0.5 rounded-full capitalize">{{ r.status }}</span>
              </td>
              <td class="py-2 text-right">
                <button v-if="r.status === 'pending' && r.order_id" @click="approveReferral(r.id)"
                  class="text-xs text-green-600 hover:underline">Approve</button>
              </td>
            </tr>
            <tr v-if="!affiliate.referrals?.length">
              <td colspan="6" class="py-6 text-center text-gray-400">No referrals yet.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Payouts -->
      <div class="lg:col-span-3 bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-3 text-sm">Payout History</h2>
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-100">
              <th class="pb-2">Date</th>
              <th class="pb-2">Method</th>
              <th class="pb-2 text-right">Amount</th>
              <th class="pb-2 text-center">Status</th>
              <th class="pb-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="p in affiliate.payouts" :key="p.id">
              <td class="py-2 text-gray-500">{{ fmt(p.created_at) }}</td>
              <td class="py-2">{{ p.method }}</td>
              <td class="py-2 text-right font-medium">${{ p.amount }}</td>
              <td class="py-2 text-center">
                <span :class="{
                  'bg-yellow-100 text-yellow-700': p.status === 'pending',
                  'bg-green-100 text-green-700': p.status === 'paid',
                }" class="text-xs font-medium px-2 py-0.5 rounded-full capitalize">{{ p.status }}</span>
              </td>
              <td class="py-2 text-right">
                <button v-if="p.status === 'pending'" @click="approvePayout(p.id)"
                  class="text-xs text-green-600 hover:underline">Mark Paid</button>
              </td>
            </tr>
            <tr v-if="!affiliate.payouts?.length">
              <td colspan="5" class="py-6 text-center text-gray-400">No payouts yet.</td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</template>
