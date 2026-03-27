<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Link } from '@inertiajs/vue3'

defineOptions({ layout: PortalLayout })

defineProps({
  article:  Object,
  related:  Array,
  siteName: String,
})
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 sm:px-6 py-16">

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-white/40 mb-8">
      <Link href="/kb" class="hover:text-white/70 transition-colors">Help Center</Link>
      <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
      </svg>
      <Link v-if="article.category" href="/kb" class="hover:text-white/70 transition-colors">
        {{ article.category.name }}
      </Link>
      <svg v-if="article.category" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
      </svg>
      <span class="text-white/70 truncate max-w-xs">{{ article.title }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

      <!-- Article -->
      <div class="lg:col-span-3">
        <div class="rounded-2xl p-8"
          style="background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12); backdrop-filter: blur(14px);">

          <h1 class="text-2xl sm:text-3xl font-bold text-white mb-4 leading-tight">{{ article.title }}</h1>

          <div class="flex items-center gap-4 text-xs text-white/40 mb-8 pb-6"
            style="border-bottom: 1px solid rgba(255,255,255,0.1);">
            <span v-if="article.author">By {{ article.author.name }}</span>
            <span v-if="article.views">{{ article.views }} views</span>
          </div>

          <!-- Article body -->
          <div class="prose prose-invert max-w-none text-white/80 leading-relaxed"
            style="
              --tw-prose-body: rgba(255,255,255,0.75);
              --tw-prose-headings: #fff;
              --tw-prose-links: #38bdf8;
              --tw-prose-code: #38bdf8;
              --tw-prose-pre-bg: rgba(0,0,0,0.3);
              --tw-prose-hr: rgba(255,255,255,0.1);
            ">
            <div style="white-space: pre-wrap; color: rgba(255,255,255,0.78); line-height: 1.75; font-size: 0.9375rem;">{{ article.body }}</div>
          </div>
        </div>

        <!-- Feedback / support nudge -->
        <div class="mt-6 rounded-2xl p-5 flex flex-col sm:flex-row items-center justify-between gap-4"
          style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(8px);">
          <p class="text-sm text-white/50">Was this article helpful?</p>
          <div class="flex gap-3">
            <button class="px-4 py-1.5 rounded-lg text-sm text-white/70 transition-colors"
              style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);"
              onmouseover="this.style.background='rgba(16,185,129,0.2)';this.style.color='#6ee7b7'"
              onmouseout="this.style.background='rgba(255,255,255,0.08)';this.style.color='rgba(255,255,255,0.7)'">
              👍 Yes
            </button>
            <Link href="/login"
              class="px-4 py-1.5 rounded-lg text-sm text-white/70 transition-colors"
              style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);"
              onmouseover="this.style.background='rgba(239,68,68,0.15)';this.style.color='#fca5a5'"
              onmouseout="this.style.background='rgba(255,255,255,0.08)';this.style.color='rgba(255,255,255,0.7)'">
              👎 No — open a ticket
            </Link>
          </div>
        </div>
      </div>

      <!-- Sidebar: related articles -->
      <aside class="lg:col-span-1">
        <div class="rounded-2xl p-5 sticky top-24"
          style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px);">
          <h3 class="text-xs font-semibold uppercase tracking-wider text-sky-400/80 mb-4">Related Articles</h3>
          <ul class="space-y-3">
            <li v-for="r in related" :key="r.id">
              <Link :href="`/kb/${r.slug}`"
                class="text-sm text-white/65 hover:text-white transition-colors leading-snug hover:underline underline-offset-2 block">
                {{ r.title }}
              </Link>
            </li>
            <li v-if="!related?.length" class="text-sm text-white/30 italic">No related articles.</li>
          </ul>

          <div class="mt-6 pt-5" style="border-top: 1px solid rgba(255,255,255,0.1);">
            <Link href="/kb"
              class="block w-full text-center py-2 rounded-lg text-xs font-medium text-white/70 transition-colors"
              style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15);"
              onmouseover="this.style.background='rgba(255,255,255,0.14)'"
              onmouseout="this.style.background='rgba(255,255,255,0.08)'">
              ← Back to Help Center
            </Link>
          </div>
        </div>
      </aside>

    </div>
  </div>
</template>
