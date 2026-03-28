<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({ affiliate: { type: Object, default: null } })

const payoutForm = useForm({
  method: '',
  notes:  '',
})

function register() {
  router.post(route('client.affiliate.register'))
}

function submitPayout() {
  payoutForm.post(route('client.affiliate.payout'), {
    onSuccess: () => payoutForm.reset(),
  })
}

const showPayoutForm = ref(false)

const referralUrl = computed(() => {
  if (!props.affiliate?.code) return ''
  return window.location.origin + '?ref=' + props.affiliate.code
})

function copy() {
  navigator.clipboard.writeText(referralUrl.value)
}
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-xl font-bold text-gray-900 mb-6">Affiliate Program</h1>

    <!-- Not registered -->
    <div v-if="!affiliate" class="bg-white rounded-xl border border-gray-200 p-6 text-center">
      <h2 class="font-semibold text-gray-900 mb-2">Join Our Affiliate Program</h2>
      <p class="text-sm text-gray-500 mb-6">
        Earn commissions by referring new customers. Share your unique referral link and get paid when they place an order.
      </p>
      <button @click="register"
        class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-6 py-2.5 rounded-lg">
        Apply to Join
      </button>
    </div>

    <!-- Pending approval -->
    <div v-else-if="affiliate.status === 'pending'" class="bg-amber-50 border border-amber-200 rounded-xl p-6">
      <h2 class="font-semibold text-amber-800 mb-2">Application Pending</h2>
      <p class="text-sm text-amber-700">
        Your affiliate application is under review. You'll be able to start referring customers once it's approved.
      </p>
    </div>

    <!-- Active affiliate -->
    <template v-else-if="affiliate.status === 'active'">

      <!-- Stats -->
      <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
          <p class="text-2xl font-bold text-gray-900">${{ affiliate.balance }}</p>
          <p class="text-xs text-gray-500 mt-1">Available Balance</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
          <p class="text-2xl font-bold text-gray-900">${{ affiliate.total_earned }}</p>
          <p class="text-xs text-gray-500 mt-1">Total Earned</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
          <p class="text-2xl font-bold text-gray-900">{{ affiliate.referrals?.length ?? 0 }}</p>
          <p class="text-xs text-gray-500 mt-1">Referrals</p>
        </div>
      </div>

      <!-- Referral link -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
        <h2 class="font-semibold text-gray-900 mb-3 text-sm">Your Referral Link</h2>
        <div class="flex items-center gap-2">
          <input type="text" :value="referralUrl" readonly
            class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-700 font-mono" />
          <button @click="copy"
            class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg">
            Copy
          </button>
        </div>
        <p class="text-xs text-gray-500 mt-2">
          Commission: <strong>{{ affiliate.commission_value }}{{ affiliate.commission_type === 'percent' ? '%' : ' flat' }}</strong>
          per referred order. Min. payout: <strong>${{ affiliate.payout_threshold }}</strong>.
        </p>
      </div>

      <!-- Referral history -->
      <div class="bg-white rounded-xl border border-gray-200 p-5 mb-4">
        <h2 class="font-semibold text-gray-900 mb-3 text-sm">Referral History</h2>
        <table class="min-w-full text-sm" v-if="affiliate.referrals?.length">
          <thead>
            <tr class="text-left text-gray-500 border-b border-gray-100">
              <th class="pb-2">Date</th>
              <th class="pb-2 text-right">Commission</th>
              <th class="pb-2 text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="r in affiliate.referrals" :key="r.id">
              <td class="py-2 text-gray-500">{{ r.created_at ? new Date(r.created_at).toLocaleDateString() : '—' }}</td>
              <td class="py-2 text-right font-medium">${{ r.commission }}</td>
              <td class="py-2 text-center">
                <span :class="{
                  'bg-yellow-100 text-yellow-700': r.status === 'pending',
                  'bg-green-100 text-green-700': r.status === 'approved',
                  'bg-blue-100 text-blue-700': r.status === 'paid',
                }" class="text-xs font-medium px-2 py-0.5 rounded-full capitalize">{{ r.status }}</span>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-sm text-gray-400">No referrals yet.</p>
      </div>

      <!-- Payout request -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-900 mb-3 text-sm">Request Payout</h2>

        <p v-if="Number(affiliate.balance) < Number(affiliate.payout_threshold)" class="text-sm text-gray-500">
          Minimum payout balance is ${{ affiliate.payout_threshold }}. Your current balance is ${{ affiliate.balance }}.
        </p>

        <template v-else>
          <div v-if="!showPayoutForm">
            <p class="text-sm text-gray-500 mb-3">
              You have <strong>${{ affiliate.balance }}</strong> available for payout.
            </p>
            <button @click="showPayoutForm = true"
              class="bg-green-600 hover:bg-green-500 text-white text-sm font-medium px-5 py-2 rounded-lg">
              Request Payout
            </button>
          </div>

          <form v-else @submit.prevent="submitPayout" class="space-y-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Payment Method</label>
              <input v-model="payoutForm.method" type="text" placeholder="e.g. PayPal, Bank Transfer"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required />
              <p v-if="payoutForm.errors.method" class="mt-1 text-xs text-red-600">{{ payoutForm.errors.method }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Notes (optional)</label>
              <textarea v-model="payoutForm.notes" rows="2"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div class="flex gap-3">
              <button type="button" @click="showPayoutForm = false" class="text-sm text-gray-500 px-4 py-2">Cancel</button>
              <button type="submit" :disabled="payoutForm.processing"
                class="bg-green-600 hover:bg-green-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg">
                Submit Request
              </button>
            </div>
          </form>
        </template>
      </div>

    </template>

    <!-- Inactive -->
    <div v-else class="bg-gray-50 border border-gray-200 rounded-xl p-6">
      <p class="text-sm text-gray-500">Your affiliate account is currently inactive. Please contact support.</p>
    </div>

  </div>
</template>
