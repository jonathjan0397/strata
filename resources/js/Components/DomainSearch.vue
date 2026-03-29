<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const t = computed(() => {
  const theme = page.props.portalTheme ?? 'blue'
  const THEMES = {
    blue:      { btn1:'#0ea5e9', btn2:'#6366f1', accent:'56,189,248' },
    red:       { btn1:'#dc2626', btn2:'#b91c1c', accent:'248,113,113' },
    green:     { btn1:'#059669', btn2:'#047857', accent:'52,211,153' },
    lightblue: { btn1:'#3b82f6', btn2:'#6366f1', accent:'147,197,253' },
  }
  return THEMES[theme] ?? THEMES.blue
})

const query   = ref('')
const loading = ref(false)
const results = ref([])
const error   = ref(null)
const searched = ref(false)

async function search() {
  const sld = query.value.trim().split('.')[0]
  if (!sld) return
  loading.value = true
  error.value   = null
  results.value = []
  searched.value = false

  try {
    const res = await fetch(route('domain.search') + '?domain=' + encodeURIComponent(sld), {
      headers: { Accept: 'application/json' },
    })
    const json = await res.json()
    if (json.error) { error.value = json.error; return }
    results.value = json.results ?? []
    searched.value = true
  } catch {
    error.value = 'Search failed. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div>
    <!-- Search form -->
    <form @submit.prevent="search" class="flex gap-2 max-w-xl mx-auto">
      <div class="flex-1 relative">
        <div class="absolute inset-y-0 left-3.5 flex items-center pointer-events-none">
          <svg class="h-4 w-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
        </div>
        <input
          v-model="query"
          type="text"
          placeholder="Search for a domain name…"
          autocomplete="off"
          spellcheck="false"
          class="w-full pl-10 pr-4 py-3 rounded-xl text-white placeholder-white/40 text-sm focus:outline-none focus:ring-2"
          style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(8px);"
          :style="{ '--tw-ring-color': `rgba(${t.accent},0.5)` }"
        />
      </div>
      <button type="submit" :disabled="!query.trim() || loading"
        class="px-5 py-3 rounded-xl text-sm font-semibold text-white disabled:opacity-50 transition-all whitespace-nowrap"
        :style="`background: linear-gradient(135deg, ${t.btn1}, ${t.btn2}); box-shadow: 0 2px 12px rgba(${t.accent},0.35);`">
        {{ loading ? 'Searching…' : 'Search' }}
      </button>
    </form>

    <!-- Error -->
    <div v-if="error" class="mt-4 text-center text-sm text-red-300">{{ error }}</div>

    <!-- Results -->
    <div v-if="searched && results.length" class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-xl mx-auto">
      <div v-for="r in results" :key="r.domain"
        class="flex items-center justify-between rounded-xl px-4 py-3 transition-all"
        :style="r.available === true
          ? 'background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.4);'
          : r.available === false
            ? 'background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25);'
            : 'background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);'">
        <div>
          <p class="text-white font-semibold text-sm">{{ r.domain }}</p>
          <p v-if="r.available === true && r.price" class="text-xs text-green-300 mt-0.5">
            ${{ r.price }} {{ r.currency }}/yr
          </p>
          <p v-else-if="r.error" class="text-xs text-white/40 mt-0.5">Unavailable to check</p>
        </div>
        <div class="shrink-0 ml-3">
          <template v-if="r.available === true">
            <a :href="route('register')"
              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
              :style="`background: linear-gradient(135deg, ${t.btn1}, ${t.btn2});`">
              Register
            </a>
          </template>
          <template v-else-if="r.available === false">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium text-red-300"
              style="background: rgba(239,68,68,0.15);">
              Taken
            </span>
          </template>
          <template v-else>
            <span class="text-xs text-white/30">—</span>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>
