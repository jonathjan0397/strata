<script setup>
import { useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

defineOptions({ layout: GuestLayout })

defineProps({
  canResetPassword: Boolean,
})

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

function submit() {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <div>
    <h2 class="text-xl font-semibold text-white mb-6 text-center">Sign in to your account</h2>

    <div v-if="$page.props.flash?.status" class="mb-4 text-sm text-green-400 bg-green-900/30 border border-green-800 rounded-lg px-4 py-3">
      {{ $page.props.flash.status }}
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

      <div>
        <div class="flex items-center justify-between mb-1.5">
          <label class="block text-sm font-medium text-gray-300">Password</label>
          <a v-if="canResetPassword" :href="route('password.request')" class="text-xs text-indigo-400 hover:text-indigo-300">
            Forgot password?
          </a>
        </div>
        <input
          v-model="form.password"
          type="password"
          autocomplete="current-password"
          required
          class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500"
          placeholder="••••••••"
        />
        <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-400">{{ form.errors.password }}</p>
      </div>

      <div class="flex items-center gap-2">
        <input
          id="remember"
          v-model="form.remember"
          type="checkbox"
          class="h-4 w-4 rounded border-gray-600 bg-gray-800 text-indigo-500 focus:ring-indigo-500"
        />
        <label for="remember" class="text-sm text-gray-400">Keep me signed in</label>
      </div>

      <button
        type="submit"
        :disabled="form.processing"
        class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-medium rounded-lg px-4 py-2.5 text-sm transition-colors"
      >
        {{ form.processing ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>
  </div>
</template>
