<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

defineOptions({ layout: PortalLayout })

const props = defineProps({ categories: Array, siteName: String })

const search = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.categories ?? []
  return (props.categories ?? []).map(cat => ({
    ...cat,
    published_articles: (cat.published_articles ?? []).filter(a =>
      a.title.toLowerCase().includes(q)
    ),
  })).filter(cat => cat.name.toLowerCase().includes(q) || cat.published_articles.length)
})
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 py-16">

    <!-- Header -->
    <div class="text-center mb-12">
      <h1 class="text-3xl sm:text-4xl font-bold text-white mb-4">Help Center</h1>
      <p class="text-white/55 mb-8">Browse our knowledge base or search for a topic.</p>

      <!-- Search -->
      <div class="relative max-w-md mx-auto">
        <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-white/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input
          v-model="search"
          type="search"
          placeholder="Search articles…"
          class="w-full pl-10 pr-4 py-3 rounded-xl text-sm text-white placeholder-white/40 outline-none transition-all"
          style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(8px);"
          onfocus="this.style.borderColor='rgba(56,189,248,0.5)';this.style.boxShadow='0 0 0 3px rgba(56,189,248,0.15)'"
          onblur="this.style.borderColor='rgba(255,255,255,0.2)';this.style.boxShadow='none'"
        />
      </div>
    </div>

    <!-- Categories grid -->
    <div v-if="filtered.length" class="grid grid-cols-1 sm:grid-cols-2 gap-6">
      <div
        v-for="cat in filtered" :key="cat.id"
        class="rounded-2xl p-6"
        style="background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12); backdrop-filter: blur(12px);">

        <div class="flex items-center gap-3 mb-4">
          <div class="h-9 w-9 rounded-xl flex items-center justify-center shrink-0"
            style="background: rgba(56,189,248,0.2); border: 1px solid rgba(56,189,248,0.3);">
            <svg class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
            </svg>
          </div>
          <div>
            <h2 class="font-semibold text-white">{{ cat.name }}</h2>
            <p v-if="cat.description" class="text-xs text-white/45 mt-0.5">{{ cat.description }}</p>
          </div>
        </div>

        <ul class="space-y-2">
          <li v-for="art in cat.published_articles" :key="art.id">
            <Link :href="`/kb/${art.slug}`"
              class="flex items-center gap-2 text-sm text-white/70 hover:text-white transition-colors group">
              <svg class="w-3 h-3 text-white/30 group-hover:text-sky-400 transition-colors shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
              </svg>
              <span class="group-hover:underline underline-offset-2">{{ art.title }}</span>
            </Link>
          </li>
          <li v-if="!cat.published_articles?.length" class="text-sm text-white/35 italic">
            No articles yet.
          </li>
        </ul>
      </div>
    </div>

    <div v-else class="text-center py-16 text-white/40">
      <svg class="w-12 h-12 mx-auto mb-4 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
      </svg>
      No articles match your search.
    </div>

    <!-- Support CTA -->
    <div class="mt-12 text-center rounded-2xl p-8"
      style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(8px);">
      <h3 class="font-semibold text-white mb-2">Can't find what you're looking for?</h3>
      <p class="text-sm text-white/50 mb-5">Our support team is available 24/7 to help you.</p>
      <Link href="/login"
        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-semibold text-white"
        style="background: linear-gradient(135deg, #0ea5e9, #6366f1); box-shadow: 0 2px 12px rgba(14,165,233,0.3);">
        Open a Support Ticket
      </Link>
    </div>
  </div>
</template>
