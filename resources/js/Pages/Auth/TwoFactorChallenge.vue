<script setup>
import { useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

defineOptions({ layout: GuestLayout })

const form = useForm({ code: '' })

function submit() {
  form.post(route('two-factor.challenge'), {
    onFinish: () => form.reset('code'),
  })
}
</script>

<template>
  <div>
    <h2 class="text-xl font-semibold text-white mb-2 text-center">Two-factor authentication</h2>
    <p class="text-sm text-gray-400 text-center mb-6">
      Enter the 6-digit code from your authenticator app.
    </p>

    <form @submit.prevent="submit" class="space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Authentication code</label>
        <input
          v-model="form.code"
          type="text"
          inputmode="numeric"
          autocomplete="one-time-code"
          maxlength="6"
          required
          autofocus
          class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-4 py-2.5 text-sm text-center tracking-[0.5em] font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500"
          placeholder="000000"
        />
        <p v-if="form.errors.code" class="mt-1.5 text-xs text-red-400">{{ form.errors.code }}</p>
      </div>

      <button
        type="submit"
        :disabled="form.processing"
        class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-medium rounded-lg px-4 py-2.5 text-sm transition-colors"
      >
        {{ form.processing ? 'Verifying…' : 'Verify' }}
      </button>

      <p class="text-center text-sm text-gray-500">
        <a :href="route('login')" class="text-indigo-400 hover:text-indigo-300">Back to sign in</a>
      </p>
    </form>
  </div>
</template>
