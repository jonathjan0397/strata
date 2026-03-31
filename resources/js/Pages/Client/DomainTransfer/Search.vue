<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, useForm } from '@inertiajs/vue3'

defineOptions({ layout: AppLayout })

const form = useForm({
    domain:    '',
    auth_code: '',
})

function proceed() {
    form.get(route('client.domain-transfer.checkout'))
}
</script>

<template>
  <div class="max-w-lg mx-auto">
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900">Transfer a Domain</h1>
      <p class="mt-1 text-sm text-gray-500">
        Move a domain you own at another registrar to this platform. You'll need the domain's EPP / authorization code.
      </p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <form @submit.prevent="proceed" class="space-y-5">

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Domain Name</label>
          <input v-model="form.domain" type="text" required placeholder="example.com"
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
          <p v-if="form.errors.domain" class="mt-1 text-xs text-red-500">{{ form.errors.domain }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">EPP / Authorization Code</label>
          <input v-model="form.auth_code" type="text" required placeholder="Auth code from current registrar"
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-400" />
          <p class="mt-1 text-xs text-gray-400">Obtain this from your current registrar's control panel (sometimes called EPP code, transfer key, or auth-info).</p>
          <p v-if="form.errors.auth_code" class="mt-1 text-xs text-red-500">{{ form.errors.auth_code }}</p>
        </div>

        <div class="pt-2 flex items-center justify-between">
          <Link :href="route('client.domain-order.search')" class="text-xs text-gray-400 hover:text-gray-600">Register a new domain instead</Link>
          <button type="submit" :disabled="form.processing"
            class="px-5 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50 transition-colors">
            {{ form.processing ? 'Checking…' : 'Continue →' }}
          </button>
        </div>

      </form>
    </div>

    <!-- Info callout -->
    <div class="mt-5 rounded-xl border border-blue-100 bg-blue-50 p-4 text-xs text-blue-700 space-y-1">
      <p class="font-semibold">Before you transfer</p>
      <ul class="list-disc list-inside space-y-0.5 text-blue-600">
        <li>Unlock the domain at your current registrar</li>
        <li>Disable WHOIS privacy if required by your registrar</li>
        <li>Domain must not have been registered or transferred within the last 60 days</li>
        <li>Transfers typically complete within 5–7 days</li>
      </ul>
    </div>
  </div>
</template>
