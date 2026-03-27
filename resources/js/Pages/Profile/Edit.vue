<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const props = defineProps({ user: Object })

const form = useForm({
  name:    props.user.name,
  country: props.user.country ?? '',
  state:   props.user.state   ?? '',
})

// ISO 3166-1 alpha-2 country list (common subset)
const countries = [
  { code: '', label: '— Select country —' },
  { code: 'US', label: 'United States' },
  { code: 'CA', label: 'Canada' },
  { code: 'GB', label: 'United Kingdom' },
  { code: 'AU', label: 'Australia' },
  { code: 'DE', label: 'Germany' },
  { code: 'FR', label: 'France' },
  { code: 'NL', label: 'Netherlands' },
  { code: 'SE', label: 'Sweden' },
  { code: 'NO', label: 'Norway' },
  { code: 'DK', label: 'Denmark' },
  { code: 'FI', label: 'Finland' },
  { code: 'CH', label: 'Switzerland' },
  { code: 'AT', label: 'Austria' },
  { code: 'BE', label: 'Belgium' },
  { code: 'IE', label: 'Ireland' },
  { code: 'NZ', label: 'New Zealand' },
  { code: 'SG', label: 'Singapore' },
  { code: 'IN', label: 'India' },
  { code: 'BR', label: 'Brazil' },
  { code: 'MX', label: 'Mexico' },
  { code: 'ZA', label: 'South Africa' },
]
</script>

<template>
  <div class="max-w-lg">
    <h1 class="text-xl font-bold text-gray-900 mb-6">My Profile</h1>

    <form @submit.prevent="form.patch(route('profile.update'))" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
        <input v-model="form.name" type="text"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input :value="user.email" type="email" disabled
          class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-400 cursor-not-allowed" />
        <p class="text-xs text-gray-400 mt-1">Contact support to change your email address.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
        <select v-model="form.country"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
          <option v-for="c in countries" :key="c.code" :value="c.code">{{ c.label }}</option>
        </select>
        <p class="text-xs text-gray-400 mt-1">Used to calculate applicable taxes at checkout.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">State / Province <span class="text-gray-400 font-normal">(optional)</span></label>
        <input v-model="form.state" type="text" placeholder="e.g. CA, TX, ON"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
      </div>

      <div class="pt-2">
        <button type="submit" :disabled="form.processing"
          class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-60 text-white text-sm font-medium rounded-lg transition-colors">
          {{ form.processing ? 'Saving…' : 'Save Profile' }}
        </button>
      </div>
    </form>
  </div>
</template>
