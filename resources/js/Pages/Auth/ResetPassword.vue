<script setup>
import { useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

defineOptions({ layout: GuestLayout })

const props = defineProps({
  token: { type: String, required: true },
  email: { type: String, required: true },
})

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post(route('password.store'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <div>
    <h2 class="text-xl font-semibold text-white mb-6 text-center">Set new password</h2>

    <form @submit.prevent="submit" class="space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Email address</label>
        <input
          v-model="form.email"
          type="email"
          autocomplete="email"
          required
          class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500"
        />
        <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-400">{{ form.errors.email }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">New password</label>
        <input
          v-model="form.password"
          type="password"
          autocomplete="new-password"
          required
          class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500"
          placeholder="••••••••"
        />
        <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-400">{{ form.errors.password }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Confirm password</label>
        <input
          v-model="form.password_confirmation"
          type="password"
          autocomplete="new-password"
          required
          class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500"
          placeholder="••••••••"
        />
        <p v-if="form.errors.password_confirmation" class="mt-1.5 text-xs text-red-400">{{ form.errors.password_confirmation }}</p>
      </div>

      <button
        type="submit"
        :disabled="form.processing"
        class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-medium rounded-lg px-4 py-2.5 text-sm transition-colors"
      >
        {{ form.processing ? 'Resetting…' : 'Reset password' }}
      </button>
    </form>
  </div>
</template>
