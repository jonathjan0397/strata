<script setup>
import { useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

defineOptions({ layout: GuestLayout })

defineProps({
  status: String,
})

const form = useForm({ email: '' })

function submit() {
  form.post(route('password.email'))
}
</script>

<template>
  <div>
    <h2 class="text-xl font-semibold text-white mb-2 text-center">Reset your password</h2>
    <p class="text-sm text-gray-400 text-center mb-6">
      Enter your email and we'll send you a reset link.
    </p>

    <div v-if="status" class="mb-4 text-sm text-green-400 bg-green-900/30 border border-green-800 rounded-lg px-4 py-3">
      {{ status }}
    </div>

    <form @submit.prevent="submit" class="space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Email address</label>
        <input
          v-model="form.email"
          type="email"
          autocomplete="email"
          required
          class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500"
          placeholder="you@example.com"
        />
        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-400">{{ form.errors.email }}</p>
      </div>

      <button
        type="submit"
        :disabled="form.processing"
        class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-medium rounded-lg px-4 py-2.5 text-sm transition-colors"
      >
        {{ form.processing ? 'Sending…' : 'Send reset link' }}
      </button>

      <p class="text-center text-sm text-gray-500">
        <a :href="route('login')" class="text-indigo-400 hover:text-indigo-300">Back to sign in</a>
      </p>
    </form>
  </div>
</template>
