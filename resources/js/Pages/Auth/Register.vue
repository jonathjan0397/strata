<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'

defineOptions({ layout: GuestLayout })

const oauth = computed(() => usePage().props.oauthProviders ?? {})

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post(route('register'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <div>
    <h2 class="text-xl font-semibold text-white mb-6 text-center">Create your account</h2>

    <form @submit.prevent="submit" class="space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Full name</label>
        <input
          v-model="form.name"
          type="text"
          autocomplete="name"
          required
          class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-500"
          placeholder="Jane Smith"
        />
        <p v-if="form.errors.name" class="mt-1.5 text-xs text-red-400">{{ form.errors.name }}</p>
      </div>

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
        <label class="block text-sm font-medium text-gray-300 mb-1.5">Password</label>
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
      </div>

      <button
        type="submit"
        :disabled="form.processing"
        class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-medium rounded-lg px-4 py-2.5 text-sm transition-colors"
      >
        {{ form.processing ? 'Creating account…' : 'Create account' }}
      </button>

      <p class="text-center text-sm text-gray-500">
        Already have an account?
        <a :href="route('login')" class="text-indigo-400 hover:text-indigo-300">Sign in</a>
      </p>
    </form>

    <div v-if="oauth.google || oauth.microsoft" class="mt-6">
      <div class="relative">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-700" /></div>
        <div class="relative flex justify-center text-xs text-gray-500"><span class="bg-gray-900 px-3">or sign up with</span></div>
      </div>

      <div class="mt-4 gap-3" :class="(oauth.google && oauth.microsoft) ? 'grid grid-cols-2' : 'flex'">
        <a v-if="oauth.google"
          :href="route('socialite.redirect', 'google')"
          class="flex items-center justify-center gap-2 rounded-lg border border-gray-700 bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-300 hover:bg-gray-700 transition-colors"
        >
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
          </svg>
          Google
        </a>
        <a v-if="oauth.microsoft"
          :href="route('socialite.redirect', 'microsoft')"
          class="flex items-center justify-center gap-2 rounded-lg border border-gray-700 bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-300 hover:bg-gray-700 transition-colors"
        >
          <svg class="h-4 w-4" viewBox="0 0 23 23" fill="none">
            <path fill="#f3f3f3" d="M0 0h23v23H0z"/>
            <path fill="#f35325" d="M1 1h10v10H1z"/>
            <path fill="#81bc06" d="M12 1h10v10H12z"/>
            <path fill="#05a6f0" d="M1 12h10v10H1z"/>
            <path fill="#ffba08" d="M12 12h10v10H12z"/>
          </svg>
          Microsoft
        </a>
      </div>
    </div>
  </div>
</template>
