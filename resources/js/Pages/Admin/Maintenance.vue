<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import axios from 'axios'

defineOptions({ layout: AppLayout })

const ACTIONS = [
    {
        key:     'migrate',
        label:   'Run Database Migrations',
        desc:    'Applies any pending migrations. Safe to run at any time — skips already-run migrations.',
        route:   '/admin/maintenance/migrate',
        color:   'indigo',
    },
    {
        key:     'cache',
        label:   'Clear Application Cache',
        desc:    'Flushes the runtime cache (config, routes, views, compiled classes). Run after deployments.',
        route:   '/admin/maintenance/cache',
        color:   'amber',
    },
]

const results = ref({})   // key → { loading, success, output, error }

async function run(action) {
    results.value[action.key] = { loading: true, success: null, output: '', error: '' }
    try {
        const { data } = await axios.post(action.route)
        results.value[action.key] = {
            loading: false,
            success: data.success,
            output:  data.output  ?? '',
            error:   data.error   ?? '',
        }
    } catch (e) {
        results.value[action.key] = {
            loading: false,
            success: false,
            output:  '',
            error:   e.response?.data?.error ?? e.message ?? 'Unknown error',
        }
    }
}
</script>

<template>
  <div class="max-w-2xl">
    <div class="mb-6">
      <h1 class="text-xl font-bold text-gray-900">Maintenance</h1>
      <p class="text-sm text-gray-500 mt-0.5">Database and cache management tools. All actions are safe to run on a live installation.</p>
    </div>

    <div class="space-y-4">
      <div v-for="action in ACTIONS" :key="action.key"
           class="bg-white rounded-xl border border-gray-200 p-5">

        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-sm font-semibold text-gray-900">{{ action.label }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ action.desc }}</p>
          </div>
          <button @click="run(action)"
            :disabled="results[action.key]?.loading"
            :class="action.color === 'indigo'
              ? 'bg-indigo-600 hover:bg-indigo-500 focus:ring-indigo-500'
              : 'bg-amber-500 hover:bg-amber-400 focus:ring-amber-400'"
            class="shrink-0 inline-flex items-center gap-1.5 text-sm font-medium text-white px-4 py-2 rounded-lg disabled:opacity-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2">
            <svg v-if="results[action.key]?.loading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            {{ results[action.key]?.loading ? 'Running…' : 'Run' }}
          </button>
        </div>

        <!-- Result panel -->
        <div v-if="results[action.key] && !results[action.key].loading" class="mt-4">
          <div :class="results[action.key].success
            ? 'bg-green-50 border-green-200 text-green-800'
            : 'bg-red-50 border-red-200 text-red-800'"
            class="rounded-lg border px-3 py-2 text-xs font-mono whitespace-pre-wrap leading-relaxed">
            <span v-if="results[action.key].success" class="font-semibold block mb-1">✓ Success</span>
            <span v-else class="font-semibold block mb-1">✗ Failed</span>
            {{ results[action.key].output || results[action.key].error }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
