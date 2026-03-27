<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: PortalLayout })

defineProps({ announcements: Object, siteName: String })

function fmt(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
}
</script>

<template>
  <div class="max-w-3xl mx-auto px-4 sm:px-6 py-16">

    <div class="text-center mb-12">
      <h1 class="text-3xl sm:text-4xl font-bold text-white mb-3">Announcements</h1>
      <p class="text-white/50">Stay up to date with the latest news and updates.</p>
    </div>

    <div v-if="announcements?.data?.length" class="space-y-5">
      <article
        v-for="a in announcements.data" :key="a.id"
        class="rounded-2xl p-6 transition-all"
        style="background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12); backdrop-filter: blur(12px);">

        <div class="flex items-start gap-4">
          <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0"
            style="background: rgba(56,189,248,0.15); border: 1px solid rgba(56,189,248,0.25);">
            <svg class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-3 mb-2">
              <h2 class="font-semibold text-white text-lg leading-snug">{{ a.title }}</h2>
              <time class="text-xs text-white/40 shrink-0">{{ fmt(a.published_at) }}</time>
            </div>
            <p class="text-sm text-white/65 leading-relaxed whitespace-pre-wrap">{{ a.body }}</p>
          </div>
        </div>
      </article>
    </div>

    <div v-else class="text-center py-20 text-white/40">
      <svg class="w-12 h-12 mx-auto mb-4 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
      </svg>
      No announcements yet.
    </div>

    <!-- Pagination -->
    <div v-if="announcements?.links?.length > 3" class="mt-8 flex justify-center gap-1">
      <template v-for="link in announcements.links" :key="link.label">
        <Link
          v-if="link.url"
          :href="link.url"
          class="px-3 py-1.5 rounded-lg text-sm transition-colors"
          :style="link.active
            ? 'background: linear-gradient(135deg,#0ea5e9,#6366f1); color:#fff;'
            : 'background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.6); border: 1px solid rgba(255,255,255,0.12);'"
          v-html="link.label"
        />
        <span v-else
          class="px-3 py-1.5 rounded-lg text-sm text-white/25"
          v-html="link.label"
        />
      </template>
    </div>

    <!-- Widget embed example -->
    <div class="mt-16 rounded-2xl p-6"
      style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(8px);">
      <h3 class="text-sm font-semibold text-white mb-2">Embed on your website</h3>
      <div class="rounded-xl p-4 font-mono text-xs text-sky-200 overflow-x-auto"
        style="background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.06);">
        <div>&lt;div data-strata-widget=<span class="text-amber-300">"announcements"</span> data-strata-url=<span class="text-amber-300">"{{ $page.props.ziggy?.url ?? '' }}"</span>&gt;&lt;/div&gt;</div>
        <div class="mt-1">&lt;script src=<span class="text-amber-300">"{{ $page.props.ziggy?.url ?? '' }}/strata-widget.js"</span> async&gt;&lt;/script&gt;</div>
      </div>
    </div>
  </div>
</template>
