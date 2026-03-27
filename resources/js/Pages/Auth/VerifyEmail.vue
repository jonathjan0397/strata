<script setup>
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

defineOptions({ layout: GuestLayout })

defineProps({
  status: String,
})

const page         = usePage()
const verifiedFlag = computed(() => new URLSearchParams(window.location.search).get('verified') === '1')

const form = useForm({})

function resend() {
  form.post(route('verification.send'))
}
</script>

<template>
  <div>
    <div class="flex justify-center mb-5">
      <div class="flex h-14 w-14 items-center justify-center rounded-full bg-indigo-900/50 text-indigo-400">
        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
        </svg>
      </div>
    </div>

    <h2 class="text-xl font-semibold text-white text-center mb-2">Verify your email</h2>
    <p class="text-sm text-gray-400 text-center mb-6">
      We sent a verification link to your email address. Click the link to activate your account.
    </p>

    <div v-if="status === 'verification-link-sent'" class="mb-5 rounded-lg bg-green-900/30 border border-green-800 px-4 py-3 text-sm text-green-400 text-center">
      A new verification link has been sent to your email.
    </div>

    <div v-if="verifiedFlag" class="mb-5 rounded-lg bg-green-900/30 border border-green-800 px-4 py-3 text-sm text-green-400 text-center">
      Your email has been verified.
    </div>

    <button
      :disabled="form.processing"
      class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-medium rounded-lg px-4 py-2.5 text-sm transition-colors"
      @click="resend"
    >
      {{ form.processing ? 'Sending…' : 'Resend verification email' }}
    </button>

    <p class="mt-4 text-center text-sm text-gray-500">
      Wrong account?
      <a :href="route('logout')" class="text-indigo-400 hover:text-indigo-300"
         @click.prevent="$inertia.post(route('logout'))">
        Sign out
      </a>
    </p>
  </div>
</template>
