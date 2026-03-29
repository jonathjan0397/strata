<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page       = usePage()
const siteName   = computed(() => page.props.siteName  ?? 'Client Portal')
const logoUrl    = computed(() => page.props.logoUrl   ?? null)
const appVersion = computed(() => page.props.appVersion ?? null)
</script>

<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-gray-950 px-4">
    <!-- Branding -->
    <div class="mb-8 flex flex-col items-center">
      <template v-if="logoUrl">
        <img :src="logoUrl" :alt="siteName" class="h-12 w-auto mb-1 object-contain" />
      </template>
      <template v-else>
        <div class="h-12 w-12 rounded-xl flex items-center justify-center text-white font-bold text-xl mb-1"
          style="background: linear-gradient(135deg, #38bdf8, #6366f1);">
          {{ siteName.charAt(0).toUpperCase() }}
        </div>
        <h1 class="text-xl font-bold text-white tracking-tight text-center">{{ siteName }}</h1>
      </template>
      <p class="text-gray-600 text-xs mt-2">
        Powered by Strata Service Billing and Support Platform<span v-if="appVersion" class="text-gray-700"> · {{ appVersion }}</span>
      </p>
    </div>

    <!-- Card -->
    <div class="w-full max-w-md bg-gray-900 rounded-2xl shadow-xl border border-gray-800 p-8">
      <slot />
    </div>
  </div>
</template>
