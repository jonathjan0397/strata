<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
  affiliates:    Object,
  eligibleUsers: Array,
})

// ── Create modal ──────────────────────────────────────────────────────────────
const showModal  = ref(false)
const userSearch = ref('')

const filteredUsers = computed(() => {
  const q = userSearch.value.toLowerCase()
  if (!q) return props.eligibleUsers.slice(0, 50)
  return props.eligibleUsers
    .filter(u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q))
    .slice(0, 50)
})

const createForm = useForm({
  user_id:          '',
  code:             '',
  status:           'active',
  commission_type:  'percent',
  commission_value: 10,
  payout_threshold: 50,
  notes:            '',
})

function openModal() {
  createForm.reset()
  userSearch.value = ''
  showModal.value = true
}

function submit() {
  createForm.post(route('admin.affiliates.store'), {
    onSuccess: () => { showModal.value = false },
  })
}

// ── Delete ────────────────────────────────────────────────────────────────────
function remove(id) {
  if (confirm('Remove this affiliate? Their referral history and payouts will also be deleted.')) {
    router.delete(route('admin.affiliates.destroy', id))
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-900">Affiliates</h1>
      <button @click="openModal"
        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg">
        + Add Affiliate
      </button>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="min-w-full divide-y divide-gray-100 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Affiliate</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Code</th>
            <th class="px-4 py-3 text-left font-medium text-gray-500">Commission</th>
            <th class="px-4 py-3 text-center font-medium text-gray-500">Status</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Referrals</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Balance</th>
            <th class="px-4 py-3 text-right font-medium text-gray-500">Total Earned</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="a in affiliates.data" :key="a.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900">{{ a.user?.name }}</div>
              <div class="text-xs text-gray-500">{{ a.user?.email }}</div>
            </td>
            <td class="px-4 py-3 font-mono text-sm">{{ a.code }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">
              {{ a.commission_value }}{{ a.commission_type === 'percent' ? '%' : ' fixed' }}
            </td>
            <td class="px-4 py-3 text-center">
              <span :class="{
                'bg-green-100 text-green-700': a.status === 'active',
                'bg-yellow-100 text-yellow-700': a.status === 'pending',
                'bg-gray-100 text-gray-500': a.status === 'inactive',
              }" class="text-xs font-medium px-2 py-0.5 rounded-full capitalize">{{ a.status }}</span>
            </td>
            <td class="px-4 py-3 text-right text-gray-600">{{ a.referrals_count }}</td>
            <td class="px-4 py-3 text-right font-medium">${{ a.balance }}</td>
            <td class="px-4 py-3 text-right text-gray-500">${{ a.total_earned }}</td>
            <td class="px-4 py-3 text-right flex items-center justify-end gap-3">
              <Link :href="route('admin.affiliates.show', a.id)" class="text-xs text-indigo-600 hover:underline">View</Link>
              <button @click="remove(a.id)" class="text-xs text-red-500 hover:underline">Remove</button>
            </td>
          </tr>
          <tr v-if="!affiliates.data?.length">
            <td colspan="8" class="px-4 py-8 text-center text-gray-400">No affiliates yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="affiliates.last_page > 1" class="mt-4 flex items-center justify-between text-sm text-gray-500">
      <span>Showing {{ affiliates.from }}–{{ affiliates.to }} of {{ affiliates.total }}</span>
      <div class="flex gap-2">
        <Link v-if="affiliates.prev_page_url" :href="affiliates.prev_page_url"
          class="px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50">← Prev</Link>
        <Link v-if="affiliates.next_page_url" :href="affiliates.next_page_url"
          class="px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50">Next →</Link>
      </div>
    </div>

    <!-- Create Affiliate Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-base font-semibold text-gray-900">Add Affiliate</h2>
          <button @click="showModal = false" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
        </div>

        <form @submit.prevent="submit" class="space-y-4">

          <!-- User search -->
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">User</label>
            <input v-model="userSearch" type="text" placeholder="Search by name or email..."
              class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm mb-1" />
            <select v-model="createForm.user_id" size="4"
              class="w-full border border-gray-300 rounded-lg px-3 py-1 text-sm">
              <option value="" disabled>— select a user —</option>
              <option v-for="u in filteredUsers" :key="u.id" :value="u.id">
                {{ u.name }} ({{ u.email }})
              </option>
            </select>
            <p v-if="createForm.errors.user_id" class="text-xs text-red-600 mt-1">{{ createForm.errors.user_id }}</p>
          </div>

          <!-- Code -->
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Referral Code <span class="text-gray-400 font-normal">(leave blank to auto-generate)</span></label>
            <input v-model="createForm.code" type="text" maxlength="20" placeholder="e.g. MYCODE123"
              class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm uppercase" />
            <p v-if="createForm.errors.code" class="text-xs text-red-600 mt-1">{{ createForm.errors.code }}</p>
          </div>

          <!-- Commission -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Commission Type</label>
              <select v-model="createForm.commission_type"
                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                <option value="percent">Percent (%)</option>
                <option value="fixed">Fixed ($)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">
                Value ({{ createForm.commission_type === 'percent' ? '%' : '$' }})
              </label>
              <input v-model="createForm.commission_value" type="number" step="0.01" min="0"
                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm" />
              <p v-if="createForm.errors.commission_value" class="text-xs text-red-600 mt-1">{{ createForm.errors.commission_value }}</p>
            </div>
          </div>

          <!-- Payout Threshold + Status -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Payout Threshold ($)</label>
              <input v-model="createForm.payout_threshold" type="number" step="0.01" min="0"
                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Initial Status</label>
              <select v-model="createForm.status"
                class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                <option value="active">Active</option>
                <option value="pending">Pending</option>
              </select>
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
            <textarea v-model="createForm.notes" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm resize-none" />
          </div>

          <div class="flex justify-end gap-3 pt-1">
            <button type="button" @click="showModal = false"
              class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
              Cancel
            </button>
            <button type="submit" :disabled="createForm.processing || !createForm.user_id"
              class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 rounded-lg">
              Create Affiliate
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
