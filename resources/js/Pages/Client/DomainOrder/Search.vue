<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

defineOptions({ layout: AppLayout })

defineProps({ defaultTlds: Array })

const query    = ref('')
const loading  = ref(false)
const results  = ref([])
const searched = ref(false)
const error    = ref(null)

async function search() {
    const sld = query.value.trim().split('.')[0]
    if (!sld) return
    loading.value  = true
    error.value    = null
    results.value  = []
    searched.value = false
    try {
        const { data } = await axios.get(route('client.domain-order.check'), { params: { domain: sld } })
        if (data.error) { error.value = data.error; return }
        results.value  = data.results ?? []
        searched.value = true
    } catch (e) {
        error.value = e.response?.data?.error ?? 'Search failed. Please try again.'
    } finally {
        loading.value = false
    }
}

function order(domain) {
    router.get(route('client.domain-order.checkout'), { domain })
}

const statusStyle = (r) => {
    if (r.available === true)  return 'background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.3);'
    if (r.available === false) return 'background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.2);'
    return 'background:rgba(0,0,0,0.03);border:1px solid rgba(0,0,0,0.08);'
}
</script>

<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900">Register a Domain</h1>
      <p class="mt-1 text-sm text-gray-500">Search for an available domain and complete registration in minutes.</p>
    </div>

    <!-- Search box -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
      <form @submit.prevent="search" class="flex gap-3 max-w-2xl">
        <div class="flex-1 relative">
          <div class="absolute inset-y-0 left-3.5 flex items-center pointer-events-none">
            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
          </div>
          <input
            v-model="query"
            type="text"
            placeholder="yourname"
            autocomplete="off"
            spellcheck="false"
            class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-200 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
        </div>
        <button type="submit" :disabled="!query.trim() || loading"
          class="px-5 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50 transition-colors whitespace-nowrap">
          {{ loading ? 'Searching…' : 'Search' }}
        </button>
      </form>
      <p v-if="defaultTlds.length" class="mt-2 text-xs text-gray-400">
        Checks: {{ defaultTlds.join(', ') }}
      </p>
    </div>

    <!-- Error -->
    <div v-if="error" class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
      {{ error }}
    </div>

    <!-- Results -->
    <div v-if="searched" class="space-y-2">
      <div v-for="r in results" :key="r.domain"
        class="flex items-center justify-between rounded-xl px-5 py-4 transition-all"
        :style="statusStyle(r)">
        <div class="flex items-center gap-4">
          <div>
            <p class="font-semibold text-gray-900 font-mono">{{ r.domain }}</p>
            <p v-if="r.available === true && r.price" class="text-xs text-green-700 mt-0.5">
              ${{ Number(r.price).toFixed(2) }} {{ r.currency }}/yr
            </p>
            <p v-else-if="r.available === true" class="text-xs text-green-600 mt-0.5">Available</p>
            <p v-else-if="r.available === false" class="text-xs text-red-500 mt-0.5">Already registered</p>
            <p v-else class="text-xs text-gray-400 mt-0.5">Could not check</p>
          </div>
        </div>
        <div class="shrink-0 ml-4">
          <button v-if="r.available === true && r.has_pricing"
            @click="order(r.domain)"
            class="px-4 py-1.5 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition-colors">
            Register
          </button>
          <span v-else-if="r.available === true"
            class="text-xs text-gray-400 italic">Contact us to order</span>
          <span v-else-if="r.available === false"
            class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium text-red-600 bg-red-50">
            Taken
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
